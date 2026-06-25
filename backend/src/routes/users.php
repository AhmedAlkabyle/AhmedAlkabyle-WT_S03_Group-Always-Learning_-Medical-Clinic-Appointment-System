<?php

declare(strict_types=1);

use App\Middleware\JwtMiddleware;

// ─── GET /me  (any authenticated) ───────────────────────────────────────────
$app->get('/me', function ($request, $response) use ($pdo) {
    $jwt = $request->getAttribute('jwt');

    try {
        $stmt = $pdo->prepare('
            SELECT id, name, email, role, phone, address, position, is_active, created_at
            FROM users WHERE id = ?
        ');
        $stmt->execute([$jwt['id']]);
        $user = $stmt->fetch();

        if (!$user) {
            $response->getBody()->write(json_encode(['error' => 'User not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        if ($user['role'] === 'doctor') {
            $stmt = $pdo->prepare('SELECT id, specialization, availability, license_number FROM doctors WHERE user_id = ?');
            $stmt->execute([$user['id']]);
            $doctor = $stmt->fetch();
            if ($doctor) {
                $user['doctor_profile'] = $doctor;
            }
        }

        $response->getBody()->write(json_encode($user));
        return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
    } catch (\Exception $e) {
        $response->getBody()->write(json_encode(['error' => 'Failed to fetch profile']));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
})->add(new JwtMiddleware());

// ─── PUT /me  (any authenticated) ───────────────────────────────────────────
$app->put('/me', function ($request, $response) use ($pdo) {
    $jwt  = $request->getAttribute('jwt');
    $data = (array) $request->getParsedBody();

    try {
        $stmt = $pdo->prepare('
            UPDATE users SET name = ?, email = ?, phone = ?, address = ?, position = ?
            WHERE id = ?
        ');
        $stmt->execute([
            $data['name']     ?? null,
            $data['email']    ?? null,
            $data['phone']    ?? null,
            $data['address']  ?? null,
            $data['position'] ?? null,
            $jwt['id'],
        ]);

        // Also update doctor profile fields if provided
        if (isset($data['specialization']) || isset($data['availability'])) {
            $stmt = $pdo->prepare('
                UPDATE doctors
                SET specialization = COALESCE(?, specialization),
                    availability   = COALESCE(?, availability)
                WHERE user_id = ?
            ');
            $stmt->execute([
                $data['specialization'] ?? null,
                $data['availability']   ?? null,
                $jwt['id'],
            ]);
        }

        $response->getBody()->write(json_encode(['message' => 'Profile updated successfully']));
        return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
    } catch (\Exception $e) {
        $response->getBody()->write(json_encode(['error' => 'Failed to update profile']));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
})->add(new JwtMiddleware());

// ─── PUT /me/password  (any authenticated) ──────────────────────────────────
$app->put('/me/password', function ($request, $response) use ($pdo) {
    $jwt  = $request->getAttribute('jwt');
    $data = (array) $request->getParsedBody();

    $currentPassword = $data['current_password'] ?? '';
    $newPassword     = $data['new_password']     ?? '';

    if ($currentPassword === '' || $newPassword === '') {
        $response->getBody()->write(json_encode(['error' => 'current_password and new_password are required']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    try {
        $stmt = $pdo->prepare('SELECT password FROM users WHERE id = ?');
        $stmt->execute([$jwt['id']]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($currentPassword, $user['password'])) {
            $response->getBody()->write(json_encode(['error' => 'Current password is incorrect']));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        if ($newPassword === $currentPassword) {
            $response->getBody()->write(json_encode(['error' => 'New password must be different from your current password']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $hashed = password_hash($newPassword, PASSWORD_BCRYPT);
        $stmt   = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
        $stmt->execute([$hashed, $jwt['id']]);

        $response->getBody()->write(json_encode(['message' => 'Password updated successfully']));
        return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
    } catch (\Exception $e) {
        $response->getBody()->write(json_encode(['error' => 'Failed to update password']));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
})->add(new JwtMiddleware());

// ─── GET /users  (admin) ────────────────────────────────────────────────────
$app->get('/users', function ($request, $response) use ($pdo) {
    $jwt = $request->getAttribute('jwt');
    if ($jwt['role'] !== 'admin') {
        $response->getBody()->write(json_encode(['error' => 'Forbidden']));
        return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
    }

    try {
        $stmt = $pdo->prepare('
            SELECT id, name, email, role, phone, address, position, is_active, created_at
            FROM users ORDER BY id
        ');
        $stmt->execute();
        $response->getBody()->write(json_encode($stmt->fetchAll()));
        return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
    } catch (\Exception $e) {
        $response->getBody()->write(json_encode(['error' => 'Failed to fetch users']));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
})->add(new JwtMiddleware());

// ─── GET /users/{id}  (admin) ───────────────────────────────────────────────
$app->get('/users/{id}', function ($request, $response, $args) use ($pdo) {
    $jwt = $request->getAttribute('jwt');
    if ($jwt['role'] !== 'admin') {
        $response->getBody()->write(json_encode(['error' => 'Forbidden']));
        return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
    }

    try {
        $stmt = $pdo->prepare('
            SELECT id, name, email, role, phone, address, position, is_active, created_at
            FROM users WHERE id = ?
        ');
        $stmt->execute([$args['id']]);
        $user = $stmt->fetch();

        if (!$user) {
            $response->getBody()->write(json_encode(['error' => 'User not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write(json_encode($user));
        return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
    } catch (\Exception $e) {
        $response->getBody()->write(json_encode(['error' => 'Failed to fetch user']));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
})->add(new JwtMiddleware());

// ─── PUT /users/{id}  (admin) ───────────────────────────────────────────────
$app->put('/users/{id}', function ($request, $response, $args) use ($pdo) {
    $jwt = $request->getAttribute('jwt');
    if ($jwt['role'] !== 'admin') {
        $response->getBody()->write(json_encode(['error' => 'Forbidden']));
        return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
    }

    $data = (array) $request->getParsedBody();

    try {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE id = ?');
        $stmt->execute([$args['id']]);
        if (!$stmt->fetch()) {
            $response->getBody()->write(json_encode(['error' => 'User not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $stmt = $pdo->prepare('
            UPDATE users SET name = ?, email = ?, phone = ?, address = ?, position = ?
            WHERE id = ?
        ');
        $stmt->execute([
            $data['name']     ?? null,
            $data['email']    ?? null,
            $data['phone']    ?? null,
            $data['address']  ?? null,
            $data['position'] ?? null,
            $args['id'],
        ]);

        $response->getBody()->write(json_encode(['message' => 'User updated successfully']));
        return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
    } catch (\Exception $e) {
        $response->getBody()->write(json_encode(['error' => 'Failed to update user']));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
})->add(new JwtMiddleware());

// ─── PUT /users/{id}/status  (admin) ────────────────────────────────────────
$app->put('/users/{id}/status', function ($request, $response, $args) use ($pdo) {
    $jwt = $request->getAttribute('jwt');
    if ($jwt['role'] !== 'admin') {
        $response->getBody()->write(json_encode(['error' => 'Forbidden']));
        return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
    }

    $data     = (array) $request->getParsedBody();
    $isActive = isset($data['is_active']) ? (int) $data['is_active'] : -1;

    if (!in_array($isActive, [0, 1], true)) {
        $response->getBody()->write(json_encode(['error' => 'is_active must be 0 or 1']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    try {
        $stmt = $pdo->prepare('UPDATE users SET is_active = ? WHERE id = ?');
        $stmt->execute([$isActive, $args['id']]);

        $response->getBody()->write(json_encode(['message' => 'User status updated']));
        return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
    } catch (\Exception $e) {
        $response->getBody()->write(json_encode(['error' => 'Failed to update user status']));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
})->add(new JwtMiddleware());

// ─── DELETE /users/{id}  (admin) ────────────────────────────────────────────
$app->delete('/users/{id}', function ($request, $response, $args) use ($pdo) {
    $jwt = $request->getAttribute('jwt');
    if ($jwt['role'] !== 'admin') {
        $response->getBody()->write(json_encode(['error' => 'Forbidden']));
        return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
    }

    try {
        $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$args['id']]);

        $response->getBody()->write(json_encode(['message' => 'User deleted successfully']));
        return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
    } catch (\Exception $e) {
        $response->getBody()->write(json_encode(['error' => 'Failed to delete user']));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
})->add(new JwtMiddleware());

// ─── POST /users/{id}/reset-password  (admin) ───────────────────────────────
$app->post('/users/{id}/reset-password', function ($request, $response, $args) use ($pdo) {
    $jwt = $request->getAttribute('jwt');
    if ($jwt['role'] !== 'admin') {
        $response->getBody()->write(json_encode(['error' => 'Forbidden']));
        return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
    }

    $defaultPassword = 'ILoveMediCareClinic';

    try {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE id = ?');
        $stmt->execute([$args['id']]);
        if (!$stmt->fetch()) {
            $response->getBody()->write(json_encode(['error' => 'User not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $hashed = password_hash($defaultPassword, PASSWORD_BCRYPT);
        $stmt   = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
        $stmt->execute([$hashed, $args['id']]);

        $response->getBody()->write(json_encode([
            'message'          => 'Password reset to default',
            'default_password' => $defaultPassword,
        ]));
        return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
    } catch (\Exception $e) {
        $response->getBody()->write(json_encode(['error' => 'Failed to reset password']));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
})->add(new JwtMiddleware());
