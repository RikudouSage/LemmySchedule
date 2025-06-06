<?php

namespace App\Controller;

use App\Authentication\User;
use App\Entity\CreatePostStoredJob;
use App\Entity\PostPinUnpinStoredJob;
use App\Entity\UnreadPostReportStoredJob;
use App\Enum\DayType;
use App\Enum\PinType;
use App\Enum\ScheduleType;
use App\Enum\Weekday;
use App\FileProvider\FileProvider;
use App\FileUploader\FileUploader;
use App\Job\CreatePostJobV2;
use App\Job\DeleteFileJobV2;
use App\Job\PinUnpinPostJobV3;
use App\Job\ReportUnreadPostsJobV2;
use App\Lemmy\LemmyApiFactory;
use App\Repository\CommunityGroupRepository;
use App\Repository\CreatePostStoredJobRepository;
use App\Repository\PostPinUnpinStoredJobRepository;
use App\Repository\UnreadPostReportStoredJobRepository;
use App\Service\CurrentUserService;
use App\Service\JobScheduler;
use App\Service\ScheduleExpressionParser;
use App\Service\TitleExpressionReplacer;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
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
use Symfony\Component\Routing\Attribute\Route;
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
    ): Response {
        return $this->render('post/expressions_help.html.twig', [
            'expression' => $expressionReplacer,
        ]);
    }

    #[Route('/list', name: 'app.post.list', methods: [Request::METHOD_GET])]
    public function listPosts(
        bool $unreadPostsEnabled,
        CreatePostStoredJobRepository $createJobRepository,
        PostPinUnpinStoredJobRepository $pinJobRepository,
        UnreadPostReportStoredJobRepository $reportJobRepository,
    ): Response {
        if (!$this->getUser()) {
            throw $this->createNotFoundException('No user logged in');
        }

        $postCreateJobs = $createJobRepository->findBy([
            'userId' => $this->getUser()->getUserIdentifier(),
        ], ['scheduledAt' => 'DESC']);
        $postPinJobs = $pinJobRepository->findBy([
            'userId' => $this->getUser()->getUserIdentifier(),
        ], ['scheduledAt' => 'DESC']);
        $postReportJobs = $reportJobRepository->findBy([
            'userId' => $this->getUser()->getUserIdentifier(),
        ], ['scheduledAt' => 'DESC']);

        return $this->render('post/list.html.twig', [
            'postCreateJobs' => $postCreateJobs,
            'postPinJobs' => $postPinJobs,
            'postReportJobs' => $postReportJobs,
            'unreadPostsEnabled' => $unreadPostsEnabled,
        ]);
    }

    #[Route('/detail/{jobId}', name: 'app.post.detail', methods: [Request::METHOD_GET])]
    public function detail(
        int $jobId,
        ScheduleExpressionParser $scheduleExpressionParser,
        CreatePostStoredJobRepository $createJobRepository,
    ): Response {
        $job = $createJobRepository->find($jobId);
        if ($job === null) {
            throw $this->createNotFoundException('Job not found');
        }
        if ($job->getUserId() !== $this->getUser()->getUserIdentifier()) {
            throw $this->createAccessDeniedException('You do not have access to this job');
        }

        $nextRuns = null;
        if ($scheduleExpression = $job->getScheduleExpression()) {
            $nextRuns = [
                $scheduleExpressionParser->getNextRunDate(expression: $scheduleExpression, nth: 1, timeZone: new DateTimeZone($job->getScheduleTimezone())),
                $scheduleExpressionParser->getNextRunDate(expression: $scheduleExpression, nth: 2, timeZone: new DateTimeZone($job->getScheduleTimezone())),
                $scheduleExpressionParser->getNextRunDate(expression: $scheduleExpression, nth: 3, timeZone: new DateTimeZone($job->getScheduleTimezone())),
            ];
        }

        return $this->render('post/detail.html.twig', [
            'job' => $job,
            'nextRuns' => $nextRuns,
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
        #[Autowire('%app.default_communities%')]
        array $defaultCommunities,
        CommunityGroupRepository $groupRepository,
    ): Response {
        $fileProviders = [...$fileProviders];
        $default = (array_values(array_filter($fileProviders, static fn (FileProvider $fileProvider) => $fileProvider->isDefault()))[0] ?? null)?->getId();
        if ($default === null) {
            throw new LogicException('No default file provider specified');
        }

        $defaultCommunities = array_map(
            static fn (string $community) => str_starts_with($community, '!') ? $community : '!' . $community,
            $defaultCommunities,
        );

        return $this->render('post/create.html.twig', [
            'communities' => $this->getCommunities(),
            'selectedCommunities' => $defaultCommunities,
            'languages' => Language::cases(),
            'selectedLanguage' => Language::tryFrom($defaultLanguage) ?? Language::Undetermined,
            'fileProviders' => [...$fileProviders],
            'defaultFileProvider' => $default,
            'groups' => $groupRepository->findForCurrentUser(),
        ]);
    }

    #[Route('/create/do', name: 'app.post.create.do', methods: [Request::METHOD_POST])]
    public function doCreatePost(
        Request $request,
        LemmyApiFactory $apiFactory,
        TranslatorInterface $translator,
        CurrentUserService $currentUserService,
        FileUploader $fileUploader,
        ScheduleExpressionParser $scheduleExpressionParser,
        #[TaggedIterator('app.file_provider')]
        iterable $fileProviders,
        #[Autowire('%app.default_post_language%')]
        int $defaultLanguage,
        EntityManagerInterface $entityManager,
        JobScheduler $jobScheduler,
        CommunityGroupRepository $groupRepository,
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
            'groups' => $groupRepository->findForCurrentUser(),
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
                $group = $groupRepository->findByNameForCurrentUser($name);
                if ($group === null) {
                    $this->addFlash('error', $translator->trans('Could not find group called "{group}"', ['{group}' => $group]));

                    return $errorResponse();
                }
                unset($data['selectedCommunities'][$key]);
                foreach ($group->getCommunityIds() as $groupCommunityId) {
                    $data['selectedCommunities'][] = $groupCommunityId;
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

        $storedImage = null;
        if ($image instanceof UploadedFile) {
            $storedImage = $fileUploader->upload($image);
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
            $entity = (new CreatePostStoredJob())
                ->setJwt($user->getJwt())
                ->setInstance($user->getInstance())
                ->setCommunityId($community->id)
                ->setTitle($data['title'])
                ->setUrl($data['url'] ?: null)
                ->setText($data['text'] ?: null)
                ->setLanguage($data['selectedLanguage'])
                ->setNsfw($data['nsfw'])
                ->setPinToCommunity($data['pinToCommunity'])
                ->setPinToInstance($data['pinToInstance'])
                ->setImage($storedImage)
                ->setScheduleExpression($data['recurring'] ? $data['scheduler']['expression'] : null)
                ->setScheduleTimezone($data['recurring'] ? $data['scheduler']['timezone'] : null)
                ->setUnpinAt($data['scheduleUnpinDateTime'] ? new DateTimeImmutable("{$data['scheduleUnpinDateTime']}:00{$data['timezoneOffset']}") : null)
                ->setFileProviderId($data['defaultFileProvider'])
                ->setTimezoneName($data['timezoneName'])
                ->setCheckForUrlDuplicates($data['checkForDuplicates'])
                ->setComments($data['comments'])
                ->setThumbnailUrl($data['thumbnailUrl'] ?: null)
                ->setUserId($this->getUser()->getUserIdentifier())
                ->setScheduledAt($dateTime)
            ;
            $entityManager->persist($entity);
            $entityManager->flush();

            $jobScheduler->schedule(
                new CreatePostJobV2($entity->getId()),
                $dateTime,
            );
        }
        if ($storedImage !== null) {
            $jobScheduler->schedule(
                new DeleteFileJobV2($storedImage->getId()),
                $dateTime->add(new DateInterval('PT5M')),
            );
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
        JobScheduler $jobScheduler,
        EntityManagerInterface $entityManager,
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
            'timezoneName' => $request->request->get('timezoneName'),
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
            $entitiesToSchedule = [];
            foreach ($communities as $community) {
                $entity = (new UnreadPostReportStoredJob())
                    ->setJwt($user->getJwt())
                    ->setInstance($user->getInstance())
                    ->setCommunityId($community->id)
                    ->setPersonId($person?->id)
                    ->setScheduleExpression($data['recurring'] ? $data['scheduler']['expression'] : null)
                    ->setScheduleTimezone($data['recurring'] ? $data['scheduler']['timezone'] : null)
                    ->setUserId($user->getUserIdentifier())
                    ->setScheduledAt($dateTime)
                    ->setTimezoneName($data['timezoneName'])
                ;
                $entityManager->persist($entity);
                $entitiesToSchedule[] = $entity;
            }
            $entityManager->flush();
            foreach ($entitiesToSchedule as $entity) {
                $jobScheduler->schedule(new ReportUnreadPostsJobV2($entity->getId()), $dateTime);
            }
        } else {
            $entity = (new UnreadPostReportStoredJob())
                ->setJwt($user->getJwt())
                ->setInstance($user->getInstance())
                ->setPersonId($person?->id)
                ->setScheduleExpression($data['recurring'] ? $data['scheduler']['expression'] : null)
                ->setScheduleTimezone($data['recurring'] ? $data['scheduler']['timezone'] : null)
                ->setUserId($user->getUserIdentifier())
                ->setScheduledAt($dateTime)
            ;
            $entityManager->persist($entity);
            $entityManager->flush();
            $jobScheduler->schedule(new ReportUnreadPostsJobV2($entity->getId()), $dateTime);
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
        JobScheduler $jobScheduler,
        CurrentUserService $currentUserService,
        EntityManagerInterface $entityManager,
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
        $entity = (new PostPinUnpinStoredJob())
            ->setPostId((int) $urlOrId)
            ->setJwt($currentUserService->getCurrentUser()?->getJwt() ?? throw new LogicException('No user logged in'))
            ->setInstance($currentUserService->getCurrentUser()?->getInstance() ?? throw new LogicException('No user logged in'))
            ->setUserId($currentUserService->getCurrentUser()?->getUserIdentifier() ?? throw new LogicException('No user logged in'))
            ->setPinType($pin)
            ->setScheduledAt($dateTime)
            ->setTimezoneName($request->request->get('timezoneName'))
        ;
        $entityManager->persist($entity);
        $entityManager->flush();

        $jobScheduler->schedule(
            new PinUnpinPostJobV3($entity->getId()),
            $dateTime,
        );
        $this->addFlash('success', $translator->trans('Post pin/unpin was successfully scheduled.'));

        return $this->redirectToRoute('app.post.list');
    }

    #[Route('/ajax/cancel/{type}/{jobId}', name: 'app.post.ajax.cancel', methods: [Request::METHOD_DELETE])]
    public function cancelJob(int $jobId, string $type, EntityManagerInterface $entityManager): JsonResponse
    {
        $repository = $entityManager->getRepository($type);
        $entity = $repository->find($jobId);

        if ($entity->getUserId() !== $this->getUser()->getUserIdentifier()) {
            throw $this->createAccessDeniedException('You do not have access to this job');
        }

        if ($entity) {
            $entityManager->remove($entity);
            $entityManager->flush();
        }

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
            } elseif ($titleTag->count()) {
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
}
