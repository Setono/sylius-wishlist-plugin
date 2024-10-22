<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Form\Type;

use Setono\SyliusWishlistPlugin\Controller\Command\ProductWishlistsCommand;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class SelectWishlistsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('wishlists', WishlistChoiceType::class, [
                'multiple' => true,
                'expanded' => true,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ProductWishlistsCommand::class,
        ]);
    }
}
