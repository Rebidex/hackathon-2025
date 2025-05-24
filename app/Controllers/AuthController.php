<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\Service\AuthService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Views\Twig;

class AuthController extends BaseController
{
    public function __construct(
        Twig $view,
        private AuthService $authService,
        private LoggerInterface $logger,
    ) {
        parent::__construct($view);
    }

    public function showRegister(Request $request, Response $response): Response
    {
        $this->logger->info('Register page requested');
        return $this->render($response, 'auth/register.twig');
    }

    public function register(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $username = $data['username'] ?? '';
        $password = $data['password'] ?? '';
        $passwordConfirm = $data['password_confirm'] ?? '';

        $result = $this->authService->registerNewUser($username, $password, $passwordConfirm);

        if(!$result['success'])
        {
            return $this->render($response, 'auth/register.twig', [
                'username'=>$username,
                'errors'=>$result['errors'],
            ]);
        }
        $this->logger->info(sprintf('New user registered: %s', $username));
        return $response->withHeader('Location', '/login')->withStatus(302);
    }

    public function showLogin(Request $request, Response $response): Response
    {
        return $this->render($response, 'auth/login.twig');
    }

    public function login(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $username = $data['username'] ?? '';
        $password = $data['password'] ?? '';

        if(!$this->authService->attemptLogin($username, $password))
        {
            return $this->render($response,'auth/login.twig',[
                'error'=>'We could not find an account with those credentials.',
                'username'=>$username,
            ]);
        }

        $this->logger->info(sprintf('User logged in: %s', $username));
        return $response->withHeader('Location', '/')->withStatus(302);
    }

    public function logout(Request $request, Response $response): Response
    {
        if(session_status() === PHP_SESSION_ACTIVE)
        {
            $_SESSION = [];
            $cookieParameters = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $cookieParameters['path'],
                $cookieParameters['domain'],
                $cookieParameters['secure'],
                $cookieParameters['httponly']
            );
            session_destroy();
        }

        return $response->withHeader('Location', '/login')->withStatus(302);
    }
}
