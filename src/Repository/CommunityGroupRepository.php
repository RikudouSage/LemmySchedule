<?php

namespace App\Repository;

use App\Entity\CommunityGroup;
use App\Service\CurrentUserService;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CommunityGroup>
 */
class CommunityGroupRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly CurrentUserService $currentUserService,
    ) {
        parent::__construct($registry, CommunityGroup::class);
    }

    /**
     * @return array<CommunityGroup>
     */
    public function findForCurrentUser(): array
    {
        $user = $this->currentUserService->getCurrentUser();
        if ($user === null) {
            return [];
        }

        return $this->findBy([
            'userId' => $user->getUserIdentifier(),
        ], [
            'name' => 'ASC',
        ]);
    }

    public function findByNameForCurrentUser(string $name): ?CommunityGroup
    {
        $user = $this->currentUserService->getCurrentUser();
        if ($user === null) {
            return null;
        }

        return $this->findOneBy([
            'name' => $name,
            'userId' => $user->getUserIdentifier(),
        ]);
    }
}
