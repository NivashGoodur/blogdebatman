<?php

namespace App\Form;

use App\Entity\Article;
use FOS\CKEditorBundle\Form\Type\CKEditorType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class NewArticleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            // Champ titre
            ->add('title', TextType::class, [
                'label' => 'Titre',
                'constraints' => [

                    new NotBlank([
                        'message' => 'Merci de renseigner un titre'
                    ]),

                    new Length([
                        'min' => 1,
                        'minMessage' => 'Le titre doit contenir au moins {{ limit }} caractères',
                        'max' => 150,
                        'maxMessage' => 'Le titre doit contenir au maximum {{ limit }} caractères'
                    ]),
                ]
            ])
            // Champ contenu
            ->add('content', CKEditorType::class, [
                'label' => 'Contenu',
                'purify_html' => true,  // Nettoyage de tout le html dangereux du contenu
                'constraints' => [
                    new NotBlank([
                        'message' => 'Merci de renseigner un contenu'
                    ]),
                    new Length([
                        'min' => 1,
                        'minMessage' => 'Le contenu doit contenir au moins 1 caractère',
                        'max' => 20000,
                        'maxMessage' => 'Le contenu doit contenir au maximum {{ limit }} caractères'
                    ]),
                ],
                'attr' => [
                    'class' => 'd-none'
                ]
            ])
            // Bouton de validation
            ->add('save', SubmitType::class, [
                'label' => 'Publier',
                'attr' => [
                    'class' => 'btn btn-outline-primary col-12'
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Article::class,
        ]);
    }
}
