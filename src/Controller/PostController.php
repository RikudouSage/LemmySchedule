<?php

namespace App\Controller;

use App\Authentication\User;
use App\Dto\CommunityGroup;
use App\Enum\DayType;
use App\Enum\PinType;
use App\Enum\ScheduleType;
use App\Enum\Weekday;
use App\FileProvider\FileProvider;
use App\FileUploader\FileUploader;
use App\Job\CreatePostJob;
use App\Job\DeleteFileJob;
use App\Job\PinUnpinPostJob;
use App\Job\PinUnpinPostJobV2;
use App\Job\ReportUnreadPostsJob;
use App\JobStamp\MetadataStamp;
use App\Lemmy\LemmyApiFactory;
use App\Service\CommunityGroupManager;
use App\Service\CurrentUserService;
use App\Service\JobManager;
use App\Service\ScheduleExpressionParser;
use App\Service\TitleExpressionReplacer;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use LogicException;
use Psr\Cache\CacheItemPoolInterface;
use Rikudou\LemmyApi\Enum\Language;
use Rikudou\LemmyApi\Exception\LemmyApiException;
use Rikudou\LemmyApi\Response\Model\Community;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\BrowserKit\HttpBrowser;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/post')]
final class PostController extends AbstractController
{
    public function __construct(
        private readonly CacheItemPoolInterface $cache,
    ) {
    }

    #[Route('/help/expressions', name: 'app.post.expressions_help', methods: [Request::METHOD_GET])]
    public function expressionsHelp(
        TitleExpressionReplacer $expressionReplacer,
    ): Response
    {
        return $this->render('post/expressions_help.html.twig', [
            'expression' => $expressionReplacer,
        ]);
    }

