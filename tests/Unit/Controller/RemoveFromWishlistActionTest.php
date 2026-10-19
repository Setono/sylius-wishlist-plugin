<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Tests\Unit\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusWishlistPlugin\Controller\RemoveFromWishlistAction;
use Setono\SyliusWishlistPlugin\Model\UserWishlist;
use Setono\SyliusWishlistPlugin\Model\WishlistInterface;
use Setono\SyliusWishlistPlugin\Model\WishlistItem;
use Setono\SyliusWishlistPlugin\Provider\WishlistProviderInterface;
use Sylius\Component\Core\Model\Product;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariant;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class RemoveFromWishlistActionTest extends TestCase
{
    use ProphecyTrait;

    #[Test]
    public function it_throws_not_found_when_the_product_does_not_exist(): void
    {
        $manager = $this->manager(Product::class, null);
        $manager->flush()->shouldNotBeCalled();

        $this->expectException(NotFoundHttpException::class);

        $this->action(Product::class, $manager, [])(new Request(), 1);
    }

    #[Test]
    public function it_removes_the_product_from_every_wishlist_and_returns_the_add_url(): void
    {
        $product = $this->product(1);
        $other = $this->product(2);

        $first = $this->wishlist($this->item($product, 2), $this->item($other, 3));
        $second = $this->wishlist($this->item($product, 1));

        $manager = $this->manager(Product::class, $product);
        $manager->flush()->shouldBeCalledOnce();

        $urlGenerator = $this->prophesize(UrlGeneratorInterface::class);
        $urlGenerator->generate('setono_sylius_wishlist_shop_wishlist_add_product', ['id' => 1])->willReturn('/add/1');

        $response = $this->action(Product::class, $manager, [$first, $second], $urlGenerator->reveal())(new Request(), 1);

        self::assertFalse($first->hasProduct($product));
        self::assertTrue($first->hasProduct($other));
        self::assertFalse($second->hasItems());
        self::assertSame(
            // The count is what is left on the wishlists: the other product's quantity
            ['event' => 'removed', 'toggleUrl' => '/add/1', 'wishlistItemsCount' => 3],
            json_decode((string) $response->getContent(), true),
        );
    }

    #[Test]
    public function it_removes_the_variant_from_every_wishlist_and_returns_the_add_url(): void
    {
        $product = $this->product(1);
        $variant = $this->variant(10, $product);
        $otherVariant = $this->variant(11, $product);

        $wishlist = $this->wishlist($this->variantItem($variant, 1), $this->variantItem($otherVariant, 4));

        $manager = $this->manager(ProductVariant::class, $variant);
        $manager->flush()->shouldBeCalledOnce();

        $urlGenerator = $this->prophesize(UrlGeneratorInterface::class);
        $urlGenerator->generate('setono_sylius_wishlist_shop_wishlist_add_product_variant', ['id' => 10])->willReturn('/add-variant/10');

        $response = $this->action(ProductVariant::class, $manager, [$wishlist], $urlGenerator->reveal())(new Request(), 10);

        self::assertFalse($wishlist->hasProductVariant($variant));
        self::assertTrue($wishlist->hasProductVariant($otherVariant));
        self::assertSame(
            ['event' => 'removed', 'toggleUrl' => '/add-variant/10', 'wishlistItemsCount' => 4],
            json_decode((string) $response->getContent(), true),
        );
    }

    /**
     * @param class-string<ProductInterface|ProductVariantInterface> $className
     * @param ObjectProphecy<EntityManagerInterface> $manager
     * @param list<WishlistInterface> $wishlists
     */
    private function action(
        string $className,
        ObjectProphecy $manager,
        array $wishlists,
        ?UrlGeneratorInterface $urlGenerator = null,
    ): RemoveFromWishlistAction {
        $managerRegistry = $this->prophesize(ManagerRegistry::class);
        $managerRegistry->getManagerForClass(Argument::type('string'))->willReturn($manager->reveal());

        $wishlistProvider = $this->prophesize(WishlistProviderInterface::class);
        $wishlistProvider->getWishlists()->willReturn($wishlists);

        return new RemoveFromWishlistAction(
            $wishlistProvider->reveal(),
            $managerRegistry->reveal(),
            $urlGenerator ?? $this->prophesize(UrlGeneratorInterface::class)->reveal(),
            $className,
        );
    }

    /**
     * @param class-string $className
     *
     * @return ObjectProphecy<EntityManagerInterface>
     */
    private function manager(string $className, ?object $entity): ObjectProphecy
    {
        $manager = $this->prophesize(EntityManagerInterface::class);
        $manager->find($className, Argument::type('int'))->willReturn($entity);

        return $manager;
    }

    private function wishlist(WishlistItem ...$items): UserWishlist
    {
        $wishlist = new UserWishlist();
        foreach ($items as $item) {
            $wishlist->addItem($item);
        }

        return $wishlist;
    }

    private function item(ProductInterface $product, int $quantity): WishlistItem
    {
        $item = new WishlistItem();
        $item->setProduct($product);
        $item->setQuantity($quantity);

        return $item;
    }

    private function variantItem(ProductVariantInterface $variant, int $quantity): WishlistItem
    {
        $item = new WishlistItem();
        $item->setVariant($variant);
        $item->setQuantity($quantity);

        return $item;
    }

    private function product(int $id): ProductInterface
    {
        $product = $this->prophesize(ProductInterface::class);
        $product->getId()->willReturn($id);

        return $product->reveal();
    }

    private function variant(int $id, ProductInterface $product): ProductVariantInterface
    {
        $variant = $this->prophesize(ProductVariantInterface::class);
        $variant->getId()->willReturn($id);
        $variant->getProduct()->willReturn($product);

        return $variant->reveal();
    }
}
