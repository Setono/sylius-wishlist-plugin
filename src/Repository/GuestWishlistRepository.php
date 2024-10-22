<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Repository;

use Setono\SyliusWishlistPlugin\Model\GuestWishlistInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Webmozart\Assert\Assert;

class GuestWishlistRepository extends EntityRepository implements GuestWishlistRepositoryInterface
{
    public function findOneByClientId(string $clientId): ?GuestWishlistInterface
    {
        $obj = $this->findOneBy(['clientId' => $clientId]);
        Assert::nullOrIsInstanceOf($obj, GuestWishlistInterface::class);

        return $obj;
    }
}
