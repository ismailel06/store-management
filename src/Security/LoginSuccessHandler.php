<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

class LoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(private UrlGeneratorInterface $urlGenerator)
    {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): ?RedirectResponse
    {
        // If Symfony saved a target URL (user tried to open a protected page first)
        $session = $request->getSession();
        $key = '_security_main.target_path'; // firewall name is "main"
        if ($session && $session->has($key)) {
            $target = (string) $session->get($key);
            $session->remove($key);

            return new RedirectResponse($target);
        }

        // Otherwise redirect by role
        if (in_array('ROLE_ADMIN', $token->getRoleNames(), true)) {
            return new RedirectResponse($this->urlGenerator->generate('dashboard'));
        }

        return new RedirectResponse($this->urlGenerator->generate('app_shop_index'));
    }
}
