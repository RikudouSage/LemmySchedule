<?php

namespace App\Controller;

use App\Service\NewVersionCheck\NewVersionChecker;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class NewVersionCheckController extends AbstractController
{
    #[Route('/api/new-version-check', name: 'api.new_version_check', methods: [Request::METHOD_GET])]
    public function checkForNewVersion(
        #[Autowire('%app.current_version%')]
        string $currentVersion,
        #[Autowire('%app.version_check.enabled%')]
        bool $enabled,
        NewVersionChecker $newVersionChecker,
    ): JsonResponse {
        if (!$enabled) {
            return new JsonResponse([
                'latestVersion' => $currentVersion,
                'currentVersion' => $currentVersion,
                'hasNewVersion' => false,
                'info' => "New version checks disabled, fake returning the current version to pretend there's not a new one without actually checking.",
            ]);
        }

        return new JsonResponse([
            'latestVersion' => $newVersionChecker->getLatestVersion(),
            'currentVersion' => $currentVersion,
            'hasNewVersion' => $newVersionChecker->hasNewVersion(),
        ]);
    }
}
