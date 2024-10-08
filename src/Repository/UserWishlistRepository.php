<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Repository;

use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Sylius\Component\User\Model\UserInterface;

class UserWishlistRepository extends EntityRepository implements UserWishlistRepositoryInterface
{
    public function findByUser(UserInterface $user): array
    {
        return $this->findBy(['user' => $user]);
    }
}
