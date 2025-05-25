<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\Service\AuthService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use RuntimeException;
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
        $data = (array) $request->getParsedBody();

        $username = trim($data['username'] ?? '');
        $password = $data['password'] ?? '';

        try {
            $this->authService->register($username, $password);
            return $response->withHeader('Location', '/login')->withStatus(302);
        } catch (RuntimeException $e) {
            $this->logger->error('Registration failed: ' . $e->getMessage());

            return $this->render($response, 'auth/register.twig', [
                'errors' => [$e->getMessage()],
                'old' => ['username' => $username],
            ]);
        }
    }

    public function showLogin(Request $request, Response $response): Response
    {
        return $this->render($response, 'auth/login.twig');
    }

    public function login(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();

        $username = trim($data['username'] ?? '');
        $password = $data['password'] ?? '';

        if ($this->authService->attempt($username, $password)) {
            session_regenerate_id(true);
            return $response->withHeader('Location', '/')->withStatus(302);
        }

        $this->logger->warning("Login failed for user: $username");

        return $this->render($response, 'auth/login.twig', [
            'errors' => ['Invalid username or password.'],
            'old' => ['username' => $username],
        ]);
    }

    public function logout(Request $request, Response $response): Response
    {
        $_SESSION = [];
        session_destroy();

        return $response->withHeader('Location', '/login')->withStatus(302);
    }
}
