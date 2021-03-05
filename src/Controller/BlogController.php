<?php

namespace App\Controller;

use App\Entity\Article;
use App\Entity\Comment;
use App\Form\CommentFormType;
use App\Form\NewArticleType;
use DateTime;
use Knp\Component\Pager\PaginatorInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Contrôleurs de la partie blog du site. Toutes les routes commenceront leur url par "/blog" et leur nom par "blog_"
 *
 * @Route("/blog", name="blog_")
 */
class BlogController extends AbstractController
{

    /**
     * Page admin permettant de créer une nouvelle publication
     *
     * @Route("/nouvelle-publication/", name="new_publication")
     * @Security("is_granted('ROLE_ADMIN')")
     */
    public function newPublication(Request $request): Response
    {

        // Création d'un nouvel article vide
        $newArticle = new Article();

        // Création d'un formulaire de création d'article, lié à l'article vide
        $form = $this->createForm(NewArticleType::class, $newArticle);

        // Liaison des données de requête (POST) avec le formulaire
        $form->handleRequest($request);

        // Si le formulaire est envoyé et n'a pas d'erreur
        if($form->isSubmitted() && $form->isValid()){

            // Hydratation de l'article
            $newArticle
                ->setAuthor($this->getUser())   // L'auteur est l'utilisateur connecté
                ->setPublicationDate(new DateTime())    // Date actuelle
            ;

            // Sauvegarde de l'article en base de données via le manage général des entités
            $em = $this->getDoctrine()->getManager();
            $em->persist($newArticle);
            $em->flush();

            // Message flash de type "success"
            $this->addFlash('success', 'Article publié avec succès !');

            // Redirection de l'utilisateur vers la page détaillée de l'article tout nouvellement créé
            return $this->redirectToRoute('blog_publication_view', [
                'slug' => $newArticle->getSlug()
            ]);

        }

        // Appel de la vue en lui envoyant le formulaire à afficher
        return $this->render('blog/newPublication.html.twig', [
            'form' => $form->createView()
        ]);
    }

    /**
     * Page affichant la liste paginée des publications du site
     *
     * @Route("/publications/liste/", name="publication_list")
     */
    public function publicationList(Request $request, PaginatorInterface $paginator): Response
    {

        // Récupération du numéro de la page demandée dans l'url (si il existe pas, 1 sera pris à la place)
        $requestedPage = $request->query->getInt('page', 1);

        // Si la page demandée est inférieur à 1, erreur 404
        if($requestedPage < 1){
            throw new NotFoundHttpException();
        }

        // Récupération du manager général des entités
        $em = $this->getDoctrine()->getManager();

        // Création d'une requête permettant de récupérer les articles (uniquement ceux de la page demandée, grâce au paginator,et non tous les articles)
        $query = $em->createQuery('SELECT a FROM App\Entity\Article a ORDER BY a.publicationDate DESC');

        // Récupération des articles
        $articles = $paginator->paginate(
            $query,             // Requête créée précedemment
            $requestedPage,     // Numéro de la page demandée
            10              // Nombre d'articles affichés par page
        );

        // Appel de la vue en envoyant les articles à afficher
        return $this->render('blog/publicationList.html.twig', [
            'articles' => $articles
        ]);

    }

    /**
     * Page d'affichage d'une publication en détail
     *
     * @Route("/publication/{slug}/", name="publication_view")
     */
    public function publicationView(Article $article, Request $request): Response
    {

        // Si l'utilisateur n'est pas connecté, appel direct de la vue en lui envoyant l'article à afficher
        // On fait ça pour éviter que le traitement du formulaire en dessous ne soit invoqué par un autre moyen même si le formulaire html est masqué
        if(!$this->getUser()){
            return $this->render('blog/publicationView.html.twig', [
                'article' => $article,
            ]);
        }

        // Création d'un commentaire vide
        $newComment = new Comment();

        // Création d'un formulaire de création de commentaire, lié au commentaire vide
        $form = $this->createForm(CommentFormType::class, $newComment);

        // Liaison des données de requête (POST) avec le formulaire
        $form->handleRequest($request);

        // Si le formulaire est envoyé et n'a pas d'erreur
        if($form->isSubmitted() && $form->isValid()){

            // Hydratation du commentaire
            $newComment
                ->setAuthor($this->getUser())           // L'auteur est l'utilisateur connecté
                ->setPublicationDate(new DateTime())    // Date actuelle
                ->setArticle($article)                  // Lié à l'article actuellement affiché sur la page
            ;

            // Sauvegarde du commentaire en base de données via le manager général des entités
            $em = $this->getDoctrine()->getManager();
            $em->persist($newComment);
            $em->flush();

            // Message flash de type "success
            $this->addFlash('success', 'Votre commentaire a été publié avec succès !');

            // Suppression des deux variables contenant le formulaire validé et le commentaire nouvellement créé (pour éviter que le nouveau formulaire soit rempli avec)
            unset($newComment);
            unset($form);

            // Création d'un nouveau commentaire vide et de son formulaire lié
            $newComment = new Comment();
            $form = $this->createForm(CommentFormType::class, $newComment);

        }

        // Appel de la vue en lui envoyant l'article et le formulaire à afficher
        return $this->render('blog/publicationView.html.twig', [
            'article' => $article,
            'commentForm' => $form->createView()
        ]);
    }


