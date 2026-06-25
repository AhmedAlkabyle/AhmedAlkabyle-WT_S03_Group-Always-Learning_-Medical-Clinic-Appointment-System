<?php

declare(strict_types=1);

use App\Middleware\JwtMiddleware;
use App\helpers\Mailer;

// ─── GET /off-days  (admin) ─────────────────────────────────────────────────
$app->get('/off-days', function ($request, $response) use ($pdo) {
    $jwt = $request->getAttribute('jwt');
    if ($jwt['role'] !== 'admin') {
        $response->getBody()->write(json_encode(['error' => 'Forbidden']));
        return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
    }

    try {
        $stmt = $pdo->prepare('
            SELECT o.*, u.name AS doctor_name, d.specialization
            FROM off_day_requests o
            JOIN doctors d ON o.doctor_id = d.id
            JOIN users   u ON d.user_id   = u.id
            ORDER BY o.date DESC
        ');
        $stmt->execute();
        $response->getBody()->write(json_encode($stmt->fetchAll()));
        return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
    } catch (\Exception $e) {
        $response->getBody()->write(json_encode(['error' => 'Failed to fetch off day requests']));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
})->add(new JwtMiddleware());

// ─── GET /off-days/doctor/{id}  (doctor or admin) ───────────────────────────
$app->get('/off-days/doctor/{id}', function ($request, $response, $args) use ($pdo) {
    $jwt = $request->getAttribute('jwt');
    if ($jwt['role'] !== 'doctor' && $jwt['role'] !== 'admin') {
        $response->getBody()->write(json_encode(['error' => 'Forbidden']));
        return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
    }

    try {
        $stmt = $pdo->prepare('SELECT * FROM off_day_requests WHERE doctor_id = ? ORDER BY date DESC');
        $stmt->execute([$args['id']]);
        $response->getBody()->write(json_encode($stmt->fetchAll()));
        return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
    } catch (\Exception $e) {
        $response->getBody()->write(json_encode(['error' => 'Failed to fetch off day requests']));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
})->add(new JwtMiddleware());

// ─── POST /off-days  (doctor) ───────────────────────────────────────────────
$app->post('/off-days', function ($request, $response) use ($pdo) {
    $jwt = $request->getAttribute('jwt');
    if ($jwt['role'] !== 'doctor') {
        $response->getBody()->write(json_encode(['error' => 'Forbidden']));
        return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
    }

    $data   = (array) $request->getParsedBody();
    $date   = trim($data['date']   ?? '');
    $reason = trim($data['reason'] ?? '');

    if ($date === '' || $reason === '') {
        $response->getBody()->write(json_encode(['error' => 'date and reason are required']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    try {
        $stmt = $pdo->prepare('SELECT id FROM doctors WHERE user_id = ?');
        $stmt->execute([$jwt['id']]);
        $doctor = $stmt->fetch();

        if (!$doctor) {
            $response->getBody()->write(json_encode(['error' => 'Doctor profile not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $stmt = $pdo->prepare('INSERT INTO off_day_requests (doctor_id, date, reason) VALUES (?, ?, ?)');
        $stmt->execute([$doctor['id'], $date, $reason]);
        $id = (int) $pdo->lastInsertId();

        $response->getBody()->write(json_encode(['message' => 'Off day request submitted', 'id' => $id]));
        return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
    } catch (\Exception $e) {
        $response->getBody()->write(json_encode(['error' => 'Failed to submit off day request']));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
})->add(new JwtMiddleware());

// ─── PUT /off-days/{id}  (admin) ────────────────────────────────────────────
$app->put('/off-days/{id}', function ($request, $response, $args) use ($pdo) {
    $jwt = $request->getAttribute('jwt');
    if ($jwt['role'] !== 'admin') {
        $response->getBody()->write(json_encode(['error' => 'Forbidden']));
        return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
    }

    $data   = (array) $request->getParsedBody();
    $status = $data['status'] ?? '';

    if (!in_array($status, ['approved', 'rejected'], true)) {
        $response->getBody()->write(json_encode(['error' => 'status must be approved or rejected']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    try {
        $stmt = $pdo->prepare('UPDATE off_day_requests SET status = ?, admin_remark = ? WHERE id = ?');
        $stmt->execute([$status, $data['admin_remark'] ?? null, $args['id']]);

        // Notify doctor of the decision
        try {
            $docStmt = $pdo->prepare('
                SELECT u.email, u.name, o.date, o.reason
                FROM off_day_requests o
                JOIN doctors d ON o.doctor_id = d.id
                JOIN users   u ON d.user_id   = u.id
                WHERE o.id = ?
            ');
            $docStmt->execute([$args['id']]);
            $offDay = $docStmt->fetch();
            if ($offDay) {
                Mailer::offDayDecision($offDay['email'], $offDay['name'], [
                    'date'   => $offDay['date'],
                    'reason' => $offDay['reason'],
                    'status' => $status,
                ]);
            }
        } catch (\Exception $mailErr) {
            error_log('Email send failed: ' . $mailErr->getMessage());
        }

        $response->getBody()->write(json_encode(['message' => 'Off day request updated']));
        return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
    } catch (\Exception $e) {
        $response->getBody()->write(json_encode(['error' => 'Failed to update off day request']));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
})->add(new JwtMiddleware());

// ─── DELETE /off-days/{id}  (doctor) ────────────────────────────────────────
$app->delete('/off-days/{id}', function ($request, $response, $args) use ($pdo) {
    $jwt = $request->getAttribute('jwt');
    if ($jwt['role'] !== 'doctor') {
        $response->getBody()->write(json_encode(['error' => 'Forbidden']));
        return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM off_day_requests WHERE id = ? AND status = 'pending'");
        $stmt->execute([$args['id']]);

        if ($stmt->rowCount() === 0) {
            $response->getBody()->write(json_encode(['error' => 'Request not found or already processed']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write(json_encode(['message' => 'Off day request cancelled']));
        return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
    } catch (\Exception $e) {
        $response->getBody()->write(json_encode(['error' => 'Failed to cancel off day request']));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
})->add(new JwtMiddleware());
