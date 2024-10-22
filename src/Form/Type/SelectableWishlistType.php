<?php

declare(strict_types=1);

namespace Setono\SyliusWishlistPlugin\Form\Type;

use Setono\SyliusWishlistPlugin\Model\WishlistInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class SelectableWishlistType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('selected', CheckboxType::class, [
                'label' => false,
                'required' => false,
            ])
            ->add('name', TextType::class, [
                'label' => false,
            ])
            ->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($options) {
                /** @var WishlistInterface|null $data */
                $data = $event->getData();
                if (null === $data) {
                    return;
                }

                $event->setData([
                    'selected' => in_array($data, $options['selected'], true), // todo test that this works when objects are being fetched from two different places
                    'name' => $data->getName(),
                ]);
            })
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'selected' => [],
        ]);
    }
}