    /**
     * Page affichant les résultats de recherches faites par le formulaire de recherche dans la navbar
     *
     * @Route("/recherche/", name="search")
     */
    public function search(Request $request, PaginatorInterface $paginator): Response
    {

        // Récupération du numéro de la page demandée dans l'url (si il existe pas, 1 sera pris à la place)
        $requestedPage = $request->query->getInt('page', 1);

        // Si la page demandée est inférieur à 1, erreur 404
        if($requestedPage < 1){
            throw new NotFoundHttpException();
        }

        // Récupération du manager général des entités
        $em = $this->getDoctrine()->getManager();

        // Recherche de l'utilisateur, récupérée depuis l'url ($_GET['q'])
        $search = $request->query->get('q');

        // Création d'une requête permettant de récupérer les articles pour la page actuelle, dont le titre ou le contenu contient la recherche de l'utilisateur
        $query = $em
            ->createQuery('SELECT a FROM App\Entity\Article a WHERE a.title LIKE :search OR a.content LIKE :search ORDER BY a.publicationDate DESC')
            ->setParameters(['search' => '%' . $search . '%'])
        ;

        // Récupération des articles
        $articles = $paginator->paginate(
            $query,
            $requestedPage,
            15
        );

        // Appel de la vue en lui envoyant les articles à afficher
        return $this->render('blog/listSearch.html.twig', ['articles' => $articles]);
    }

    /**
     * Page admin servant à supprimer un commentaire via son id passé dans l'url
     *
     * @Route("/commentaire/suppression/{id}/", name="comment_delete")
     * @Security("is_granted('ROLE_ADMIN')")
     */
    public function commentDelete(Comment $comment, Request $request): Response
    {

        // Si le token CSRF passé dans l'url n'est pas le token valide, message d'erreur
        if(!$this->isCsrfTokenValid('blog_comment_delete'. $comment->getId(), $request->query->get('csrf_token'))){

            $this->addFlash('error', 'Token sécurité invalide, veuillez ré-essayer.');

        } else {

            // Suppression du commentaire via le manager général des entités
            $em = $this->getDoctrine()->getManager();
            $em->remove($comment);
            $em->flush();

            // Message flash de type "success" pour indiquer la réussite de la suppression
            $this->addFlash('success', 'Le commentaire a été supprimé avec succès!');

        }

        // Redirection de l'utilisateur sur la page détaillée de l'article auquel est/était rattaché le commentaire
        return $this->redirectToRoute('blog_publication_view', [
            'slug' => $comment->getArticle()->getSlug(),
        ]);

    }

    /**
     * Page admin servant à supprimer un article via son id passé dans l'url
     *
     * @Route("/publication/suppression/{id}/", name="publication_delete")
     * @Security("is_granted('ROLE_ADMIN')")
     */
    public function publicationDelete(Article $article, Request $request): Response
    {

        // Si le token CSRF passé dans l'url n'est pas le token valide, message d'erreur
        if(!$this->isCsrfTokenValid('blog_publication_delete_'. $article->getId(), $request->query->get('csrf_token'))){

            $this->addFlash('error', 'Token sécurité invalide, veuillez ré-essayer.');

        } else {

            // Suppression de l'article via le manager général des entités
            $em = $this->getDoctrine()->getManager();
            $em->remove($article);
            $em->flush();

            // Message flash de type "success" pour indiquer la réussite de la suppression
            $this->addFlash('success', 'La publication a été supprimé avec succès!');

        }

        // Redirection de l'utilisateur sur la liste des articles
        return $this->redirectToRoute('blog_publication_list');
    }

    /**
     * Page admin permettant de modifier un article existant via son id passé dans l'url
     *
     * @Route("/publication/modifier/{id}/", name="publication_edit")
     * @Security("is_granted('ROLE_ADMIN')")
     */
    public function publicationEdit(Article $article, request $request): Response
    {

        // Création du formulaire de modification d'article (c'est le même que le formulaire permettant de créer un nouvel article, sauf qu'il sera déjà rempli avec les données de l'article existant "$article")
        $form = $this->createForm(NewArticleType::class, $article);

        // Liaison des données de requête (POST) avec le formulaire
        $form->handleRequest($request);

        // Si le formulaire est envoyé et n'a pas d'erreur
        if($form->isSubmitted() && $form->isValid()){

            // Sauvegarde des changements faits dans l'article via le manager général des entités
            $em = $this->getDoctrine()->getManager();
            $em->flush();

            // Message flash de type "success"
            $this->addFlash('success', 'Article modifié avec succès !');

            // Redirection vers la page de l'article modifié
            return $this->redirectToRoute('blog_publication_view', ['slug' => $article->getSlug()]);

        }

        // Appel de la vue en lui envoyant le formulaire à afficher
        return $this->render('blog/editPublication.html.twig', [
            'form' => $form->createView()
        ]);

    }

}