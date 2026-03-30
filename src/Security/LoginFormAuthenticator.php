<?php
namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class LoginFormAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'app_login';

    public function __construct(private UrlGeneratorInterface $urlGenerator)
    {
    }

    public function authenticate(Request $request): Passport
    {
        $email = $request->request->get('_username', '');

        try {
            if ($request->hasSession()) {
                $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $email);
            }
        } catch (\Throwable) {
            // Shared hosting can refuse the session path very early in the request lifecycle.
        }

        $badges = [];
        $csrfToken = trim((string) $request->request->get('_csrf_token', ''));
        if ($csrfToken !== '') {
            $badges[] = new \Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge('authenticate', $csrfToken);
        }

        return new Passport(
            new UserBadge($email),
            new PasswordCredentials($request->request->get('_password', '')),
            $badges
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        try {
            if ($request->hasSession() && ($targetPath = $this->getTargetPath($request->getSession(), $firewallName))) {
                return new RedirectResponse($targetPath);
            }
        } catch (\Throwable) {
            // Fall through to the default dashboard redirect when the session storage is unavailable.
        }

        return new RedirectResponse($this->urlGenerator->generate('app_dashboard'));
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}
