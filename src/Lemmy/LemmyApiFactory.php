<?php

namespace App\Lemmy;

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

    public function get(string $instance, ?string $username = null, ?string $password = null, ?string $jwt = null): LemmyApi
    {
        $api = new DefaultLemmyApi(
            instanceUrl: "https://{$instance}",
            version: LemmyApiVersion::Version3,
            httpClient: $this->httpClient,
            requestFactory: $this->requestFactory,
        );

        if ($username && $password) {
            $api->login($username, $password);
        } elseif ($jwt) {
            $api->setJwt($jwt);
        } else {
            throw new LogicException('Either username/password or jwt must be provided.');
        }

        return $api;
    }

    public function getForCurrentUser(): LemmyApi
    {
        $user = $this->currentUserService->getCurrentUser() ?? throw new LogicException('No user logged in');

        return $this->get($user->getInstance(), $user->getUsername(), jwt: $user->getJwt());
    }
}
