<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Recaptcha\RecaptchaValidator;
use App\Security\EmailVerifier;
use App\Repository\UserRepository;
use DateTime;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

class RegistrationController extends AbstractController
{
    private $emailVerifier;

    public function __construct(EmailVerifier $emailVerifier)
    {
        $this->emailVerifier = $emailVerifier;
    }

    /**
     * Page d'inscription
     *
     * @Route("/creer-un-compte/", name="main_register")
     */
    public function register(Request $request, UserPasswordEncoderInterface $passwordEncoder, RecaptchaValidator $recaptcha): Response
    {

        // Redirige de force vers l'accueil si l'utilisateur est déjà connecté
        if ($this->getUser()) {
            return $this->redirectToRoute('main_home');
        }

        // Création d'un nouvel objet "User"
        $user = new User();

        // Création d'un formulaire d'inscription, lié à l'objet vide créé juste avant
        $form = $this->createForm(RegistrationFormType::class, $user);

        // Liaison des données de requête (POST) avec le formulaire
        $form->handleRequest($request);

        // Si le formulaire a été envoyé
        if ($form->isSubmitted()) {

            // Vérification que le captcha est valide
            $captchaResponse = $request->request->get('g-recaptcha-response', null);

            // Si le captcha est null ou si il est invalide, ajout d'une erreur générale sur le formulaire (qui sera considéré comme échoué après)
            if ($captchaResponse == null || !$recaptcha->verify($captchaResponse, $request->server->get('REMOTE_ADDR'))) {

                // Ajout de l'erreur au formulaire
                $form->addError(new FormError('Veuillez remplir le Captcha de sécurité'));
            }

            if ($form->isValid()) {

                // Hydratation du nouveau compte
                $user
                    // Hashage du mot de passe
                    ->setPassword(
                        $passwordEncoder->encodePassword(
                            $user,
                            $form->get('plainPassword')->getData()
                        )
                    )
                    // Date actuelle
                    ->setRegistrationDate(new DateTime())
                ;

                // Sauvegarde du nouveau compte grâce au manager général des entités
                $entityManager = $this->getDoctrine()->getManager();
                $entityManager->persist($user);
                $entityManager->flush();

                // generate a signed url and email it to the user
                $this->emailVerifier->sendEmailConfirmation('main_verify_email', $user,
                    (new TemplatedEmail())
                        // Email et nom d'expéditeur paramétrables dans services.yaml (paramètres globaux)
                        ->from(new Address($this->getParameter('app.email.sender_email'), $this->getParameter('app.email.sender_name')))
                        ->to($user->getEmail())
                        ->subject('Activation de votre compte')
                        ->htmlTemplate('registration/confirmation_email.html.twig')
                );
                // do anything else you need here, like send an email

                // Message flash de succès
                $this->addFlash('success', 'Votre compte a été créé avec succès ! Un email vous a été envoyé pour activer votre compte.');

                // Redirection de l'utilisateur vers la page de connexion
                return $this->redirectToRoute('main_login');
            }
        }

        // Appel de la vue en envoyant le formulaire à afficher
        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }

    /**
     * Page d'activation du compte
     *
     * @Route("/verify/email", name="main_verify_email")
     */
    public function verifyUserEmail(Request $request, UserRepository $userRepository): Response
    {
        $id = $request->get('id');

        if (null === $id) {
            return $this->redirectToRoute('main_register');
        }

        $user = $userRepository->find($id);

        if (null === $user) {
            return $this->redirectToRoute('main_register');
        }

        // validate email confirmation link, sets User::isVerified=true and persists
        try {
            $this->emailVerifier->handleEmailConfirmation($request, $user);
        } catch (VerifyEmailExceptionInterface $exception) {
            $this->addFlash('error', $exception->getReason());

            return $this->redirectToRoute('main_login');
        }

        $this->addFlash('success', 'Votre compte a bien été activé !');

        return $this->redirectToRoute('main_login');
    }
}
