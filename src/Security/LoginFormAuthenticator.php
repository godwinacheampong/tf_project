<?php

namespace App\Security;

use App\Entity\Person;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Guard\Authenticator\AbstractFormLoginAuthenticator;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class LoginFormAuthenticator extends AbstractFormLoginAuthenticator
{
    use TargetPathTrait;

    private $entityManager;
    private $urlGenerator;
    private $csrfTokenManager;
    private $encoder;

    public function __construct(EntityManagerInterface $entityManager, UrlGeneratorInterface $urlGenerator, CsrfTokenManagerInterface $csrfTokenManager, RouterInterface $route, UserPasswordEncoderInterface $encoder)
    {
        $this->router = $route;
        $this->entityManager = $entityManager;
        $this->urlGenerator = $urlGenerator;
        $this->csrfTokenManager = $csrfTokenManager;
        $this->encoder = $encoder;
    }

    public function supports(Request $request)
    {
        return 'app_login' === $request->attributes->get('_route')
            && $request->isMethod('POST');
    }

    public function getCredentials(Request $request)
    {
        $credentials = [
            'username' => $request->request->get('username'),
            'password' => $request->request->get('password'),
            'csrf_token' => $request->request->get('_csrf_token'),
        ];
        $request->getSession()->set(
            Security::LAST_USERNAME,
            $credentials['username']
        );

        return $credentials;
    }

    public function getUser($credentials, UserProviderInterface $userProvider)
    {
        $token = new CsrfToken('authenticate', $credentials['csrf_token']);
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            throw new InvalidCsrfTokenException();
        }

        $user = $this->entityManager->getRepository(Person::class)->findOneBy(['loginname' => $credentials['username']]);


        if (!$user) {
            // fail authentication with a custom error
            throw new CustomUserMessageAuthenticationException('Gebruikersnaam kon niet gevonden worden.');
        }
        if (!$user->isEnabled()) {
            // fail authentication with a custom error
            throw new CustomUserMessageAuthenticationException('Uw account is gedisabled. Neem contact op met Training Center Den Haag');
        }
        return $user;
    }

    public function checkCredentials($credentials, UserInterface $user)
    {

//        dump(password_hash($credentials['password'], PASSWORD_DEFAULT));
//        dump($user->getPassword());
//        die();
        if ($this->encoder->isPasswordValid($user, $credentials['password'])) {
            return true;
        }
        throw new CustomUserMessageAuthenticationException("Ongeldige inloggegevens.");
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, $providerKey)
    {
        if ($targetPath = $this->getTargetPath($request->getSession(), $providerKey)) {
            return new RedirectResponse($targetPath);
        }
        switch ($token->getUser()->getRoles()[0]) {
            case 'ROLE_MEMBER':
                return new RedirectResponse($this->urlGenerator->generate("app_lid_home"));
                break;
            case 'ROLE_ADMIN':
                return new RedirectResponse($this->urlGenerator->generate('app_admin_main'));
                break;
            case 'ROLE_INSTRUCTOR':
                return new RedirectResponse($this->urlGenerator->generate("app_instructor_main"));
                break;
        }
    }

    protected function getLoginUrl()
    {
        return $this->urlGenerator->generate('app_login');
    }
}