    #[Route('/list', name: 'app.post.list', methods: [Request::METHOD_GET])]
    public function listPosts(JobManager $jobManager, LemmyApiFactory $apiFactory, bool $unreadPostsEnabled): Response
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
                'recurring' => $message->scheduleExpression !== null,
            ];
        }, $postCreateJobs);
        usort($postCreateJobs, static fn (array $a, array $b) => $a['dateTime'] <=> $b['dateTime']);

        $postPinJobs = array_filter($jobManager->listJobs(), static function (Envelope $envelope) use ($jobManager) {
            $jobId = $envelope->last(MetadataStamp::class)?->metadata['jobId'];

            return ($envelope->getMessage() instanceof PinUnpinPostJob || $envelope->getMessage() instanceof PinUnpinPostJobV2)
                && $jobId && !$jobManager->isCancelled($jobId);
        });
        $postPinJobs = array_map(static function (Envelope $job) use ($api) {
            $message = $job->getMessage();
            assert($message instanceof PinUnpinPostJob || $message instanceof PinUnpinPostJobV2);

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
                'pin' => $message instanceof PinUnpinPostJob
                    ? ($message->pin ? PinType::PinToCommunity : PinType::UnpinFromCommunity)
                    : $message->pin,
            ];
        }, $postPinJobs);
        usort($postPinJobs, static fn (array $a, array $b) => $a['dateTime'] <=> $b['dateTime']);

        $postReportJobs = array_map(
            fn (Envelope $job) => $this->getJobArray($job, callbacks: [
                'community' => static function (ReportUnreadPostsJob $job) {
                    if ($job->community === null) {
                        return null;
                    }
                    $host = parse_url($job->community->actorId, PHP_URL_HOST);

                    return "!{$job->community->name}@{$host}";
                },
                'user' => static function (ReportUnreadPostsJob $job) {
                    if ($job->person === null) {
                        return null;
                    }
                    $host = parse_url($job->person->actorId, PHP_URL_HOST);

                    return "@{$job->person->name}@{$host}";
                },
                'communityUrl' => static function (ReportUnreadPostsJob $job) {
                    if ($job->community === null) {
                        return null;
                    }
                    $host = parse_url($job->community->actorId, PHP_URL_HOST);
                    $community = "{$job->community->name}@{$host}";

                    return "https://{$job->instance}/c/{$community}";
                },
                'userUrl' => static function (ReportUnreadPostsJob $job) {
                    if ($job->person === null) {
                        return null;
                    }

                    $host = parse_url($job->person->actorId, PHP_URL_HOST);
                    $user = "{$job->person->name}@{$host}";

                    return "https://{$job->instance}/u/{$user}";
                },
                'recurring' => static fn (ReportUnreadPostsJob $job) => $job->scheduleExpression !== null,
            ]),
            $jobManager->getActiveJobsByType(ReportUnreadPostsJob::class),
        );
        usort($postReportJobs, static fn (array $a, array $b) => $a['dateTime'] <=> $b['dateTime']);

        return $this->render('post/list.html.twig', [
            'postCreateJobs' => $postCreateJobs,
            'postPinJobs' => $postPinJobs,
            'postReportJobs' => $postReportJobs,
            'unreadPostsEnabled' => $unreadPostsEnabled,
        ]);
    }

    #[Route('/detail/{jobId}', name: 'app.post.detail', methods: [Request::METHOD_GET])]
    public function detail(
        Uuid $jobId,
        JobManager $jobManager,
        ScheduleExpressionParser $scheduleExpressionParser,
    ): Response {
        $job = $jobManager->getJob($jobId);
        $message = $job?->getMessage();
        if (!$message instanceof CreatePostJob) {
            throw $this->createNotFoundException();
        }

        $metadata = $job->last(MetadataStamp::class);
        assert($metadata !== null);

        $dateTime = $metadata->metadata['expiresAt'];
        assert($dateTime instanceof DateTimeInterface);

        $job = [
            'jobId' => (string) $jobId,
            'text' => $message->text,
            'url' => $message->url,
            'title' => $message->title,
            'community' => sprintf('!%s@%s', $message->community->name, parse_url($message->community->actorId, PHP_URL_HOST)),
            'nsfw' => $message->nsfw,
            'pinToCommunity' => $message->pinToCommunity,
            'pinToInstance' => $message->pinToInstance,
            'language' => $message->language,
            'image' => $message->imageId,
            'recurring' => $message->scheduleExpression !== null,
            'dateTime' => $dateTime,
            'nextRuns' => $message->scheduleExpression ? [
                $scheduleExpressionParser->getNextRunDate(expression: $message->scheduleExpression, nth: 1, timeZone: new DateTimeZone((string) $message->scheduleTimezone)),
                $scheduleExpressionParser->getNextRunDate(expression: $message->scheduleExpression, nth: 2, timeZone: new DateTimeZone((string) $message->scheduleTimezone)),
                $scheduleExpressionParser->getNextRunDate(expression: $message->scheduleExpression, nth: 3, timeZone: new DateTimeZone((string) $message->scheduleTimezone)),
            ] : null,
            'unpinAt' => $message->unpinAt,
        ];

        return $this->render('post/detail.html.twig', [
            'job' => $job,
        ]);
    }

    /**
     * @param iterable<FileProvider> $fileProviders
     */
    #[Route('/create', name: 'app.post.create', methods: [Request::METHOD_GET])]
    public function createPost(
        #[TaggedIterator('app.file_provider')]
        iterable $fileProviders,
        #[Autowire('%app.default_post_language%')]
        int $defaultLanguage,
        CommunityGroupManager $groupManager,
        #[Autowire('%app.default_communities%')]
        array $defaultCommunities,
    ): Response {
        $fileProviders = [...$fileProviders];
        $default = (array_values(array_filter($fileProviders, static fn (FileProvider $fileProvider) => $fileProvider->isDefault()))[0] ?? null)?->getId();
        if ($default === null) {
            throw new LogicException('No default file provider specified');
        }

        $defaultCommunities = array_map(
            fn (string $community) => str_starts_with($community, '!') ? $community : '!' . $community,
            $defaultCommunities,
        );

        return $this->render('post/create.html.twig', [
            'communities' => $this->getCommunities(),
            'selectedCommunities' => $defaultCommunities,
            'languages' => Language::cases(),
            'selectedLanguage' => Language::tryFrom($defaultLanguage) ?? Language::Undetermined,
            'fileProviders' => [...$fileProviders],
            'defaultFileProvider' => $default,
            'groups' => [...$groupManager->getGroups()],
        ]);
    }

    #[Route('/create/do', name: 'app.post.create.do', methods: [Request::METHOD_POST])]
    public function doCreatePost(
        Request $request,
        LemmyApiFactory $apiFactory,
        TranslatorInterface $translator,
        JobManager $jobManager,
        CurrentUserService $currentUserService,
        FileUploader $fileUploader,
        ScheduleExpressionParser $scheduleExpressionParser,
        #[TaggedIterator('app.file_provider')]
        iterable $fileProviders,
        CommunityGroupManager $groupManager,
        #[Autowire('%app.default_post_language%')]
        int $defaultLanguage,
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
            'pinToInstance' => $request->request->getBoolean('pinToInstance'),
            'selectedLanguage' => Language::tryFrom($request->request->getInt('language')) ?? Language::tryFrom($defaultLanguage) ?? Language::Undetermined,
            'scheduler' => $request->request->all('scheduler'),
            'recurring' => $request->request->getBoolean('recurring'),
            'scheduleUnpin' => $request->request->getBoolean('scheduleUnpin'),
            'scheduleUnpinDateTime' => $request->request->get('scheduleUnpinDateTime'),
            'fileProviders' => [...$fileProviders],
            'defaultFileProvider' => $request->request->get('fileProvider'),
            'timezoneName' => $request->request->get('timezoneName'),
            'checkForDuplicates' => $request->request->getBoolean('checkForDuplicates'),
            'comments' => $request->request->all('comments'),
            'thumbnailUrl' => $request->request->get('thumbnailUrl'),
        ];
        $data['scheduleDateTimeObject'] = $data['scheduleDateTime'] ? new DateTimeImmutable($data['scheduleDateTime']) : null;
        if (isset($data['scheduler']['scheduleType'])) {
            $data['scheduler']['scheduleType'] = ScheduleType::from((int) $data['scheduler']['scheduleType']);
        }
        if (isset($data['scheduler']['selectedDayType'])) {
            $data['scheduler']['selectedDayType'] = DayType::from((int) $data['scheduler']['selectedDayType']);
        }
        if (isset($data['scheduler']['weekday'])) {
            $data['scheduler']['weekday'] = Weekday::from((int) $data['scheduler']['weekday']);
        }

        $image = $request->files->get('image');

        $errorResponse = fn () => $this->render('post/create.html.twig', [
            ...$data,
            'communities' => $this->getCommunities(),
            'languages' => Language::cases(),
            'groups' => [...$groupManager->getGroups()],
        ], new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY));

        if ($image && $data['url']) {
            $this->addFlash('error', $translator->trans('You cannot add both image and URL. Note that due to security concerns you have to select the file again.'));

            return $errorResponse();
        }
        if (!$data['title']) {
            $this->addFlash('error', $translator->trans('The post must have a title.'));

            return $errorResponse();
        }
        if (!$data['timezoneOffset']) {
            $this->addFlash('error', $translator->trans('Failed to get your timezone offset. Do you have javascript enabled?'));

            return $errorResponse();
        }
        if ($data['recurring']) {
            $data['scheduler']['timezone'] ??= 'UTC';
            if (!($data['scheduler']['expression'] ?? false)) {
                $this->addFlash('error', $translator->trans('The schedule recurring configuration is invalid.'));

                return $errorResponse();
            }
        } else {
            if (!$data['scheduleDateTime']) {
                $this->addFlash('error', $translator->trans('The schedule date and time must be set.'));

                return $errorResponse();
            }
        }
        if (($data['pinToInstance'] || $data['pinToCommunity']) && $data['scheduleUnpin'] && !$data['scheduleUnpinDateTime']) {
            $this->addFlash('error', $translator->trans("You selected scheduling of unpin, but you didn't specify a date and time for the unpin."));

            return $errorResponse();
        }

        foreach ($data['selectedCommunities'] as $key => $selectedCommunity) {
            if (str_starts_with($selectedCommunity, 'group***')) {
                $name = substr($selectedCommunity, strlen('group***'));
                $group = $groupManager->getGroup($name);
                if ($group === null) {
                    $this->addFlash('error', $translator->trans('Could not find group called "{group}"', ['{group}' => $group]));

                    return $errorResponse();
                }
                unset($data['selectedCommunities'][$key]);
                $groupCommunities = $group->communities;
                foreach ($groupCommunities as $groupCommunity) {
                    $data['selectedCommunities'][] = $groupCommunity->id;
                }
            }
        }

        $communities = $data['selectedCommunities'];
        $communities = array_map(static function (string|int $community) {
            if (is_int($community)) {
                return $community;
            }
            if (str_starts_with($community, '!')) {
                $community = substr($community, 1);
            }

            return $community;
        }, $communities);

        try {
            $communities = array_map(static fn (string|int $community) => $api->community()->get($community)->community, $communities);
        } catch (LemmyApiException) {
            $this->addFlash('error', $translator->trans("Couldn't find one or more of the communities, are you sure all of them exist?"));

            return $errorResponse();
        }

        $selectedLanguage = $data['selectedLanguage'];

        $site = $api->site()->getSite();
        $instanceLanguages = $site->discussionLanguages;
        $communityLanguages = array_map(
            static fn (Community $community) => array_map(
                static fn (Language $language) => $language->value,
                $api->community()->getLanguages($community),
            ),
            $communities,
        );
        if (count($communityLanguages)) {
            $communityLanguages = array_map(static fn (int $language) => Language::from($language), array_intersect(...$communityLanguages));
        }
        $userLanguages = $site->myUser?->discussionLanguages ?? null;

        if (count($instanceLanguages) && !in_array($selectedLanguage, $instanceLanguages, true)) {
            $this->addFlash('error', $translator->trans('The language you have selected is not supported by the target instance.'));

            return $errorResponse();
        }
        if (count($communityLanguages) && !in_array($selectedLanguage, $communityLanguages, true)) {
            $this->addFlash('error', $translator->trans('The language you have selected is not supported by one or more of the communities you have selected.'));

            return $errorResponse();
        }
        if ($userLanguages !== null && count($userLanguages) && !in_array($selectedLanguage, $userLanguages, true)) {
            $this->addFlash('error', $translator->trans('The language is not supported by your user.'));

            return $errorResponse();
        }

        $imageId = null;
        if ($image instanceof UploadedFile) {
            $imageId = $fileUploader->upload($image);
        }

        if ($data['recurring']) {
            $dateTime = $scheduleExpressionParser->getNextRunDate(
                expression: $data['scheduler']['expression'],
                timeZone: new DateTimeZone($data['scheduler']['timezone']),
            );
        } else {
            $dateTime = new DateTimeImmutable("{$data['scheduleDateTime']}:00{$data['timezoneOffset']}");
        }
        foreach ($communities as $community) {
            $jobManager->createJob(
                new CreatePostJob(
                    jwt: $user->getJwt(),
                    instance: $user->getInstance(),
                    community: $community,
                    title: $data['title'],
                    url: $data['url'] ?: null,
                    text: $data['text'] ?: null,
                    language: $data['selectedLanguage'],
                    nsfw: $data['nsfw'],
                    pinToCommunity: $data['pinToCommunity'],
                    pinToInstance: $data['pinToInstance'],
                    imageId: $imageId,
                    scheduleExpression: $data['recurring'] ? $data['scheduler']['expression'] : null,
                    scheduleTimezone: $data['recurring'] ? $data['scheduler']['timezone'] : null,
                    unpinAt: $data['scheduleUnpinDateTime'] ? new DateTimeImmutable("{$data['scheduleUnpinDateTime']}:00{$data['timezoneOffset']}") : null,
                    fileProvider: $data['defaultFileProvider'],
                    timezoneName: $data['timezoneName'],
                    checkForUrlDuplicates: $data['checkForDuplicates'],
                    comments: $data['comments'],
                    thumbnailUrl: $data['thumbnailUrl'] ?: null,
                ),
                $dateTime,
            );
        }
        if ($imageId !== null) {
            $jobManager->createJob(new DeleteFileJob($imageId), $dateTime->add(new DateInterval('PT5M')));
        }

        $this->addFlash('success', $translator->trans('Posts have been successfully scheduled.'));

        return $this->redirectToRoute('app.post.list');
    }

    #[Route('/create-unread-post-report', name: 'app.post.unread_post_report_create', methods: [Request::METHOD_GET])]
    public function createUnreadPostReport(bool $unreadPostsEnabled): Response
    {
        $user = $this->getUser();
        assert($user instanceof User);

        if (!$unreadPostsEnabled) {
            throw $this->createNotFoundException('Unread posts not enabled because a bot user is not configured');
        }

        return $this->render('post/create-report.html.twig', [
            'communities' => $this->getCommunities(),
            'selectedCommunities' => [],
            'recurring' => false,
            'scheduleDateTime' => '',
            'username' => '',
            'currentInstance' => $user->getInstance(),
            'currentUsername' => $user->getUsername(),
        ]);
    }

    #[Route('/create-unread-post-report/do', name: 'app.post.unread_post_report_create.do', methods: [Request::METHOD_POST])]
    public function doCreateUnreadPostReport(
        Request $request,
        LemmyApiFactory $apiFactory,
        TranslatorInterface $translator,
        JobManager $jobManager,
        CurrentUserService $currentUserService,
        ScheduleExpressionParser $scheduleExpressionParser,
        bool $unreadPostsEnabled,
    ) {
        if (!$unreadPostsEnabled) {
            throw $this->createNotFoundException('Unread posts not enabled because a bot user is not configured');
        }

        $api = $apiFactory->getForCurrentUser();
        $user = $currentUserService->getCurrentUser() ?? throw new LogicException('No user logged in');

        $data = [
            'selectedCommunities' => $request->request->all('communities'),
            'scheduleDateTime' => $request->request->get('scheduleDateTime'),
            'timezoneOffset' => $request->request->get('timezoneOffset'),
            'scheduler' => $request->request->all('scheduler'),
            'recurring' => $request->request->getBoolean('recurring'),
            'username' => $request->request->get('username'),
            'currentInstance' => $user->getInstance(),
            'currentUsername' => $user->getUsername(),
        ];
        $data['scheduleDateTimeObject'] = $data['scheduleDateTime'] ? new DateTimeImmutable($data['scheduleDateTime']) : null;
        if (isset($data['scheduler']['scheduleType'])) {
            $data['scheduler']['scheduleType'] = ScheduleType::from((int) $data['scheduler']['scheduleType']);
        }
        if (isset($data['scheduler']['selectedDayType'])) {
            $data['scheduler']['selectedDayType'] = DayType::from((int) $data['scheduler']['selectedDayType']);
        }
        if (isset($data['scheduler']['weekday'])) {
            $data['scheduler']['weekday'] = Weekday::from((int) $data['scheduler']['weekday']);
        }

        $errorResponse = fn () => $this->render('post/create-report.html.twig', [
            ...$data,
            'communities' => $this->getCommunities(),
        ], new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY));

        if (!$data['timezoneOffset']) {
            $this->addFlash('error', $translator->trans('Failed to get your timezone offset. Do you have javascript enabled?'));

            return $errorResponse();
        }
        if ($data['recurring']) {
            $data['scheduler']['timezone'] ??= 'UTC';
            if (!($data['scheduler']['expression'] ?? false)) {
                $this->addFlash('error', $translator->trans('The schedule recurring configuration is invalid.'));

                return $errorResponse();
            }
        } else {
            if (!$data['scheduleDateTime']) {
                $this->addFlash('error', $translator->trans('The schedule date and time must be set.'));

                return $errorResponse();
            }
        }
        $communities = $data['selectedCommunities'];
        $communities = array_map(static function (string $community) {
            if (str_starts_with($community, '!')) {
                $community = substr($community, 1);
            }

            return $community;
        }, $communities);

        try {
            $communities = array_map(static fn (string $community) => $api->community()->get($community)->community, $communities);
        } catch (LemmyApiException) {
            $this->addFlash('error', $translator->trans("Couldn't find one or more of the communities, are you sure all of them exist?"));

            return $errorResponse();
        }

        if (!count($communities) && !$data['username']) {
            $this->addFlash('error', $translator->trans('You must provide either a community or a username (or both).'));

            return $errorResponse();
        }

        $person = null;
        if ($username = $data['username']) {
            try {
                $person = $api->user()->get($username);
            } catch (LemmyApiException) {
                $this->addFlash('error', $translator->trans('Could not find the specified user, are you sure they exist?'));

                return $errorResponse();
            }
        }

        if ($data['recurring']) {
            $dateTime = $scheduleExpressionParser->getNextRunDate(
                expression: $data['scheduler']['expression'],
                timeZone: new DateTimeZone($data['scheduler']['timezone']),
            );
        } else {
            $dateTime = new DateTimeImmutable("{$data['scheduleDateTime']}:00{$data['timezoneOffset']}");
        }
        if (count($communities)) {
            foreach ($communities as $community) {
                $jobManager->createJob(
                    new ReportUnreadPostsJob(
                        jwt: $user->getJwt(),
                        instance: $user->getInstance(),
                        community: $community,
                        person: $person,
                        scheduleExpression: $data['recurring'] ? $data['scheduler']['expression'] : null,
                        scheduleTimezone: $data['recurring'] ? $data['scheduler']['timezone'] : null,
                    ),
                    $dateTime,
                );
            }
        } else {
            $jobManager->createJob(
                new ReportUnreadPostsJob(
                    jwt: $user->getJwt(),
                    instance: $user->getInstance(),
                    person: $person,
                    scheduleExpression: $data['recurring'] ? $data['scheduler']['expression'] : null,
                    scheduleTimezone: $data['recurring'] ? $data['scheduler']['timezone'] : null,
                ),
                $dateTime,
            );
        }

        $this->addFlash('success', $translator->trans('Post reports have been successfully scheduled.'));

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
                $this->addFlash('error', $translator->trans('Failed extracting ID from URL, please provide the ID manually.'));

                return $errorResponse();
            }
        }

        $pin = PinType::tryFrom($request->request->getInt('pin'));
        if ($pin === null) {
            $this->addFlash('error', $translator->trans('Invalid value for type of action.'));

            return $errorResponse();
        }

        $dateTime = new DateTimeImmutable("{$request->request->get('scheduleDateTime')}:00{$request->request->get('timezoneOffset')}");
        $jobManager->createJob(
            new PinUnpinPostJobV2(
                postId: (int) $urlOrId,
                jwt: $currentUserService->getCurrentUser()?->getJwt() ?? throw new LogicException('No user logged in'),
                instance: $currentUserService->getCurrentUser()?->getInstance() ?? throw new LogicException('No user logged in'),
                pin: $pin,
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

    #[Route('/ajax/title/expression', name: 'app.post.ajax.title_expression', methods: [Request::METHOD_POST])]
    public function getTitleExpressions(
        Request $request,
        TitleExpressionReplacer $expressionReplacer,
    ): JsonResponse {
        $body = json_decode($request->getContent(), true, flags: JSON_THROW_ON_ERROR);
        if (!isset($body['title']) || !isset($body['timezone'])) {
            throw new BadRequestHttpException('Missing the title or timezone parameter');
        }

        $timezone = $body['timezone'];
        $originalTimezone = date_default_timezone_get();
        date_default_timezone_set($timezone);
        $parserResult = $expressionReplacer->parse($body['title']);
        $title = $expressionReplacer->replace($body['title'], $parserResult);
        date_default_timezone_set($originalTimezone);

        return new JsonResponse([
            'validCount' => count($parserResult),
            'invalid' => $parserResult->invalidExpressions,
            'title' => $title,
        ]);
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

    #[Route('/ajax/new-comment-box', name: 'app.post.ajax.new_comment_box', methods: [Request::METHOD_POST])]
    public function getNewCommentBox(
        Request $request,
    ): Response {
        $json = json_decode($request->getContent(), true, flags: JSON_THROW_ON_ERROR);

        $name = $json['name'] ?? throw new BadRequestHttpException('Missing required parameters');
        $inputId = $json['inputId'] ?? throw new BadRequestHttpException('Missing required parameters');

        return $this->render('ajax/new-comment-box.html.twig', [
            'name' => $name,
            'inputId' => $inputId,
        ]);
    }

    #[Route('/ajax/page-title', name: 'app.post.ajax.page_title', methods: [Request::METHOD_POST])]
    public function getPageTitle(
        Request $request,
        HttpBrowser $browser,
    ): JsonResponse {
        $title = null;
        try {
            $json = json_decode($request->getContent(), true, flags: JSON_THROW_ON_ERROR);
            if (!isset($json['url'])) {
                throw new RuntimeException('URL is missing');
            }

            $page = $browser->request(Request::METHOD_GET, $json['url']);
            $ogTitleTag = $page->filter('meta[property="og:titlee"]');
            $titleTag = $page->filter('title');

            if ($ogTitleTag->count()) {
                $title = $ogTitleTag->first()->attr('content');
            } else if ($titleTag->count()) {
                $title = $titleTag->first()->text();
            }
        } catch (Exception $e) {
            error_log('Error getting title: ' . $e->getMessage());
        }

        return new JsonResponse([
            'title' => $title,
        ]);
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

    /**
     * @param array<string|int, string>                       $fields
     * @param array<string, callable(object $message): mixed> $callbacks
     */
    private function getJobArray(Envelope $job, array $fields = [], array $callbacks = []): array
    {
        $metadata = $job->last(MetadataStamp::class);
        assert($metadata !== null);

        $dateTime = $metadata->metadata['expiresAt'];
        assert($dateTime instanceof DateTimeInterface);
        $id = $metadata->metadata['jobId'];

        $result = [
            'jobId' => $id,
            'dateTime' => $dateTime->format('c'),
        ];

        if (!count($fields) && !count($callbacks)) {
            return $result;
        }

        $message = $job->getMessage();
        foreach ($fields as $sourceField => $targetField) {
            if (is_int($sourceField)) {
                $sourceField = $targetField;
            }
            $result[$sourceField] = $message->{$targetField};
        }

        foreach ($callbacks as $field => $callback) {
            $result[$field] = $callback($message);
        }

        return $result;
    }
}
