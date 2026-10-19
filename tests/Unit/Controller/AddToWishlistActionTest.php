<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Tests\Unit\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
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
    use ProphecyTrait;

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

        $manager = $this->prophesize(EntityManagerInterface::class);
        $manager->find(Argument::cetera())->willReturn($product);
        $manager->persist($wishlist)->shouldBeCalledOnce();
        $manager->flush()->shouldBeCalledOnce();

        $urlGenerator = $this->prophesize(UrlGeneratorInterface::class);
        $urlGenerator
            ->generate('setono_sylius_wishlist_shop_wishlist_remove_product', ['id' => 1])
            ->shouldBeCalledOnce()
            ->willReturn('/remove')
        ;

        $response = $this->action(Product::class, $manager->reveal(), [$wishlist], urlGenerator: $urlGenerator->reveal())(1);

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

        $urlGenerator = $this->prophesize(UrlGeneratorInterface::class);
        $urlGenerator
            ->generate('setono_sylius_wishlist_shop_wishlist_remove_product_variant', ['id' => 10])
            ->shouldBeCalledOnce()
            ->willReturn('/remove')
        ;

        $this->action(ProductVariant::class, $this->manager($variant), [$wishlist], urlGenerator: $urlGenerator->reveal())(10);

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

        $wishlistFactory = $this->prophesize(WishlistFactoryInterface::class);
        $wishlistFactory->createNew()->shouldBeCalledOnce()->willReturn($wishlist);

        $this->action(Product::class, $this->manager($product), [], $wishlistFactory->reveal())(1);

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
        $managerRegistry = $this->prophesize(ManagerRegistry::class);
        $managerRegistry->getManagerForClass(Argument::any())->willReturn($manager);

        $wishlistProvider = $this->prophesize(WishlistProviderInterface::class);
        $wishlistProvider->getPreSelectedWishlists()->willReturn($preSelectedWishlists);

        // Prophecy binds non-static closures to the double, so the item helper is captured instead of called on $this
        $item = $this->item(...);

        $wishlistItemFactory = $this->prophesize(WishlistItemFactoryInterface::class);
        $wishlistItemFactory->createWithProduct(Argument::cetera())->will(static function (array $args) use ($item): WishlistItem {
            self::assertInstanceOf(ProductInterface::class, $args[0]);

            return $item($args[0]);
        });
        $wishlistItemFactory->createWithProductVariant(Argument::cetera())->will(static function (array $args): WishlistItem {
            self::assertInstanceOf(ProductVariantInterface::class, $args[0]);

            $item = new WishlistItem();
            $item->setVariant($args[0]);

            return $item;
        });

        if (null === $urlGenerator) {
            $defaultUrlGenerator = $this->prophesize(UrlGeneratorInterface::class);
            $defaultUrlGenerator->generate(Argument::cetera())->willReturn('/remove');
            $urlGenerator = $defaultUrlGenerator->reveal();
        }

        return new AddToWishlistAction(
            $wishlistProvider->reveal(),
            $wishlistItemFactory->reveal(),
            $managerRegistry->reveal(),
            $wishlistFactory ?? $this->prophesize(WishlistFactoryInterface::class)->reveal(),
            $urlGenerator,
            $className,
        );
    }

    private function manager(?object $entity): EntityManagerInterface
    {
        $manager = $this->prophesize(EntityManagerInterface::class);
        $manager->find(Argument::cetera())->willReturn($entity);

        // Once a double has a stubbed method, Prophecy rejects calls to any method it wasn't told about, so allow the writes
        $manager->persist(Argument::any())->will(static function (): void {});
        $manager->flush()->will(static function (): void {});

        return $manager->reveal();
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
