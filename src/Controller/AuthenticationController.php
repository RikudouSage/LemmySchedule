<?php

namespace App\Controller;

use App\Exception\InvalidTotpTokenException;
use App\Exception\ProvideTotpException;
use App\InstanceList\InstanceListProviderCollection;
use LogicException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

#[Route('/auth')]
final class AuthenticationController extends AbstractController
{
    public function __construct(
        private readonly string $defaultInstance,
    ) {
    }

    #[Route(path: '/login', name: 'auth.login')]
    public function login(
        AuthenticationUtils $authenticationUtils,
        Request $request,
        InstanceListProviderCollection $instanceListProvider,
        bool $singleInstanceMode,
        string $defaultInstance,
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app.home');
        }

        $error = $authenticationUtils->getLastAuthenticationError();

        $lastUsername = $authenticationUtils->getLastUsername();
        $lastInstance = $request->getSession()->get('last_instance', $this->defaultInstance);
        $lastPassword = $request->getSession()->get('last_password');
        if ($lastPassword) {
            $request->getSession()->remove('last_password');
        }

        $showTotp = false;
        if ($error instanceof ProvideTotpException) {
            $error = null;
            $showTotp = true;
        }
        if ($error instanceof InvalidTotpTokenException) {
            $showTotp = true;
        }

        return $this->render('authentication/login.html.twig', [
            'last_username' => $lastUsername,
            'last_password' => $lastPassword,
            'error' => $error,
            'last_instance' => $lastInstance,
            'show_totp' => $showTotp,
            'instances' => $instanceListProvider->getInstances(),
            'default_instance' => $defaultInstance,
            'single_instance_mode' => $singleInstanceMode,
        ]);
    }

    #[Route(path: '/logout', name: 'auth.logout')]
    public function logout(): void
    {
        throw new LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}
