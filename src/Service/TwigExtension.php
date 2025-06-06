<?php

namespace App\Service;

use App\Dto\Time;
use App\Enum\Feature;
use App\Lemmy\LemmyApiFactory;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use IntlDateFormatter;
use LogicException;
use ReflectionEnum;
use Rikudou\LemmyApi\LemmyApi;
use Rikudou\LemmyApi\Response\Model\Community;
use Rikudou\LemmyApi\Response\Model\Person;
use Rikudou\LemmyApi\Response\Model\Post;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Twig\TwigTest;

final class TwigExtension extends AbstractExtension
{
    public function __construct(
        private readonly SupportedFeaturesManager $featuresManager,
        private readonly LemmyApiFactory $apiFactory,
        private readonly CurrentUserService $currentUserService,
        #[Autowire('%app.default_instance%')]
        private readonly string $defaultInstance,
    ) {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('format_date_time', $this->formatDate(...)),
            new TwigFilter('timezone_offset', $this->getTimezoneOffset(...)),
            new TwigFilter('community_name', $this->getCommunityName(...)),
            new TwigFilter('person_name', $this->getPersonName(...)),
            new TwigFilter('class_name', $this->getClassName(...)),
            new TwigFilter('post_url', $this->getPostUrl(...)),
            new TwigFilter('person_url', $this->getPersonUrl(...)),
            new TwigFilter('community_url', $this->getCommunityUrl(...)),
        ];
    }

    public function getTests(): array
    {
        return [
            new TwigTest('supported', $this->isFeatureSupported(...)),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('enum', $this->getEnum(...)),
            new TwigFunction('community', $this->getCommunity(...)),
            new TwigFunction('post', $this->getPost(...)),
            new TwigFunction('person', $this->getPerson(...)),
        ];
    }

    private function formatDate(string|DateTimeInterface $dateTime, string $locale = 'en-US'): string
    {
        if (is_string($dateTime)) {
            $dateTime = new DateTimeImmutable($dateTime);
        }

        $formatter = new IntlDateFormatter(
            locale: $locale,
            dateType: IntlDateFormatter::FULL,
            timeType: IntlDateFormatter::SHORT,
        );
        $formatter->setTimeZone($dateTime->getTimezone());

        return $formatter->format($dateTime) ?: throw new RuntimeException("Failed formatting datetime: {$dateTime->format('c')}");
    }

    private function getTimezoneOffset(DateTimeZone $timeZone): Time
    {
        $offset = $timeZone->getOffset(new DateTimeImmutable(timezone: $timeZone));
        $hours = floor($offset / 60 / 60);
        $remainder = $offset - $hours * 60 * 60;
        $minutes = ceil($remainder / 60);

        return new Time($hours, $minutes);
    }

    private function isFeatureSupported(Feature $feature): bool
    {
        return $this->featuresManager->supports($feature);
    }

    private function getEnum(string $class): object
    {
        if (!enum_exists($class)) {
            throw new LogicException("The enum '{$class}' does not exist");
        }

        return new readonly class ($class) {
            public function __construct(
                private string $class,
            ) {
            }

            public function __call(string $name, array $arguments)
            {
                return (new ReflectionEnum($this->class))->getCase($name)->getValue();
            }
        };
    }

    private function getCommunity(int $id): Community
    {
        return $this->getApi()->community()->get($id)->community;
    }

    private function getPost(int $id): Post
    {
        return $this->getApi()->post()->get($id)->post;
    }

    private function getPerson(int $id): Person
    {
        return $this->getApi()->user()->get($id);
    }

    private function getCommunityName(Community $community): string
    {
        $host = parse_url($community->actorId, PHP_URL_HOST);

        return "!{$community->name}@{$host}";
    }

    private function getPersonName(Person $person): string
    {
        $host = parse_url($person->actorId, PHP_URL_HOST);

        return "@{$person->name}@{$host}";
    }

    private function getClassName(object $object): string
    {
        return $object::class;
    }

    private function getPostUrl(Post $post): string
    {
        $instance = $this->currentUserService->getCurrentUser()?->getInstance() ?? $this->defaultInstance;

        return "https://{$instance}/post/{$post->id}";
    }

    private function getPersonUrl(Person $person): string
    {
        $instance = $this->currentUserService->getCurrentUser()?->getInstance() ?? $this->defaultInstance;
        $personInstance = parse_url($person->actorId, PHP_URL_HOST);

        $result = "https://{$instance}/u/{$person->name}";
        if ($personInstance !== $instance) {
            $result .= "@{$personInstance}";
        }

        return $result;
    }

    private function getCommunityUrl(Community $community): string
    {
        $instance = $this->currentUserService->getCurrentUser()?->getInstance() ?? $this->defaultInstance;
        $communityInstance = parse_url($community->actorId, PHP_URL_HOST);

        $result = "https://{$instance}/c/{$community->name}";
        if ($communityInstance !== $instance) {
            $result .= "@{$communityInstance}";
        }

        return $result;
    }

    private function getApi(): LemmyApi
    {
        return $this->currentUserService->getCurrentUser()
            ? $this->apiFactory->getForCurrentUser()
            : $this->apiFactory->get($this->defaultInstance)
        ;
    }
}
