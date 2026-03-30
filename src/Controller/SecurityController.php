<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // if user is already logged in, redirect them
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        $error = null;
        $lastUsername = '';
        $sessionFeedbackAvailable = true;
        $loginCsrfToken = '';

        try {
            $error = $authenticationUtils->getLastAuthenticationError();
            $lastUsername = $authenticationUtils->getLastUsername();
        } catch (\Throwable) {
            $sessionFeedbackAvailable = false;
        }

        try {
            $csrfTokenManager = $this->container->get('security.csrf.token_manager');
            $loginCsrfToken = $csrfTokenManager->getToken('authenticate')->getValue();
        } catch (\Throwable) {
            $loginCsrfToken = '';
        }

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
            'login_csrf_token' => $loginCsrfToken,
            'session_feedback_available' => $sessionFeedbackAvailable,
        ]);
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}
