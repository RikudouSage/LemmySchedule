<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/notice/ajax')]
final class AjaxNoticeController extends AbstractController
{
    #[Route('/error', name: 'app.notice.ajax.error', methods: [Request::METHOD_POST])]
    public function getError(Request $request, TranslatorInterface $translator): Response
    {
        $message = $request->request->get('text');
        $translated = $request->request->getBoolean('translated');
        if (!$translated) {
            $message = $translator->trans($message);
        }

        return $this->render('notification/error.html.twig', [
            'message' => $message,
        ]);
    }

    #[Route('/success', name: 'app.notice.ajax.success', methods: [Request::METHOD_POST])]
    public function getSuccess(Request $request, TranslatorInterface $translator): Response
    {
        $message = $request->request->get('text');
        $translated = $request->request->getBoolean('translated');
        if (!$translated) {
            $message = $translator->trans($message);
        }

        return $this->render('notification/success.html.twig', [
            'message' => $message,
        ]);
    }
}
