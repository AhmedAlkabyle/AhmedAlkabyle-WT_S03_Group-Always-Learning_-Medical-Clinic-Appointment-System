<?php

declare(strict_types=1);

use Firebase\JWT\JWT;

// ─── POST /auth/register ────────────────────────────────────────────────────
$app->post('/auth/register', function ($request, $response) use ($pdo) {
    $data = (array) $request->getParsedBody();

    $name     = trim($data['name'] ?? '');
    $email    = trim($data['email'] ?? '');
    $password = $data['password'] ?? '';
    $role     = $data['role'] ?? 'patient';
    $phone    = $data['phone'] ?? null;

    if ($name === '' || $email === '' || $password === '' || $role === '') {
        $response->getBody()->write(json_encode(['error' => 'name, email, password, and role are required']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    if (!in_array($role, ['patient', 'doctor', 'admin'], true)) {
        $response->getBody()->write(json_encode(['error' => 'role must be patient, doctor, or admin']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response->getBody()->write(json_encode(['error' => 'Invalid email address']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    try {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $response->getBody()->write(json_encode(['error' => 'Email is already registered']));
            return $response->withStatus(422)->withHeader('Content-Type', 'application/json');
        }

        $hashed = password_hash($password, PASSWORD_BCRYPT);
        $stmt   = $pdo->prepare('INSERT INTO users (name, email, password, role, phone) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$name, $email, $hashed, $role, $phone]);
        $id = (int) $pdo->lastInsertId();

        $response->getBody()->write(json_encode([
            'message' => 'User registered successfully',
            'user'    => ['id' => $id, 'name' => $name, 'email' => $email, 'role' => $role],
        ]));
        return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
    } catch (\Exception $e) {
        $response->getBody()->write(json_encode(['error' => 'Registration failed']));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
});

// ─── POST /auth/login ───────────────────────────────────────────────────────
$app->post('/auth/login', function ($request, $response) use ($pdo) {
    $data = (array) $request->getParsedBody();

    $email    = trim($data['email'] ?? '');
    $password = $data['password'] ?? '';

    if ($email === '' || $password === '') {
        $response->getBody()->write(json_encode(['error' => 'email and password are required']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    try {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            $response->getBody()->write(json_encode(['error' => 'Invalid credentials']));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        if (!(int) $user['is_active']) {
            $response->getBody()->write(json_encode(['error' => 'Account has been deactivated']));
            return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
        }

        $secret = $_ENV['JWT_SECRET'] ?? 'medicare_secret_key_2026';
        $expiry = (int) ($_ENV['JWT_EXPIRY'] ?? 86400);
        $now    = time();

        $payload = [
            'id'    => (int) $user['id'],
            'name'  => $user['name'],
            'email' => $user['email'],
            'role'  => $user['role'],
            'iat'   => $now,
            'exp'   => $now + $expiry,
        ];

        $token = JWT::encode($payload, $secret, 'HS256');

        $response->getBody()->write(json_encode([
            'token' => $token,
            'user'  => [
                'id'    => (int) $user['id'],
                'name'  => $user['name'],
                'email' => $user['email'],
                'role'  => $user['role'],
            ],
        ]));
        return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
    } catch (\Exception $e) {
        $response->getBody()->write(json_encode(['error' => 'Login failed']));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
});
