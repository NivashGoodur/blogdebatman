<?php

namespace App\Controller;

use App\Entity\Article;
use App\Form\EditPhotoType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class MainController extends AbstractController
{
    /**
     * @Route("/", name="main_home")
     */
    public function home(): Response
    {


        $articleRepo = $this->getDoctrine()->getRepository(Article::class);

        // Récupération des 3 derniers articles publié, par date la plus récente (le nombre d'article dépend du paramètre configuré dans le fichier services.yaml)
        $articles = $articleRepo->findBy([], ['publicationDate' => 'DESC'], $this->getParameter('app.article.last_article_number'));

        // Appel d'une vue en lui envoyant les derniers articles publiés à afficher
        return $this->render('main/home.html.twig', [
            'articles' => $articles
        ]);

    }

    /**
     * Page de profil
     *
     * @Route("/mon-profil/", name="main_profil")
     * @Security("is_granted('ROLE_USER')")
     */
    public function profil(): Response
    {
        return $this->render('main/profil.html.twig');
    }

    /**
     * Page de modification de la photo de profil
     *
     * @Route("/edit-photo/", name="main_edit_photo")
     * @Security("is_granted('ROLE_USER')")
     */
    public function editPhoto(Request $request): Response
    {

        // Création du formulaire de changement de photo
        $form = $this->createForm(EditPhotoType::class);

        // Liaison des données de requête (POST) avec le formulaire
        $form->handleRequest($request);

        // Si le formulaire a été envoyé et s'il ne contient pas d'erreur
        if($form->isSubmitted() && $form->isValid()){

            // Récupération du champ photo dans le formulaire
            $photo = $form->get('photo')->getData();

            // Si l'utilisateur a déjà une photo de profil, on la supprime
            if($this->getUser()->getPhoto() != null){

                // Suppression de l'ancienne photo
                unlink($this->getParameter('app.user.photo.directory') . $this->getUser()->getPhoto());
            }

            // Création d'un nouveau nom de fichier pour la nouvelle photo (boucle jusqu'à trouver un nom pas déjà utilisé)

            // NOTE : le nom de la photo est généré en calculant le hashage de l'id de l'utilisateur, concaténé avec une grande phrase aléatoire.
            // En théorie, chaque compte ayant un id unique, chaque photo devrait avoir un nom unique, ce qui rend la boucle inutile.
            // Seulement, c'est la théorie. En pratique il peut exister plusieurs noms différents ayant le même hashage (colision cryptographique)
            // Même si le taux de "malchance" que cela arrive est extrêment bas, ça ne coute rien de coder proprement pour
            // que ce problème ne puisse pas arriver du tout.
            do{

                // guessExtension() permet de récupérer la vrai extension du fichier, calculée par rapport à son vrai type MIME
                $newFileName = md5( $this->getUser()->getId() . random_bytes(100) ) . '.' . $photo->guessExtension();

            } while(file_exists($this->getParameter('app.user.photo.directory') . $newFileName));

            // Changement du nom de la photo stockée dans l'utilisateur connecté
            $this->getUser()->setPhoto($newFileName);

            // Application de ce changement dans la base de données via le manager général des entités
            $em = $this->getDoctrine()->getManager();
            $em->flush();

            // Déplacement physique de l'image dans le dossier paramétré dans le paramètre "app.user.photo.directory" dans le fichier config/services.yaml
            $photo->move(
                $this->getParameter('app.user.photo.directory'),    // Emplacement cible du déplacement
                $newFileName        // Nouveau nom du fichier déplacé
            );

            // Message flash de type "success"
            $this->addFlash('success', 'Photo de profil modifiée avec succès !');

            // Redirection de l'utilisateur vers la page de profil
            return $this->redirectToRoute('main_profil');

        }

        // Appel de la vue en lui envoyant le formulaire à afficher
        return $this->render('main/editPhoto.html.twig', [
            'form' => $form->createView()
        ]);
    }

}
