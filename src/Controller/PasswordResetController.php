<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PasswordResetController extends AbstractController
{
    #[Route('/forgot-password', name: 'app_forgot_password', methods: ['GET', 'POST'])]
    public function requestReset(
        Request $request,
        UtilisateurRepository $utilisateurRepository,
        EntityManagerInterface $entityManager,
        TransportInterface $mailerTransport,
        string $mailerFrom,
    ): Response {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('forgot_password', (string) $request->request->get('_csrf_token'))) {
                $this->addFlash('danger', 'La demande de reinitialisation a expire. Merci de reessayer.');

                return $this->redirectToRoute('app_forgot_password');
            }

            $email = mb_strtolower(trim((string) $request->request->get('email')));
            $user = $utilisateurRepository->findOneBy(['email' => $email]);

            if ($user instanceof Utilisateur) {
                $plainToken = bin2hex(random_bytes(32));
                $user
                    ->setResetPasswordToken(hash('sha256', $plainToken))
                    ->setResetPasswordExpiresAt(new \DateTime('+1 hour'));

                $entityManager->flush();

                $resetUrl = $this->generateUrl('app_reset_password', [
                    'token' => $plainToken,
                ], UrlGeneratorInterface::ABSOLUTE_URL);

                $emailMessage = (new Email())
                    ->from($mailerFrom)
                    ->to($user->getEmail())
                    ->subject('Reinitialisation de votre mot de passe')
                    ->text($this->buildTextBody($user, $resetUrl))
                    ->html($this->buildHtmlBody($user, $resetUrl));

                try {
                    $mailerTransport->send($emailMessage);
                } catch (TransportExceptionInterface) {
                    $this->addFlash('danger', 'Le lien a ete genere, mais l\'email n\'a pas pu etre envoye. Verifie la configuration MAILER_DSN.');

                    return $this->redirectToRoute('app_forgot_password');
                }
            }

            $this->addFlash('success', 'Si cette adresse existe, un lien de reinitialisation vient d\'etre envoye.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/forgot_password.html.twig');
    }

    #[Route('/reset-password/{token}', name: 'app_reset_password', methods: ['GET', 'POST'])]
    public function resetPassword(
        string $token,
        Request $request,
        UtilisateurRepository $utilisateurRepository,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
    ): Response {
        $hashedToken = hash('sha256', $token);
        $user = $utilisateurRepository->findOneBy(['resetPasswordToken' => $hashedToken]);

        if (
            !$user instanceof Utilisateur
            || null === $user->getResetPasswordExpiresAt()
            || $user->getResetPasswordExpiresAt() < new \DateTime()
        ) {
            $this->addFlash('danger', 'Ce lien de reinitialisation est invalide ou expire.');

            return $this->redirectToRoute('app_forgot_password');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('reset_password', (string) $request->request->get('_csrf_token'))) {
                $this->addFlash('danger', 'La reinitialisation a expire. Merci de recommencer.');

                return $this->redirectToRoute('app_forgot_password');
            }

            $password = (string) $request->request->get('password');
            $passwordConfirmation = (string) $request->request->get('password_confirmation');

            if (mb_strlen($password) < 8) {
                $this->addFlash('danger', 'Le mot de passe doit contenir au moins 8 caracteres.');

                return $this->render('security/reset_password.html.twig', [
                    'token' => $token,
                ]);
            }

            if ($password !== $passwordConfirmation) {
                $this->addFlash('danger', 'Les mots de passe saisis ne correspondent pas.');

                return $this->render('security/reset_password.html.twig', [
                    'token' => $token,
                ]);
            }

            $user
                ->setMotDePasse($passwordHasher->hashPassword($user, $password))
                ->setResetPasswordToken(null)
                ->setResetPasswordExpiresAt(null);

            $entityManager->flush();

            $this->addFlash('success', 'Ton mot de passe a ete reinitialise. Tu peux maintenant te connecter.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/reset_password.html.twig', [
            'token' => $token,
        ]);
    }

    private function buildTextBody(Utilisateur $user, string $resetUrl): string
    {
        return implode("\n\n", [
            sprintf('Bonjour %s,', $user->getPrenom() ?: $user->getEmail()),
            'Une demande de reinitialisation de mot de passe a ete effectuee pour votre compte.',
            sprintf('Cliquez sur ce lien pour definir un nouveau mot de passe : %s', $resetUrl),
            'Ce lien expire dans 1 heure.',
            'Si vous n\'etes pas a l\'origine de cette demande, vous pouvez ignorer cet email.',
        ]);
    }

    private function buildHtmlBody(Utilisateur $user, string $resetUrl): string
    {
        $displayName = htmlspecialchars((string) ($user->getPrenom() ?: $user->getEmail()), ENT_QUOTES, 'UTF-8');
        $safeUrl = htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8');

        return <<<HTML
<div style="font-family: Arial, sans-serif; max-width: 640px; margin: 0 auto; color: #1f2937;">
    <h2 style="margin-bottom: 16px;">Reinitialisation du mot de passe</h2>
    <p>Bonjour {$displayName},</p>
    <p>Une demande de reinitialisation a ete effectuee pour votre compte.</p>
    <p>
        <a href="{$safeUrl}" style="display: inline-block; background: #0d6efd; color: #ffffff; padding: 12px 18px; text-decoration: none; border-radius: 8px;">
            Choisir un nouveau mot de passe
        </a>
    </p>
    <p>Ce lien expire dans 1 heure.</p>
    <p>Si vous n'etes pas a l'origine de cette demande, ignorez simplement cet email.</p>
</div>
HTML;
    }
}
