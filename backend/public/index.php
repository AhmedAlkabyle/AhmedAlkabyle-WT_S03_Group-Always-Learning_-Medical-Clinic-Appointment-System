<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use Slim\Factory\AppFactory;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Bootstrap PDO connection
$pdo = require __DIR__ . '/../src/config/database.php';

// Create Slim app
$app = AppFactory::create();

// Parse JSON and form-encoded bodies
$app->addBodyParsingMiddleware();

// Routing middleware
$app->addRoutingMiddleware();

// CORS middleware — added last so it runs outermost (first on request, last on response)
$app->add(function ($request, $handler) {
    // Short-circuit OPTIONS preflight immediately
    if ($request->getMethod() === 'OPTIONS') {
        $response = new \Slim\Psr7\Response();
        return $response
            ->withHeader('Access-Control-Allow-Origin', 'http://localhost:5173')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
            ->withStatus(200);
    }

    $response = $handler->handle($request);

    return $response
        ->withHeader('Access-Control-Allow-Origin', 'http://localhost:5173')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
        ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization');
});

// Error middleware — catches all exceptions and returns JSON
$errorMiddleware = $app->addErrorMiddleware(true, true, true);
$errorMiddleware->setDefaultErrorHandler(function ($request, \Throwable $exception, bool $displayErrorDetails) use ($app) {
    $payload = ['error' => $displayErrorDetails ? $exception->getMessage() : 'Internal server error'];
    $response = $app->getResponseFactory()->createResponse();
    $response->getBody()->write(json_encode($payload));
    return $response
        ->withStatus(500)
        ->withHeader('Content-Type', 'application/json');
});

// Register all route groups
require __DIR__ . '/../src/routes/auth.php';
require __DIR__ . '/../src/routes/users.php';
require __DIR__ . '/../src/routes/doctors.php';
require __DIR__ . '/../src/routes/appointments.php';
require __DIR__ . '/../src/routes/offdays.php';

$app->run();
