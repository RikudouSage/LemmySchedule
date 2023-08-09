<?php

namespace App\Controller;

use App\InstanceList\InstanceListProviderCollection;
use LogicException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

#[Route('/auth')]
final class AuthenticationController extends AbstractController
{
    #[Route(path: '/login', name: 'auth.login')]
    public function login(
        AuthenticationUtils $authenticationUtils,
        Request $request,
        InstanceListProviderCollection $instanceListProvider,
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app.home');
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();
        $lastInstance = $request->getSession()->get('last_instance', 'lemmings.world');

        return $this->render('authentication/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
            'last_instance' => $lastInstance,
            'instances' => $instanceListProvider->getInstances(),
        ]);
    }

    #[Route(path: '/logout', name: 'auth.logout')]
    public function logout(): void
    {
        throw new LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}
