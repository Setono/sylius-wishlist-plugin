<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Tests\Unit\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Setono\SyliusWishlistPlugin\Controller\AddToWishlistAction;
use Setono\SyliusWishlistPlugin\Factory\WishlistFactoryInterface;
use Setono\SyliusWishlistPlugin\Factory\WishlistItemFactoryInterface;
use Setono\SyliusWishlistPlugin\Model\UserWishlist;
use Setono\SyliusWishlistPlugin\Model\WishlistInterface;
use Setono\SyliusWishlistPlugin\Model\WishlistItem;
use Setono\SyliusWishlistPlugin\Provider\WishlistProviderInterface;
use Sylius\Component\Core\Model\Product;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariant;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class AddToWishlistActionTest extends TestCase
{
    #[Test]
    public function it_throws_not_found_when_the_product_does_not_exist(): void
    {
        $action = $this->action(Product::class, $this->manager(null), [$this->wishlist()]);

        $this->expectException(NotFoundHttpException::class);

        $action(1);
    }

    #[Test]
    public function it_adds_the_product_to_the_preselected_wishlist(): void
    {
        $product = $this->product(1);
        $wishlist = $this->wishlist();

        $manager = $this->manager($product);
        $manager->expects(self::once())->method('persist')->with($wishlist);
        $manager->expects(self::once())->method('flush');

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator
            ->expects(self::once())
            ->method('generate')
            ->with('setono_sylius_wishlist_shop_wishlist_remove_product', ['id' => 1])
            ->willReturn('/remove')
        ;

        $response = $this->action(Product::class, $manager, [$wishlist], urlGenerator: $urlGenerator)(1);

        self::assertTrue($wishlist->hasProduct($product));
        self::assertSame(
            ['event' => 'added', 'toggleUrl' => '/remove', 'wishlistItemsCount' => 1],
            json_decode((string) $response->getContent(), true),
        );
    }

    #[Test]
    public function it_adds_the_variant_to_the_preselected_wishlist(): void
    {
        $variant = $this->variant(10, $this->product(1));
        $wishlist = $this->wishlist();

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator
            ->expects(self::once())
            ->method('generate')
            ->with('setono_sylius_wishlist_shop_wishlist_remove_product_variant', ['id' => 10])
            ->willReturn('/remove')
        ;

        $this->action(ProductVariant::class, $this->manager($variant), [$wishlist], urlGenerator: $urlGenerator)(10);

        self::assertTrue($wishlist->hasProductVariant($variant));
    }

    #[Test]
    public function it_does_not_add_a_product_that_is_already_on_the_wishlist(): void
    {
        $product = $this->product(1);
        $wishlist = $this->wishlist();
        $wishlist->addItem($this->item($product));

        $this->action(Product::class, $this->manager($product), [$wishlist])(1);

        self::assertCount(1, $wishlist->getItems());
    }

    #[Test]
    public function it_does_not_add_a_variant_that_is_already_on_the_wishlist(): void
    {
        $variant = $this->variant(10, $this->product(1));
        $wishlist = $this->wishlist();
        $item = new WishlistItem();
        $item->setVariant($variant);
        $wishlist->addItem($item);

        $this->action(ProductVariant::class, $this->manager($variant), [$wishlist])(10);

        self::assertCount(1, $wishlist->getItems());
    }

    #[Test]
    public function it_adds_a_separate_item_to_each_preselected_wishlist(): void
    {
        $product = $this->product(1);
        $first = $this->wishlist();
        $second = $this->wishlist();

        $response = $this->action(Product::class, $this->manager($product), [$first, $second])(1);

        $firstItem = $first->getItems()->first();
        $secondItem = $second->getItems()->first();
        self::assertInstanceOf(WishlistItem::class, $firstItem);
        self::assertInstanceOf(WishlistItem::class, $secondItem);
        self::assertNotSame($firstItem, $secondItem);
        self::assertSame($first, $firstItem->getWishlist());
        self::assertSame($second, $secondItem->getWishlist());
        self::assertSame(
            ['event' => 'added', 'toggleUrl' => '/remove', 'wishlistItemsCount' => 2],
            json_decode((string) $response->getContent(), true),
        );
    }

    #[Test]
    public function it_creates_a_wishlist_when_none_is_preselected(): void
    {
        $product = $this->product(1);
        $wishlist = $this->wishlist();

        $wishlistFactory = $this->createMock(WishlistFactoryInterface::class);
        $wishlistFactory->expects(self::once())->method('createNew')->willReturn($wishlist);

        $this->action(Product::class, $this->manager($product), [], $wishlistFactory)(1);

        self::assertTrue($wishlist->hasProduct($product));
    }

    /**
     * @param class-string<ProductInterface|ProductVariantInterface> $className
     * @param list<WishlistInterface> $preSelectedWishlists
     */
    private function action(
        string $className,
        EntityManagerInterface $manager,
        array $preSelectedWishlists,
        ?WishlistFactoryInterface $wishlistFactory = null,
        ?UrlGeneratorInterface $urlGenerator = null,
    ): AddToWishlistAction {
        $managerRegistry = $this->createMock(ManagerRegistry::class);
        $managerRegistry->method('getManagerForClass')->willReturn($manager);

        $wishlistProvider = $this->createMock(WishlistProviderInterface::class);
        $wishlistProvider->method('getPreSelectedWishlists')->willReturn($preSelectedWishlists);

        $wishlistItemFactory = $this->createMock(WishlistItemFactoryInterface::class);
        $wishlistItemFactory->method('createWithProduct')->willReturnCallback($this->item(...));
        $wishlistItemFactory->method('createWithProductVariant')->willReturnCallback(static function (ProductVariantInterface $variant): WishlistItem {
            $item = new WishlistItem();
            $item->setVariant($variant);

            return $item;
        });

        if (null === $urlGenerator) {
            $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
            $urlGenerator->method('generate')->willReturn('/remove');
        }

        return new AddToWishlistAction(
            $wishlistProvider,
            $wishlistItemFactory,
            $managerRegistry,
            $wishlistFactory ?? $this->createMock(WishlistFactoryInterface::class),
            $urlGenerator,
            $className,
        );
    }

    private function manager(?object $entity): EntityManagerInterface&MockObject
    {
        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->method('find')->willReturn($entity);

        return $manager;
    }

    private function wishlist(): UserWishlist
    {
        return new UserWishlist();
    }

    private function item(ProductInterface $product): WishlistItem
    {
        $item = new WishlistItem();
        $item->setProduct($product);

        return $item;
    }

    private function product(int $id): ProductInterface&MockObject
    {
        $product = $this->createMock(ProductInterface::class);
        $product->method('getId')->willReturn($id);

        return $product;
    }

    private function variant(int $id, ProductInterface $product): ProductVariantInterface&MockObject
    {
        $variant = $this->createMock(ProductVariantInterface::class);
        $variant->method('getId')->willReturn($id);
        $variant->method('getProduct')->willReturn($product);

        return $variant;
    }
}
