<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Tests\Unit\DependencyInjection;

use Matthias\SymfonyConfigTest\PhpUnit\ConfigurationTestCaseTrait;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Setono\SyliusWishlistPlugin\DependencyInjection\Configuration;
use Setono\SyliusWishlistPlugin\Model\GuestWishlist;
use Setono\SyliusWishlistPlugin\Model\UserWishlist;
use Setono\SyliusWishlistPlugin\Model\Wishlist;
use Setono\SyliusWishlistPlugin\Model\WishlistItem;
use Setono\SyliusWishlistPlugin\Repository\GuestWishlistRepository;
use Setono\SyliusWishlistPlugin\Repository\UserWishlistRepository;
use Setono\SyliusWishlistPlugin\Repository\WishlistRepository;
use Sylius\Resource\Factory\Factory;

final class ConfigurationTest extends TestCase
{
    use ConfigurationTestCaseTrait;

    protected function getConfiguration(): Configuration
    {
        return new Configuration();
    }

    #[Test]
    public function it_has_default_classes_for_every_resource(): void
    {
        $this->assertProcessedConfigurationEquals([[]], ['resources' => self::defaultResources()]);
    }

    #[Test]
    public function it_accepts_resource_options(): void
    {
        $expected = self::defaultResources();
        $expected['wishlist_item']['options'] = ['foo' => 'bar'];

        $this->assertProcessedConfigurationEquals([[
            'resources' => [
                'wishlist_item' => [
                    'options' => ['foo' => 'bar'],
                ],
            ],
        ]], ['resources' => $expected]);
    }

    #[Test]
    public function it_rejects_an_empty_repository_class(): void
    {
        $this->assertConfigurationIsInvalid([[
            'resources' => [
                'wishlist' => [
                    'classes' => ['repository' => ''],
                ],
            ],
        ]], 'cannot contain an empty value');
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function defaultResources(): array
    {
        return [
            'wishlist' => [
                'classes' => [
                    'model' => Wishlist::class,
                    'repository' => WishlistRepository::class,
                    'factory' => Factory::class,
                ],
            ],
            'guest_wishlist' => [
                'classes' => [
                    'model' => GuestWishlist::class,
                    'repository' => GuestWishlistRepository::class,
                    'factory' => Factory::class,
                ],
            ],
            'user_wishlist' => [
                'classes' => [
                    'model' => UserWishlist::class,
                    'repository' => UserWishlistRepository::class,
                    'factory' => Factory::class,
                ],
            ],
            'wishlist_item' => [
                'classes' => [
                    'model' => WishlistItem::class,
                    'factory' => Factory::class,
                ],
            ],
        ];
    }
}
