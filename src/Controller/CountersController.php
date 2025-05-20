<?php

namespace App\Controller;

use App\Entity\Counter;
use App\Repository\CounterRepository;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
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
    public function list(CounterRepository $countersRepository): Response
    {
        return $this->render('post/counter-list.html.twig', [
            'counters' => $countersRepository->findBy([
                'userId' => $this->getUser()?->getUserIdentifier() ?? throw new LogicException('No user logged in'),
            ]),
        ]);
    }

    #[Route('/add', name: 'app.counters.add', methods: [Request::METHOD_GET, Request::METHOD_POST])]
    #[Route('/edit/{id}', name: 'app.counters.edit', methods: [Request::METHOD_GET, Request::METHOD_POST])]
    public function edit(
        CounterRepository $countersRepository,
        Request $request,
        TranslatorInterface $translator,
        EntityManagerInterface $entityManager,
        ?int $id = null
    ): Response {
        $isNew = $id === null;
        $counter = $isNew ? null : $countersRepository->find($id);
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
            if ($counter && $counter->getName() !== $name) {
                $this->addFlash('error', $translator->trans('Cannot change the name of the counter.'));

                return $response();
            }
            if ($isNew && $countersRepository->findOneBy(['name' => $name])) {
                $this->addFlash('error', $translator->trans('Counter with the same name already exists.'));

                return $response();
            }
            if ($incrementBy === 0) {
                $this->addFlash('error', $translator->trans('Counter must be incremented by a negative or positive value, but it cannot be 0.'));

                return $response();
            }

            $counter ??= (new Counter())->setName($name);
            $counter
                ->setValue($value)
                ->setIncrementBy($incrementBy)
                ->setUserId($this->getUser()?->getUserIdentifier() ?? throw new LogicException('No user logged in'))
            ;
            $entityManager->persist($counter);
            $entityManager->flush();
            $this->addFlash('success', $isNew ? $translator->trans('Counter has been added.') : $translator->trans('Counter has been updated.'));

            return $this->redirectToRoute('app.counters.edit', [
                'id' => $counter->getId(),
            ]);
        }

        return $response(false);
    }

    #[Route('/delete/{id}', name: 'app.counters.delete', methods: [Request::METHOD_GET])]
    public function delete(int $id, CounterRepository $repository, EntityManagerInterface $entityManager): RedirectResponse
    {
        if ($entity = $repository->find($id)) {
            $entityManager->remove($entity);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app.counters.list');
    }
}
