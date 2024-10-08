<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Repository;

use Setono\SyliusWishlistPlugin\Model\GuestWishlistInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;

class GuestWishlistRepository extends EntityRepository implements GuestWishlistRepositoryInterface
{
    public function findOneByClientId(string $clientId): ?GuestWishlistInterface
    {
        return $this->findOneBy(['clientId' => $clientId]);
    }
}
