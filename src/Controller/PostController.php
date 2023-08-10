<?php

namespace App\Controller;

use App\Authentication\User;
use App\Job\CreatePostJob;
use App\JobStamp\MetadataStamp;
use App\Lemmy\LemmyApiFactory;
use App\Service\CurrentUserService;
use App\Service\JobManager;
use DateTimeImmutable;
use DateTimeInterface;
use LogicException;
use Psr\Cache\CacheItemPoolInterface;
use Rikudou\LemmyApi\Exception\LemmyApiException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/post')]
final class PostController extends AbstractController
{
    public function __construct(
        private readonly CacheItemPoolInterface $cache,
    ) {
    }

    #[Route('/list', name: 'app.post.list', methods: [Request::METHOD_GET])]
    public function listPosts(JobManager $jobManager, Request $request): Response
    {
        $jobs = array_filter($jobManager->listJobs(), function (Envelope $envelope) use ($jobManager) {
            $jobId = $envelope->last(MetadataStamp::class)?->metadata['jobId'];
            return $envelope->getMessage() instanceof CreatePostJob && $jobId && !$jobManager->isCancelled($jobId);
        });
        $jobs = array_map(function (Envelope $job) {
            $message = $job->getMessage();
            assert($message instanceof CreatePostJob);

            $community = "!{$message->community->name}@" . parse_url($message->community->actorId, PHP_URL_HOST);
            $title = $message->title;

            $metadata = $job->last(MetadataStamp::class);
            assert($metadata !== null);

            $dateTime = $metadata->metadata['expiresAt'];
            assert($dateTime instanceof DateTimeInterface);
            $id = $metadata->metadata['jobId'];

            return [
                'jobId' => $id,
                'dateTime' => $dateTime->format('c'),
                'community' => $community,
                'title' => $title,
            ];
        }, $jobs);

        return $this->render('post/list.html.twig', [
            'jobs' => $jobs,
        ]);
    }

    #[Route('/create', name: 'app.post.create', methods: [Request::METHOD_GET])]
    public function createPost(): Response {
        return $this->render('post/create.html.twig', [
            'communities' => $this->getCommunities(),
            'selectedCommunities' => [],
        ]);
    }

    #[Route('/create/do', name: 'app.post.create.do', methods: [Request::METHOD_POST])]
    public function doCreatePost(
        Request $request,
        LemmyApiFactory $apiFactory,
        TranslatorInterface $translator,
        JobManager $jobManager,
        CurrentUserService $currentUserService,
    ) {
        $api = $apiFactory->getForCurrentUser();
        $user = $currentUserService->getCurrentUser() ?? throw new LogicException('No user logged in');

        $data = [
            'title' => $request->request->get('title'),
            'selectedCommunities' => $request->request->all('communities'),
            'url' => $request->request->get('url'),
            'text' => $request->request->get('text'),
            'nsfw' => $request->request->getBoolean('nsfw'),
            'scheduleDateTime' => $request->request->get('scheduleDateTime'),
            'timezoneOffset' => $request->request->get('timezoneOffset'),
        ];

        $errorResponse = fn() => $this->render('post/create.html.twig', [
            ...$data,
            'communities' => $this->getCommunities(),
        ], new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY));

        if (!$data['title']) {
            $this->addFlash('error', $translator->trans('The post must have a title.'));
            return $errorResponse();
        }
        if (!$data['scheduleDateTime']) {
            $this->addFlash('error', $translator->trans('The schedule date and time must be set.'));
            return $errorResponse();
        }
        if (!$data['timezoneOffset']) {
            $this->addFlash('error', $translator->trans('Failed to get your timezone offset. Do you have javascript enabled?'));
            return $errorResponse();
        }

        $communities = $data['selectedCommunities'];
        $communities = array_map(function(string $community) {
            if (str_starts_with($community, '!')) {
                $community = substr($community, 1);
            }

            return $community;
        }, $communities);

        try {
            $communities = array_map(fn (string $community) => $api->community()->get($community), $communities);
        } catch (LemmyApiException) {
            $this->addFlash('error', $translator->trans("Couldn't find one or more of the communities, are you sure all of them exist?"));
            return $errorResponse();
        }

        $dateTime = new DateTimeImmutable("{$data['scheduleDateTime']}:00{$data['timezoneOffset']}");
        foreach ($communities as $community) {
            $jobManager->createJob(
                new CreatePostJob(
                    jwt:$user->getJwt(),
                    instance: $user->getInstance(),
                    community: $community,
                    title: $data['title'],
                    url: $data['url'] ?: null,
                    text: $data['text'] ?: null,
                    nsfw: $data['nsfw'],
                ),
                $dateTime,
            );
        }

        $this->addFlash('success', $translator->trans('Posts have been successfully scheduled.'));
        return $this->redirectToRoute('app.post.list');
    }

    #[Route('/ajax/cancel/{jobId}', name: 'app.post.ajax.cancel', methods: [Request::METHOD_DELETE])]
    public function cancelJob(Uuid $jobId, JobManager $jobManager): JsonResponse
    {
        $jobManager->cancelJob($jobId);

        return new JsonResponse(status: Response::HTTP_NO_CONTENT);
    }

    /**
     * @return array<string>
     */
    private function getCommunities(): array
    {
        $user = $this->getUser();
        assert($user instanceof User);
        $cacheItem = $this->cache->getItem("community_list_{$user->getInstance()}");
        return $cacheItem->isHit() ? $cacheItem->get() : [];
    }
}
