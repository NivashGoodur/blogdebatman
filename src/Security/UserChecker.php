<?php

namespace App\Security;

use App\Exception\AccountDeletedException;
use App\Entity\User as AppUser;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Classe permettant d'ajouter des vérifications additionnelles à la connexion des utilisateurs.
 * Pour être prise en compte, la classe doit être ajoutée dans le fichier config/packages/security.yaml
 */
class UserChecker implements UserCheckerInterface
{

    private $session;
    private $router;

    /**
     * On se sert du constructeur pour récupérer par autowiring les services d'accès aux sessions et routers
     */
    public function __construct(SessionInterface $session, UrlGeneratorInterface $router){
        $this->session = $session;
        $this->router = $router;
    }

    /**
     * Vérifications additionnelles avant que les identifiants soient vérifiés
     */
    public function checkPreAuth(UserInterface $user)
    {
        if (!$user instanceof AppUser) {
            return;
        }

    }

    /**
     * Vérifications additionnelles après que les identifiants soient vérifiés
     */
    public function checkPostAuth(UserInterface $user)
    {
        if (!$user instanceof AppUser) {
            return;
        }

        // Vérification que le compte est bien activé
        if (!$user->isVerified()) {

            // Lancement d'une erreur qui empêchera la connexion, tout en affichant un message d'erreur dans le formulaire de connexion
            throw new CustomUserMessageAuthenticationException('Vous devez d\'abord activer votre compte avec le mail que vous avez reçu avant de pouvoir vous connecter.');
        }
    }
}