<?php

namespace App\Controller;

use App\Authentication\User;
use App\Job\CreatePostJob;
use App\Job\PinUnpinPostJob;
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
    public function listPosts(JobManager $jobManager, LemmyApiFactory $apiFactory): Response
    {
        $api = $apiFactory->getForCurrentUser();

        $postCreateJobs = array_filter($jobManager->listJobs(), static function (Envelope $envelope) use ($jobManager) {
            $jobId = $envelope->last(MetadataStamp::class)?->metadata['jobId'];

            return $envelope->getMessage() instanceof CreatePostJob && $jobId && !$jobManager->isCancelled($jobId);
        });
        $postCreateJobs = array_map(static function (Envelope $job) {
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
        }, $postCreateJobs);

        $postPinJobs = array_filter($jobManager->listJobs(), static function (Envelope $envelope) use ($jobManager) {
            $jobId = $envelope->last(MetadataStamp::class)?->metadata['jobId'];

            return $envelope->getMessage() instanceof PinUnpinPostJob && $jobId && !$jobManager->isCancelled($jobId);
        });
        $postPinJobs = array_map(static function (Envelope $job) use ($api) {
            $message = $job->getMessage();
            assert($message instanceof PinUnpinPostJob);

            $metadata = $job->last(MetadataStamp::class);
            assert($metadata !== null);

            $dateTime = $metadata->metadata['expiresAt'];
            assert($dateTime instanceof DateTimeInterface);
            $id = $metadata->metadata['jobId'];

            try {
                $post = $api->post()->get($message->postId);
            } catch (LemmyApiException) {
                $post = null;
            }

            return [
                'jobId' => $id,
                'dateTime' => $dateTime->format('c'),
                'url' => "https://{$message->instance}/post/{$message->postId}",
                'title' => $post?->post->name ?? 'Unknown',
                'pin' => $message->pin,
            ];
        }, $postPinJobs);

        return $this->render('post/list.html.twig', [
            'postCreateJobs' => $postCreateJobs,
            'postPinJobs' => $postPinJobs,
        ]);
    }

    #[Route('/detail/{jobId}', name: 'app.post.detail', methods: [Request::METHOD_GET])]
    public function detail(Uuid $jobId, JobManager $jobManager): Response
    {
        $job = $jobManager->getJob($jobId);
        $message = $job->getMessage();
        if (!$message instanceof CreatePostJob) {
            throw $this->createNotFoundException();
        }

        $job = [
            'jobId' => (string) $jobId,
            'text' => $message->text,
            'url' => $message->url,
            'title' => $message->title,
            'community' => sprintf('!%s@%s', $message->community->name, parse_url($message->community->actorId, PHP_URL_HOST)),
            'nsfw' => $message->nsfw,
            'pinToCommunity' => $message->pinToCommunity,
        ];

        return $this->render('post/detail.html.twig', [
            'job' => $job,
        ]);
    }

    #[Route('/create', name: 'app.post.create', methods: [Request::METHOD_GET])]
    public function createPost(): Response
    {
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
            'pinToCommunity' => $request->request->getBoolean('pinToCommunity'),
        ];

        $errorResponse = fn () => $this->render('post/create.html.twig', [
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
        $communities = array_map(static function (string $community) {
            if (str_starts_with($community, '!')) {
                $community = substr($community, 1);
            }

            return $community;
        }, $communities);

        try {
            $communities = array_map(static fn (string $community) => $api->community()->get($community), $communities);
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
                    pinToCommunity: $data['pinToCommunity'],
                ),
                $dateTime,
            );
        }

        $this->addFlash('success', $translator->trans('Posts have been successfully scheduled.'));

        return $this->redirectToRoute('app.post.list');
    }

    #[Route('/pin', name: 'app.post.pin', methods: [Request::METHOD_GET])]
    public function pinPost(): Response
    {
        return $this->render('post/pin.html.twig');
    }

    #[Route('/pin/do', name: 'app.post.pin.do', methods: [Request::METHOD_POST])]
    public function doPinPost(
        Request $request,
        TranslatorInterface $translator,
        JobManager $jobManager,
        CurrentUserService $currentUserService,
    ): Response {
        $errorResponse = fn () => $this->render('post/pin.html.twig');

        $urlOrId = $request->request->get('urlOrId');

        if (!$urlOrId) {
            $this->addFlash('error', $translator->trans('ID or URL is required'));

            return $errorResponse();
        }
        if (!$request->request->has('pin')) {
            $this->addFlash('error', $translator->trans('Either pin or unpin must be selected'));

            return $errorResponse();
        }
        if (!$request->request->get('scheduleDateTime')) {
            $this->addFlash('error', $translator->trans('The schedule date and time must be set.'));

            return $errorResponse();
        }
        if (!$request->request->get('timezoneOffset')) {
            $this->addFlash('error', $translator->trans('Failed to get your timezone offset. Do you have javascript enabled?'));

            return $errorResponse();
        }

        if (!is_numeric($urlOrId)) {
            assert(is_string($urlOrId));
            $regex = /** @lang RegExp */ "@https://{$currentUserService->getCurrentUser()?->getInstance()}/post/([^/\s]+)@";
            if (!preg_match($regex, $urlOrId, $matches)) {
                return new JsonResponse([
                    'error' => 'The URL or ID must be a numeric post ID or URL on the same instance as your user',
                ], status: Response::HTTP_BAD_REQUEST);
            }

            $urlOrId = $matches[1];
            if (!is_numeric($urlOrId)) {
                $this->addFlash('error', 'Failed extracting ID from URL, please provide the ID manually.');

                return $errorResponse();
            }
        }

        $dateTime = new DateTimeImmutable("{$request->request->get('scheduleDateTime')}:00{$request->request->get('timezoneOffset')}");
        $jobManager->createJob(
            new PinUnpinPostJob(
                postId: (int) $urlOrId,
                jwt: $currentUserService->getCurrentUser()?->getJwt() ?? throw new LogicException('No user logged in'),
                instance: $currentUserService->getCurrentUser()?->getInstance() ?? throw new LogicException('No user logged in'),
                pin: $request->request->getBoolean('pin'),
            ),
            $dateTime,
        );
        $this->addFlash('success', $translator->trans('Post pin/unpin was successfully scheduled.'));

        return $this->redirectToRoute('app.post.list');
    }

    #[Route('/ajax/cancel/{jobId}', name: 'app.post.ajax.cancel', methods: [Request::METHOD_DELETE])]
    public function cancelJob(Uuid $jobId, JobManager $jobManager): JsonResponse
    {
        $jobManager->cancelJob($jobId);

        return new JsonResponse(status: Response::HTTP_NO_CONTENT);
    }

    #[Route('/ajax/fetch-post', name: 'app.post.ajax.fetch', methods: [Request::METHOD_POST])]
    public function loadPost(
        Request $request,
        LemmyApiFactory $apiFactory,
        CurrentUserService $currentUserService,
    ): JsonResponse {
        $urlOrId = $request->request->get('urlOrId');
        if (!$urlOrId) {
            return new JsonResponse([
                'error' => 'Missing required parameters',
            ], status: Response::HTTP_BAD_REQUEST);
        }

        if (!is_numeric($urlOrId)) {
            assert(is_string($urlOrId));
            $regex = /** @lang RegExp */ "@https://{$currentUserService->getCurrentUser()?->getInstance()}/post/([^/\s]+)@";
            if (!preg_match($regex, $urlOrId, $matches)) {
                return new JsonResponse([
                    'error' => 'The URL or ID must be a numeric post ID or URL on the same instance as your user',
                ], status: Response::HTTP_BAD_REQUEST);
            }

            $urlOrId = $matches[1];
            if (!is_numeric($urlOrId)) {
                return new JsonResponse([
                    'error' => 'There was a problem with converting the URL to a post ID, please provide the post ID directly',
                ], status: Response::HTTP_NOT_IMPLEMENTED);
            }
        }

        $api = $apiFactory->getForCurrentUser();

        try {
            $post = $api->post()->get(postId: (int) $urlOrId);
        } catch (LemmyApiException) {
            return new JsonResponse([
                'error' => "Couldn't find the post",
            ], status: Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse($post);
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
