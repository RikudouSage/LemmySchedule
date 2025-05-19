<?php

namespace App\Lemmy;

use App\Authentication\User;
use App\Service\CurrentUserService;
use LogicException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Rikudou\LemmyApi\DefaultLemmyApi;
use Rikudou\LemmyApi\Enum\LemmyApiVersion;
use Rikudou\LemmyApi\LemmyApi;

final readonly class LemmyApiFactory
{
    public function __construct(
        private ClientInterface $httpClient,
        private RequestFactoryInterface $requestFactory,
        private CurrentUserService $currentUserService,
    ) {
    }

    public function get(string $instance, ?string $username = null, ?string $password = null, ?string $jwt = null, ?string $totpToken = null): LemmyApi
    {
        $api = new DefaultLemmyApi(
            instanceUrl: "https://{$instance}",
            version: LemmyApiVersion::Version3,
            httpClient: $this->httpClient,
            requestFactory: $this->requestFactory,
            strictDeserialization: false,
        );

        if ($username && $password) {
            $api->login($username, $password, $totpToken);
        } elseif ($jwt) {
            $api->setJwt($jwt);
        }

        return $api;
    }

    public function getForUser(User $user): LemmyApi
    {
        return $this->get($user->getInstance(), $user->getUsername(), jwt: $user->getJwt());
    }

    public function getForCurrentUser(): LemmyApi
    {
        return $this->getForUser($this->currentUserService->getCurrentUser() ?? throw new LogicException('No user logged in'));
    }
}
