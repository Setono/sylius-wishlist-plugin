<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Repository;

use Setono\SyliusWishlistPlugin\Model\WishlistInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Webmozart\Assert\Assert;

/**
 * @implements WishlistRepositoryInterface<WishlistInterface>
 */
class WishlistRepository extends EntityRepository implements WishlistRepositoryInterface
{
    public function findOneByUuid(string $uuid): ?WishlistInterface
    {
        $obj = $this->findOneBy(['uuid' => $uuid]);
        Assert::nullOrIsInstanceOf($obj, WishlistInterface::class);

        return $obj;
    }
}
