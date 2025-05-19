<?php

namespace App\JobHandler;

use App\Authentication\User;
use App\Entity\UnreadPostReportStoredJob;
use App\Job\ReportUnreadPostsJobV2;
use App\Lemmy\LemmyApiFactory;
use App\Repository\UnreadPostReportStoredJobRepository;
use App\Service\CurrentUserService;
use App\Service\JobScheduler;
use App\Service\ScheduleExpressionParser;
use DateTimeZone;
use Rikudou\LemmyApi\Enum\ListingType;
use Rikudou\LemmyApi\Enum\SortType;
use Rikudou\LemmyApi\LemmyApi;
use Rikudou\LemmyApi\Response\Model\Post;
use RuntimeException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ReportUnreadPostsJobV2Handler
{
    private LemmyApi $botApi;

    public function __construct(
        private LemmyApiFactory                     $apiFactory,
        private ScheduleExpressionParser            $scheduleExpressionParser,
        private CurrentUserService                  $currentUserService,
        private UnreadPostReportStoredJobRepository $jobRepository,
        string                                      $botJwt,
        string                                      $botInstance,
        private JobScheduler $jobScheduler,
    ) {
        $this->botApi = $this->apiFactory->get(instance: $botInstance, jwt: $botJwt);
    }

    public function __invoke(ReportUnreadPostsJobV2 $message): void
    {
        $job = $this->jobRepository->find($message->jobId);
        if ($job === null) {
            return;
        }

        try {
            $api = $this->apiFactory->get(instance: $job->getInstance(), jwt: $job->getJwt());
            $recipient = $api->site()->getSite()->myUser?->localUserView?->person;
            if ($recipient === null) {
                throw new RuntimeException('The target user was not found.');
            }

            $unread = [...$this->getUnreadPosts($job)];
            if (!count($unread)) {
                return;
            }

            if (!$job->getPersonId() && !$job->getCommunityId()) {
                return;
            }

            $message = 'Here is a list of unread posts from ';
            if ($job->getPersonId()) {
                $person = $api->user()->get($job->getPersonId());
                $personInstance = parse_url($person->actorId, PHP_URL_HOST);
                $personUrl = "https://{$job->getInstance()}/u/{$person->name}@{$personInstance}";

                $message .= "[@{$person->name}@{$personInstance}]({$personUrl})";
            }
            if ($job->getPersonId() && $job->getCommunityId()) {
                $message .= ' in ';
            }
            if ($job->getCommunityId()) {
                $community = $api->community()->get($job->getCommunityId())->community;

                $communityInstance = parse_url($community->actorId, PHP_URL_HOST);
                $communityUrl = "https://{$job->getInstance()}/c/{$community->name}@{$communityInstance}";
                $message .= "[!{$community->name}@{$communityInstance}]({$communityUrl})";
            }
            $message .= ":\n";
            foreach ($unread as $post) {
                assert($post instanceof Post);
                $message .= "- [{$post->name}](https://{$job->getInstance()}/post/{$post->id})\n";
            }

            $this->botApi->currentUser()->sendPrivateMessage(recipient: $recipient, content: $message);
        } finally {
            if (isset($recipient) && ($expression = $job->getScheduleExpression())) {
                sleep(1);
                assert($job->getScheduleTimezone() !== null);

                $this->currentUserService->setCurrentUser(new User($recipient->name, $job->getInstance(), $job->getJwt()));

                $nextDate = $this->scheduleExpressionParser->getNextRunDate(
                    expression: $expression,
                    timeZone: new DateTimeZone($job->getScheduleTimezone()),
                );
                $this->jobScheduler->schedule($message, $nextDate);;
            }
        }
    }

    /**
     * @return iterable<Post>
     */
    private function getUnreadPosts(UnreadPostReportStoredJob $job): iterable
    {
        $api = $this->apiFactory->get(instance: $job->getInstance(), jwt: $job->getJwt());

        $maxPage = 10;
        $currentPage = 1;
        if ($job->getCommunityId() && !$job->getPersonId()) {
            while ($currentPage < $maxPage) {
                $response = $api->post()->getPosts(
                    community: $job->getCommunityId(),
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
        } elseif ($job->getPersonId()) {
            while ($currentPage < $maxPage) {
                $response = $api->user()->getPosts(
                    user: $job->getPersonId(),
                    community: $job->getCommunityId(),
                    page: $currentPage,
                    sort: SortType::New,
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
}
