<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Form\Type;

use Setono\SyliusWishlistPlugin\Model\WishlistItemInterface;
use Sylius\Bundle\ProductBundle\Form\Type\ProductVariantChoiceType;
use Sylius\Bundle\ResourceBundle\Form\Type\AbstractResourceType;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

class WishlistItemType extends AbstractResourceType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('quantity', IntegerType::class, [
                'attr' => ['min' => 1],
                'label' => 'sylius.ui.quantity',
            ])
            ->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
                $data = $event->getData();
                if (!$data instanceof WishlistItemInterface) {
                    return;
                }

                $product = $data->getProduct();
                if (null === $product) {
                    return;
                }

                $form = $event->getForm();
                $form->add('variant', ProductVariantChoiceType::class, [
                    'choice_label' => fn (ProductVariantInterface $variant) => implode(', ', array_map(static fn ($optionValue) => sprintf('%s: %s', (string) $optionValue->getOption()?->getName(), (string) $optionValue->getValue()), $variant->getOptionValues()->toArray())),
                    'placeholder' => 'setono_sylius_wishlist.ui.select_variant',
                    'product' => $product,
                    'expanded' => false,
                ]);
            });
    }

    public function getBlockPrefix(): string
    {
        return 'setono_sylius_wishlist__wishlist_item';
    }
}
