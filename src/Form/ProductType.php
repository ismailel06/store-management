<?php

namespace App\Form;

use App\Entity\Product;
use App\Entity\Supplier;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use App\Repository\SupplierRepository;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProductType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name')
            ->add('category', ChoiceType::class, [
                'choices' => [
                    'Electronics' => 'Electronics',
                    'Food' => 'Food',
                    'Drinks' => 'Drinks',
                    'Kitchen' => 'Kitchen',
                    'Cleaning' => 'Cleaning',
                    'Packaging' => 'Packaging',
                    'Other' => 'Other',
                ],
                'placeholder' => 'Choose a category',
            ])
            ->add('price', NumberType::class, [
                'html5' => true,
                'scale' => 2,
                'attr' => [
                    'min' => 0,
                    'step' => '0.01',
                    'placeholder' => 'e.g. 12.50',
                ],
            ])
            ->add('quantity')
            ->add('imageFile', FileType::class, [
                'label' => 'Product image (JPG/PNG/WebP)',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '2M',
                        'mimeTypes' => ['image/jpeg', 'image/png', 'image/webp'],
                        'mimeTypesMessage' => 'Please upload a valid image (JPG, PNG, WebP).',
                    ]),
                ],
            ])
            
            ->add('supplier', EntityType::class, [
                'class' => Supplier::class,
                'choice_label' => 'name',
                'placeholder' => 'Choose a supplier',
                'query_builder' => function (SupplierRepository $sr) {
                    return $sr->createQueryBuilder('s')
                        ->andWhere('s.isArchived = false')
                        ->orderBy('s.name', 'ASC');
                },
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Product::class,
        ]);
    }
}
