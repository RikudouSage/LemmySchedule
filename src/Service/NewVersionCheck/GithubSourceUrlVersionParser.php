<?php

namespace App\Service\NewVersionCheck;

use App\Exception\VersionExtractionFailedException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class GithubSourceUrlVersionParser implements SourceUrlVersionParser
{
    public function __construct(
        private HttpClientInterface $httpClient,
    ) {
    }

    public function supports(string $url): bool
    {
        return str_starts_with($url, 'https://github.com');
    }

    public function getLatestVersion(string $url): string
    {
        $url .= '/releases/latest';
        $response = $this->httpClient->request(Request::METHOD_GET, $url, [
            'max_redirects' => 0,
        ]);
        $headers = $response->getHeaders(throw: false);
        foreach ($headers as $header => $values) {
            if (strtolower($header) === 'location') {
                $value = $values[array_key_first($values)];

                return $this->getVersionFromUrl($value);
            }
        }

        throw new VersionExtractionFailedException('Failed extracting version information from GitHub source.');
    }

    private function getVersionFromUrl(string $url): string
    {
        $parts = explode('/', $url);
        $version = $parts[array_key_last($parts)];
        if (str_starts_with($version, 'v')) {
            $version = substr($version, 1);
        }

        return $version;
    }
}
