<?php

declare(strict_types=1);

namespace App\Middleware;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

class JwtMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $authHeader = $request->getHeaderLine('Authorization');

        if (empty($authHeader) || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $this->unauthorized('Missing or invalid Authorization header');
        }

        $token = trim($matches[1]);

        try {
            $secret  = $_ENV['JWT_SECRET'] ?? 'medicare_secret_key_2026';
            $decoded = JWT::decode($token, new Key($secret, 'HS256'));
            $request = $request->withAttribute('jwt', (array) $decoded);
            return $handler->handle($request);
        } catch (\Exception $e) {
            return $this->unauthorized('Invalid or expired token');
        }
    }

    private function unauthorized(string $message): ResponseInterface
    {
        $response = new Response();
        $response->getBody()->write(json_encode(['error' => $message]));
        return $response
            ->withStatus(401)
            ->withHeader('Content-Type', 'application/json');
    }
}
