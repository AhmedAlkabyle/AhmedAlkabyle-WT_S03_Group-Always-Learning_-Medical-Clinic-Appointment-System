<?php

declare(strict_types=1);

use App\Middleware\JwtMiddleware;

// ─── GET /doctors  (public) ─────────────────────────────────────────────────
$app->get('/doctors', function ($request, $response) use ($pdo) {
    try {
        $stmt = $pdo->prepare('
            SELECT d.id, d.user_id, d.specialization, d.availability, d.license_number, d.created_at,
                   u.name, u.email, u.phone, u.is_active
            FROM doctors d
            JOIN users u ON d.user_id = u.id
            ORDER BY d.id
        ');
        $stmt->execute();
        $response->getBody()->write(json_encode($stmt->fetchAll()));
        return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
    } catch (\Exception $e) {
        $response->getBody()->write(json_encode(['error' => 'Failed to fetch doctors']));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
});

// ─── GET /doctors/{id}/availability  (any authenticated) ────────────────────
// Returns working hours, off-day status, and booked time ranges for a given date.
// Query params: date=YYYY-MM-DD (required)
$app->get('/doctors/{id}/availability', function ($request, $response, $args) use ($pdo) {
    $params = $request->getQueryParams();
    $date   = $params['date'] ?? '';

    if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $response->getBody()->write(json_encode(['error' => 'date query param is required (YYYY-MM-DD)']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    // 1. Confirm doctor exists (base columns always present)
    $stmt = $pdo->prepare('SELECT id FROM doctors WHERE id = ?');
    $stmt->execute([$args['id']]);
    if (!$stmt->fetch()) {
        $response->getBody()->write(json_encode(['error' => 'Doctor not found']));
        return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
    }

    // 2. Work schedule — columns added by ALTER TABLE; fall back to defaults if missing
    $hasSchedule  = false;
    $workStart    = '09:00';
    $workEnd      = '17:00';
    $worksThisDay = true;
    try {
        $s = $pdo->prepare('SELECT work_days, work_start, work_end FROM doctors WHERE id = ?');
        $s->execute([$args['id']]);
        $sched = $s->fetch();
        if ($sched) {
            $hasSchedule = !empty($sched['work_start']) && !empty($sched['work_end']);
            if ($hasSchedule) {
                $workStart = substr($sched['work_start'], 0, 5);
                $workEnd   = substr($sched['work_end'],   0, 5);
            }
            if (!empty($sched['work_days'])) {
                $allowed      = array_map('intval', explode(',', $sched['work_days']));
                $dow          = (int) date('N', strtotime($date));
                $worksThisDay = in_array($dow, $allowed, true);
            }
        }
    } catch (\Exception $ignored) {
        // work_days/work_start/work_end columns not yet added — use defaults
    }

    // 3. Off-day check — table may not exist on older installs
    $isOffDay = false;
    try {
        $offStmt = $pdo->prepare("SELECT id FROM off_day_requests WHERE doctor_id = ? AND date = ? AND status = 'approved'");
        $offStmt->execute([$args['id'], $date]);
        $isOffDay = (bool) $offStmt->fetch();
    } catch (\Exception $ignored) {}

    // 4. Booked slots — try with duration_minutes; fall back to 30 min if column missing
    $booked = [];
    try {
        $apptStmt = $pdo->prepare("
            SELECT time, duration_minutes
            FROM appointments
            WHERE doctor_id = ? AND date = ? AND status != 'cancelled'
            ORDER BY time
        ");
        $apptStmt->execute([$args['id'], $date]);
        foreach ($apptStmt->fetchAll() as $appt) {
            $t = substr($appt['time'], 0, 5);
            [$h, $m] = array_map('intval', explode(':', $t));
            $startMin = $h * 60 + $m;
            $endMin   = $startMin + (int) ($appt['duration_minutes'] ?? 30);
            $booked[] = ['start' => $t, 'end' => sprintf('%02d:%02d', intdiv($endMin, 60), $endMin % 60)];
        }
    } catch (\Exception $ignored) {
        // duration_minutes column not yet added — fall back without it
        try {
            $apptStmt = $pdo->prepare("
                SELECT time FROM appointments
                WHERE doctor_id = ? AND date = ? AND status != 'cancelled'
                ORDER BY time
            ");
            $apptStmt->execute([$args['id'], $date]);
            foreach ($apptStmt->fetchAll() as $appt) {
                $t = substr($appt['time'], 0, 5);
                [$h, $m] = array_map('intval', explode(':', $t));
                $startMin = $h * 60 + $m;
                $endMin   = $startMin + 30;
                $booked[] = ['start' => $t, 'end' => sprintf('%02d:%02d', intdiv($endMin, 60), $endMin % 60)];
            }
        } catch (\Exception $ignored2) {}
    }

    $response->getBody()->write(json_encode([
        'is_off_day'     => $isOffDay,
        'works_this_day' => $worksThisDay,
        'has_schedule'   => $hasSchedule,
        'work_start'     => $workStart,
        'work_end'       => $workEnd,
        'booked'         => $booked,
    ]));
    return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
})->add(new JwtMiddleware());

// ─── GET /doctors/{id}  (public) ────────────────────────────────────────────
$app->get('/doctors/{id}', function ($request, $response, $args) use ($pdo) {
    try {
        $stmt = $pdo->prepare('
            SELECT d.id, d.user_id, d.specialization, d.availability, d.license_number, d.created_at,
                   u.name, u.email, u.phone, u.is_active
            FROM doctors d
            JOIN users u ON d.user_id = u.id
            WHERE d.id = ?
        ');
        $stmt->execute([$args['id']]);
        $doctor = $stmt->fetch();

        if (!$doctor) {
            $response->getBody()->write(json_encode(['error' => 'Doctor not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write(json_encode($doctor));
        return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
    } catch (\Exception $e) {
        $response->getBody()->write(json_encode(['error' => 'Failed to fetch doctor']));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
});

// ─── POST /doctors  (admin) ─────────────────────────────────────────────────
$app->post('/doctors', function ($request, $response) use ($pdo) {
    $jwt = $request->getAttribute('jwt');
    if ($jwt['role'] !== 'admin') {
        $response->getBody()->write(json_encode(['error' => 'Forbidden']));
        return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
    }

    $data           = (array) $request->getParsedBody();
    $userId         = $data['user_id'] ?? null;
    $specialization = trim($data['specialization'] ?? '');

    if (!$userId || $specialization === '') {
        $response->getBody()->write(json_encode(['error' => 'user_id and specialization are required']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    try {
        $stmt = $pdo->prepare('
            INSERT INTO doctors (user_id, specialization, availability, license_number)
            VALUES (?, ?, ?, ?)
        ');
        $stmt->execute([
            (int) $userId,
            $specialization,
            $data['availability'] ?? null,
            $data['license_number'] ?? null,
        ]);
        $id = (int) $pdo->lastInsertId();

        $response->getBody()->write(json_encode(['message' => 'Doctor created successfully', 'id' => $id]));
        return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
    } catch (\Exception $e) {
        $response->getBody()->write(json_encode(['error' => 'Failed to create doctor']));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
})->add(new JwtMiddleware());

// ─── PUT /doctors/{id}  (admin) ─────────────────────────────────────────────
$app->put('/doctors/{id}', function ($request, $response, $args) use ($pdo) {
    $jwt = $request->getAttribute('jwt');
    if ($jwt['role'] !== 'admin') {
        $response->getBody()->write(json_encode(['error' => 'Forbidden']));
        return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
    }

    $data = (array) $request->getParsedBody();

    try {
        $stmt = $pdo->prepare('SELECT id FROM doctors WHERE id = ?');
        $stmt->execute([$args['id']]);
        if (!$stmt->fetch()) {
            $response->getBody()->write(json_encode(['error' => 'Doctor not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $stmt = $pdo->prepare('
            UPDATE doctors
            SET specialization = COALESCE(?, specialization),
                availability   = COALESCE(?, availability)
            WHERE id = ?
        ');
        $stmt->execute([
            $data['specialization'] ?? null,
            $data['availability'] ?? null,
            $args['id'],
        ]);

        $response->getBody()->write(json_encode(['message' => 'Doctor updated successfully']));
        return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
    } catch (\Exception $e) {
        $response->getBody()->write(json_encode(['error' => 'Failed to update doctor']));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
})->add(new JwtMiddleware());

// ─── DELETE /doctors/{id}  (admin) ──────────────────────────────────────────
$app->delete('/doctors/{id}', function ($request, $response, $args) use ($pdo) {
    $jwt = $request->getAttribute('jwt');
    if ($jwt['role'] !== 'admin') {
        $response->getBody()->write(json_encode(['error' => 'Forbidden']));
        return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
    }

    try {
        $stmt = $pdo->prepare('DELETE FROM doctors WHERE id = ?');
        $stmt->execute([$args['id']]);

        $response->getBody()->write(json_encode(['message' => 'Doctor deleted successfully']));
        return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
    } catch (\Exception $e) {
        $response->getBody()->write(json_encode(['error' => 'Failed to delete doctor']));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
})->add(new JwtMiddleware());
