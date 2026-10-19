<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Tests\Unit\DependencyInjection;

use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use PHPUnit\Framework\Attributes\Test;
use Setono\SyliusWishlistPlugin\Checker\CachedWishlistChecker;
use Setono\SyliusWishlistPlugin\DependencyInjection\SetonoSyliusWishlistExtension;
use Setono\SyliusWishlistPlugin\Model\GuestWishlist;
use Setono\SyliusWishlistPlugin\Model\UserWishlist;
use Setono\SyliusWishlistPlugin\Model\Wishlist;
use Setono\SyliusWishlistPlugin\Model\WishlistItem;
use Setono\SyliusWishlistPlugin\Provider\CachedWishlistProvider;
use Setono\SyliusWishlistPlugin\Provider\CompositeWishlistProvider;
use Setono\SyliusWishlistPlugin\Provider\WishlistProviderInterface;
use Setono\SyliusWishlistPlugin\Security\Voter\WishlistVoter;

final class SetonoSyliusWishlistExtensionTest extends AbstractExtensionTestCase
{
    protected function getContainerExtensions(): array
    {
        return [
            new SetonoSyliusWishlistExtension(),
        ];
    }

    #[Test]
    public function it_registers_the_resources(): void
    {
        $this->load();

        $this->assertContainerBuilderHasParameter('setono_sylius_wishlist.model.wishlist.class', Wishlist::class);
        $this->assertContainerBuilderHasParameter('setono_sylius_wishlist.model.guest_wishlist.class', GuestWishlist::class);
        $this->assertContainerBuilderHasParameter('setono_sylius_wishlist.model.user_wishlist.class', UserWishlist::class);
        $this->assertContainerBuilderHasParameter('setono_sylius_wishlist.model.wishlist_item.class', WishlistItem::class);
    }

    #[Test]
    public function it_loads_the_services(): void
    {
        $this->load();

        $this->assertContainerBuilderHasService(WishlistVoter::class);
        $this->assertContainerBuilderHasAlias(WishlistProviderInterface::class, CompositeWishlistProvider::class);
    }

    #[Test]
    public function it_resets_the_request_caches_between_requests(): void
    {
        $this->load();

        $this->assertContainerBuilderHasServiceDefinitionWithTag(CachedWishlistProvider::class, 'kernel.reset', ['method' => 'reset']);
        $this->assertContainerBuilderHasServiceDefinitionWithTag(CachedWishlistChecker::class, 'kernel.reset', ['method' => 'reset']);
    }

    #[Test]
    public function it_prepends_the_shop_twig_hooks(): void
    {
        $extension = new SetonoSyliusWishlistExtension();
        $extension->prepend($this->container);

        self::assertSame([[
            'hooks' => [
                'sylius_shop.base#stylesheets' => [
                    'setono_sylius_wishlist_styles' => [
                        'template' => '@SetonoSyliusWishlistPlugin/shop/_styles.html.twig',
                        'priority' => 0,
                    ],
                ],
                'sylius_shop.base#javascripts' => [
                    'setono_sylius_wishlist_javascripts' => [
                        'template' => '@SetonoSyliusWishlistPlugin/shop/_javascripts.html.twig',
                        'priority' => 0,
                    ],
                ],
                'sylius_shop.product.show.content.info.summary' => [
                    'setono_sylius_wishlist_toggle' => [
                        'template' => '@SetonoSyliusWishlistPlugin/shop/product/_wishlist_toggle.html.twig',
                        'priority' => 5,
                    ],
                ],
            ],
        ]], $this->container->getExtensionConfig('sylius_twig_hooks'));
    }
}
