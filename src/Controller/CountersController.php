<?php

namespace App\Controller;

use App\Dto\CounterConfiguration;
use App\Service\CountersRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/counters')]
final class CountersController extends AbstractController
{
    #[Route('/list', name: 'app.counters.list', methods: [Request::METHOD_GET])]
    public function list(CountersRepository $countersRepository): Response
    {
        return $this->render('post/counter-list.html.twig', [
            'counters' => $countersRepository->getCounters(),
        ]);
    }

    #[Route('/add', name: 'app.counters.add', methods: [Request::METHOD_GET, Request::METHOD_POST])]
    #[Route('/edit/{name}', name: 'app.counters.edit', methods: [Request::METHOD_GET, Request::METHOD_POST])]
    public function edit(
        CountersRepository $countersRepository,
        Request $request,
        TranslatorInterface $translator,
        ?string $name = null
    ): Response {
        $isNew = $name === null;
        $counter = $isNew ? null : $countersRepository->findByName($name);
        if (!$isNew && $counter === null) {
            throw $this->createNotFoundException();
        }

        $response = function (bool $error = true) use ($isNew, $counter) {
            $response = $this->render('post/counter-edit.html.twig', [
                'isNew' => $isNew,
                'counter' => $counter,
            ]);
            $response->setStatusCode($error ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK);

            return $response;
        };

        if ($request->isMethod(Request::METHOD_POST)) {
            $name = $request->request->getString('name');
            $value = $request->request->getInt('value');
            $incrementBy = $request->request->getInt('incrementBy');

            if (!trim($name)) {
                $this->addFlash('error', $translator->trans('Name cannot be empty.'));
                return $response();
            }
            if ($counter && $counter->name !== $name) {
                $this->addFlash('error', $translator->trans('Cannot change the name of the counter.'));
                return $response();
            }
            if ($isNew && $countersRepository->findByName($name)) {
                $this->addFlash('error', $translator->trans('Counter with the same name already exists.'));
                return $response();
            }
            if ($incrementBy === 0) {
                $this->addFlash('error', $translator->trans('Counter must be incremented by a negative or positive value, but it cannot be 0.'));
                return $response();
            }

            $counter = new CounterConfiguration(
                name: $name,
                value: $value,
                incrementBy: $incrementBy,
            );

            $countersRepository->store($counter);
            $this->addFlash('success', $isNew ? $translator->trans('Counter has been added.') : $translator->trans('Counter has been updated.'));

            return $this->redirectToRoute('app.counters.edit', [
                'name' => $name,
            ]);
        }

        return $response(false);
    }

    #[Route('/delete/{name}', name: 'app.counters.delete', methods: [Request::METHOD_GET])]
    public function delete(string $name, CountersRepository $repository): RedirectResponse
    {
        $repository->delete($name);
        return $this->redirectToRoute('app.counters.list');
    }
}
