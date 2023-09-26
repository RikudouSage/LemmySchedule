<?php

namespace App\JobHandler;

use App\Authentication\User;
use App\Job\ReportUnreadPostsJob;
use App\Lemmy\LemmyApiFactory;
use App\Service\CurrentUserService;
use App\Service\JobManager;
use App\Service\ScheduleExpressionParser;
use DateTimeZone;
use Rikudou\LemmyApi\Enum\ListingType;
use Rikudou\LemmyApi\Enum\SortType;
use Rikudou\LemmyApi\LemmyApi;
use Rikudou\LemmyApi\Response\Model\Post;
use RuntimeException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ReportUnreadPostsJobHandler
{
    private LemmyApi $botApi;

    public function __construct(
        private LemmyApiFactory $apiFactory,
        private ScheduleExpressionParser $scheduleExpressionParser,
        private JobManager $jobManager,
        private CurrentUserService $currentUserService,
        string $botJwt,
        string $botInstance,
    ) {
        $this->botApi = $this->apiFactory->get(instance: $botInstance, jwt: $botJwt);
    }

    public function __invoke(ReportUnreadPostsJob $job): void
    {
        try {
            $api = $this->apiFactory->get(instance: $job->instance, jwt: $job->jwt);
            $targetUser = $api->site()->getSite()->myUser?->localUserView?->person;
            if ($targetUser === null) {
                throw new RuntimeException('The target user was not found.');
            }

            $unread = [...$this->getUnreadPosts($job)];
            if (!count($unread)) {
                return;
            }

            $communityInstance = parse_url($job->community->actorId, PHP_URL_HOST);
            $communityUrl = "https://{$job->instance}/c/{$job->community->name}@{$communityInstance}";
            $message = "Here is a list of unread posts from [!{$job->community->name}@{$communityInstance}]({$communityUrl}):\n";
            foreach ($unread as $post) {
                assert($post instanceof Post);
                $message .= "- [{$post->name}](https://{$job->instance}/post/{$post->id})\n";
            }

            $this->botApi->currentUser()->sendPrivateMessage(recipient: $targetUser, content: $message);
        } finally {
            if (isset($targetUser) && ($expression = $job->scheduleExpression)) {
                sleep(1);
                assert($job->scheduleTimezone !== null);

                $this->currentUserService->setCurrentUser(new User($targetUser->name, $job->instance, $job->jwt));

                $nextDate = $this->scheduleExpressionParser->getNextRunDate(
                    expression: $expression,
                    timeZone: new DateTimeZone($job->scheduleTimezone),
                );
                $this->jobManager->createJob($job, $nextDate);
            }
        }
    }

    /**
     * @return iterable<Post>
     */
    private function getUnreadPosts(ReportUnreadPostsJob $job): iterable
    {
        $api = $this->apiFactory->get(instance: $job->instance, jwt: $job->jwt);

        $maxPage = 10;
        $currentPage = 1;
        while ($currentPage < $maxPage) {
            $response = $api->post()->getPosts(
                community: $job->community,
                page: $currentPage,
                sort: SortType::New,
                listingType: ListingType::All,
            );
            foreach ($response as $post) {
                if ($post->read) {
                    continue;
                }

                yield $post->post;
            }

            ++$currentPage;
        }
    }
}
