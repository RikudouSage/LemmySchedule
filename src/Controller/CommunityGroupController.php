<?php

namespace App\Controller;

use App\Entity\CommunityGroup;
use App\Lemmy\LemmyApiFactory;
use App\Repository\CommunityGroupRepository;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Rikudou\LemmyApi\Exception\LemmyApiException;
use Rikudou\LemmyApi\Response\Model\Community;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Unleash\Client\Bundle\Attribute\IsEnabled;

#[IsEnabled('community_groups')]
#[Route('/community-groups')]
final class CommunityGroupController extends AbstractController
{
    #[Route('', name: 'app.community_groups.list', methods: [Request::METHOD_GET])]
    public function listGroups(
        CommunityGroupRepository $groupRepository,
    ): Response {
        return $this->render('community_groups/list.html.twig', [
            'groups' => [...$groupRepository->findBy([
                'userId' => $this->getUser()?->getUserIdentifier() ?? throw new LogicException('No user logged in'),
            ])],
        ]);
    }

    #[Route('/add', name: 'app.community_groups.add', methods: [Request::METHOD_GET])]
    public function addGroup(): Response
    {
        return $this->render('community_groups/add.html.twig');
    }

    #[Route('/add/do', name: 'app.community_groups.add.do', methods: [Request::METHOD_POST])]
    public function doAddGroup(
        Request $request,
        TranslatorInterface $translator,
        LemmyApiFactory $apiFactory,
        EntityManagerInterface $entityManager,
    ) {
        $post = $request->request;

        $data = [
            'title' => $post->get('title'),
            'selectedCommunities' => $post->all('communities'),
        ];

        $errorResponse = fn () => $this->render(
            'community_groups/add.html.twig',
            $data,
            new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY),
        );

        if (!$data['title']) {
            $this->addFlash('error', $translator->trans('Title is required'));

            return $errorResponse();
        }

        if (!count($data['selectedCommunities'])) {
            $this->addFlash('error', $translator->trans('At least one community is required'));

            return $errorResponse();
        }

        $api = $apiFactory->getForCurrentUser();
        $communities = $data['selectedCommunities'];
        $communities = array_map(static function (string $community) {
            if (str_starts_with($community, '!')) {
                $community = substr($community, 1);
            }

            return $community;
        }, $communities);

        try {
            $communities = array_map(static fn (string $community) => $api->community()->get($community)->community, $communities);
        } catch (LemmyApiException) {
            $this->addFlash('error', $translator->trans("Couldn't find one or more of the communities, are you sure all of them exist?"));

            return $errorResponse();
        }

        $entity = (new CommunityGroup())
            ->setName($data['title'])
            ->setCommunityIds(array_map(
                static fn (Community $community) => $community->id,
                $communities,
            ))
            ->setUserId($this->getUser()?->getUserIdentifier() ?? throw new LogicException('No user logged in'))
        ;
        $entityManager->persist($entity);
        $entityManager->flush();
        $this->addFlash('success', $translator->trans('Your group was successfully created.'));

        return $this->redirectToRoute('app.community_groups.list');
    }

    #[Route('/delete/{id}', name: 'app.community_groups.delete', methods: [Request::METHOD_GET])]
    public function deleteGroup(
        int $id,
        TranslatorInterface $translator,
        EntityManagerInterface $entityManager,
        CommunityGroupRepository $groupRepository,
    ): Response {
        $entity = $groupRepository->find($id);
        if (!$entity) {
            $this->addFlash('info', $translator->trans('Group not found'));
        } else {
            $entityManager->remove($entity);
            $entityManager->flush();
            $this->addFlash('success', $translator->trans('Group successfully deleted'));
        }

        return $this->redirectToRoute('app.community_groups.list');
    }
}
