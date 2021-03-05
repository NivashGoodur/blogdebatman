<?php

namespace App\DataFixtures;

use App\Entity\Article;
use App\Entity\Comment;
use App\Entity\User;
use DateTime;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class AppFixtures extends Fixture
{

    private $encoder;

    /**
     * Utilisation du constructeur pour récupérer le service de hashage des mots de passe via autowiring
     */
    public function __construct(UserPasswordEncoderInterface $encoder)
    {
        $this->encoder = $encoder;
    }

    /**
     * Méthode chargée automatiquement au chargement des fixtures
     */
    public function load(ObjectManager $em)
    {

        // Instanciation du Faker
        $faker = Faker\Factory::create('fr_FR');



        // Création compte admin
        $admin = new User();

        // Hydratation du compte
        $admin
            ->setEmail('admin@a.a')
            ->setRegistrationDate( $faker->dateTimeBetween('-1 year', 'now') )
            ->setPseudonym('Batman')
            ->setRoles(["ROLE_ADMIN"])
            ->setPassword($this->encoder->encodePassword($admin, 'aaaaaaaaA7/'))
            ->setIsVerified(true)    // Compte activé
        ;

        // Persistance du compte
        $em->persist($admin);

        // Stockage de l'admin de côté pour créer des articles plus bas
        $users[] = $admin;



        // Création de 50 comptes utilisateur
        for($i = 0; $i < 50; $i++){

            // Création d'un nouveau compte
            $user = new User();

            // Hydratation du compte avec des données aléatoire
            $user
                ->setEmail( $faker->email )
                ->setRegistrationDate( $faker->dateTimeBetween('-1 year', 'now') )
                ->setPseudonym( $faker->userName )
                // Tous les comptes ont le même mot de passe
                ->setPassword( $this->encoder->encodePassword($user, 'aaaaaaaaA7/') )
                ->setIsVerified( $faker->boolean )
            ;

            // Persistance du compte
            $em->persist($user);

            // Stockage du compte de côté pour créer des articles plus bas
            $users[] = $user;

        }



        //  Création 200 articles
        for($i = 0; $i < 200; $i++){

            // Création d'un nouvel article
            $newArticle = new Article();

            // Hydratation de l'article
            $newArticle
                // Date de publication aléatoire entre la date d'inscription de l'auteur ($admin) et maintenant
                ->setPublicationDate( $faker->dateTimeBetween($admin->getRegistrationDate(), 'now') )
                ->setAuthor($admin)
                ->setTitle($faker->sentence(10))
                ->setContent($faker->paragraph(15))
            ;

            // Persistance de l'article
            $em->persist($newArticle);



            // Création entre 0 et 10 commentaires aléatoires par article
            $rand = rand(0, 10);

            for($j = 0; $j < $rand; $j++){

                // Création nouveau commentaire
                $newComment = new Comment();

                // Hydratation du commentaire
                $newComment
                    ->setArticle($newArticle)
                    // Date aléatoire entre la publication du dernier commentaire et maintenant
                    ->setPublicationDate($faker->dateTimeBetween( '-1 year' , 'now'))
                    // Auteur aléatoire parmis les comptes créés plus haut
                    ->setAuthor($faker->randomElement($users))
                    ->setContent($faker->paragraph(5))
                ;


                // Persistance du commentaire
                $em->persist($newComment);

            }

        }

        // Sauvegarde de tous les éléments créés en base de données via le manager général des entités
        $em->flush();
    }
}
