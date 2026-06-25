<?php

declare(strict_types=1);

use App\Middleware\JwtMiddleware;
use App\helpers\Mailer;

// NOTE: Specific sub-paths (/patient/{id}, /doctor/{id}, /{id}/complete, /{id}/comment)
// are registered before the generic /{id} route to ensure correct matching.

// ─── GET /appointments  (admin) ─────────────────────────────────────────────
$app->get('/appointments', function ($request, $response) use ($pdo) {
    $jwt = $request->getAttribute('jwt');
    if ($jwt['role'] !== 'admin') {
        $response->getBody()->write(json_encode(['error' => 'Forbidden']));
        return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
    }

    try {
        $stmt = $pdo->prepare('
            SELECT a.*,
                   p.name  AS patient_name,  p.email AS patient_email,
                   u.name  AS doctor_name,   d.specialization
            FROM appointments a
            JOIN users   p ON a.patient_id = p.id
            JOIN doctors d ON a.doctor_id  = d.id
            JOIN users   u ON d.user_id    = u.id
            ORDER BY a.date DESC, a.time DESC
        ');
        $stmt->execute();
        $response->getBody()->write(json_encode($stmt->fetchAll()));
        return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
    } catch (\Exception $e) {
        $response->getBody()->write(json_encode(['error' => 'Failed to fetch appointments']));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
})->add(new JwtMiddleware());

// ─── GET /appointments/patient/{id}  (patient or admin) ─────────────────────
$app->get('/appointments/patient/{id}', function ($request, $response, $args) use ($pdo) {
    $jwt = $request->getAttribute('jwt');
    if ($jwt['role'] !== 'patient' && $jwt['role'] !== 'admin') {
        $response->getBody()->write(json_encode(['error' => 'Forbidden']));
        return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
    }

    try {
        $stmt = $pdo->prepare('
            SELECT a.*,
                   u.name  AS doctor_name, d.specialization
            FROM appointments a
            JOIN doctors d ON a.doctor_id = d.id
            JOIN users   u ON d.user_id   = u.id
            WHERE a.patient_id = ?
            ORDER BY a.date DESC, a.time DESC
        ');
        $stmt->execute([$args['id']]);
        $response->getBody()->write(json_encode($stmt->fetchAll()));
        return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
    } catch (\Exception $e) {
        $response->getBody()->write(json_encode(['error' => 'Failed to fetch appointments']));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
})->add(new JwtMiddleware());

// ─── GET /appointments/doctor/{id}  (doctor or admin) ───────────────────────
$app->get('/appointments/doctor/{id}', function ($request, $response, $args) use ($pdo) {
    $jwt = $request->getAttribute('jwt');
    if ($jwt['role'] !== 'doctor' && $jwt['role'] !== 'admin') {
        $response->getBody()->write(json_encode(['error' => 'Forbidden']));
        return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
    }

    try {
        $stmt = $pdo->prepare('
            SELECT a.*,
                   p.name  AS patient_name, p.email AS patient_email, p.phone AS patient_phone
            FROM appointments a
            JOIN users p ON a.patient_id = p.id
            WHERE a.doctor_id = ?
            ORDER BY a.date DESC, a.time DESC
        ');
        $stmt->execute([$args['id']]);
        $response->getBody()->write(json_encode($stmt->fetchAll()));
        return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
    } catch (\Exception $e) {
        $response->getBody()->write(json_encode(['error' => 'Failed to fetch appointments']));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
})->add(new JwtMiddleware());

// ─── GET /appointments/{id}  (any authenticated) ────────────────────────────
$app->get('/appointments/{id}', function ($request, $response, $args) use ($pdo) {
    try {
        $stmt = $pdo->prepare('
            SELECT a.*,
                   p.name  AS patient_name, p.email AS patient_email,
                   u.name  AS doctor_name,  d.specialization
            FROM appointments a
            JOIN users   p ON a.patient_id = p.id
            JOIN doctors d ON a.doctor_id  = d.id
            JOIN users   u ON d.user_id    = u.id
            WHERE a.id = ?
        ');
        $stmt->execute([$args['id']]);
        $appointment = $stmt->fetch();

        if (!$appointment) {
            $response->getBody()->write(json_encode(['error' => 'Appointment not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write(json_encode($appointment));
        return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
    } catch (\Exception $e) {
        $response->getBody()->write(json_encode(['error' => 'Failed to fetch appointment']));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
})->add(new JwtMiddleware());

// ─── POST /appointments  (patient) ──────────────────────────────────────────
$app->post('/appointments', function ($request, $response) use ($pdo) {
    $jwt = $request->getAttribute('jwt');
    if ($jwt['role'] !== 'patient') {
        $response->getBody()->write(json_encode(['error' => 'Forbidden']));
        return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
    }

    $data            = (array) $request->getParsedBody();
    $doctorId        = $data['doctor_id']        ?? null;
    $date            = trim($data['date']         ?? '');
    $time            = trim($data['time']         ?? '');
    $appointmentType = trim($data['appointment_type'] ?? '');

    if (!$doctorId || $date === '' || $time === '') {
        $response->getBody()->write(json_encode(['error' => 'doctor_id, date, and time are required']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    // Map type → duration (minutes)
    $typeDurationMap = [
        'Quick Consultation / Follow-up' => 15,
        'General Checkup'                => 30,
        'Detailed Examination'           => 45,
        'New Patient / Full Assessment'  => 60,
    ];
    $duration = $typeDurationMap[$appointmentType] ?? 30;

    // Helper: "HH:MM" or "HH:MM:SS" → minutes since midnight
    $timeToMin = function (string $t): int {
        [$h, $m] = array_map('intval', explode(':', $t));
        return $h * 60 + $m;
    };

    try {
        // Confirm doctor exists
        $stmt = $pdo->prepare('SELECT id FROM doctors WHERE id = ?');
        $stmt->execute([(int) $doctorId]);
        if (!$stmt->fetch()) {
            $response->getBody()->write(json_encode(['error' => 'Doctor not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        // ── Schedule validation (skip gracefully if columns missing) ─────────
        try {
            $schedStmt = $pdo->prepare('SELECT work_days, work_start, work_end FROM doctors WHERE id = ?');
            $schedStmt->execute([(int) $doctorId]);
            $sched = $schedStmt->fetch();
            if ($sched) {
                // Off-day check
                try {
                    $offStmt = $pdo->prepare("SELECT id FROM off_day_requests WHERE doctor_id = ? AND date = ? AND status = 'approved'");
                    $offStmt->execute([(int) $doctorId, $date]);
                    if ($offStmt->fetch()) {
                        $response->getBody()->write(json_encode(['error' => 'Doctor has an approved day off on this date. Please choose another date.']));
                        return $response->withStatus(422)->withHeader('Content-Type', 'application/json');
                    }
                } catch (\Exception $ignored) {}

                // Day-of-week check
                if (!empty($sched['work_days'])) {
                    $allowed  = array_map('intval', explode(',', $sched['work_days']));
                    $dow      = (int) date('N', strtotime($date));
                    if (!in_array($dow, $allowed, true)) {
                        $dayNames = ['', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                        $response->getBody()->write(json_encode([
                            'error' => 'Doctor is not available on ' . ($dayNames[$dow] ?? 'this day') . '. Please choose a working day.',
                        ]));
                        return $response->withStatus(422)->withHeader('Content-Type', 'application/json');
                    }
                }

                // Working-hours check
                if (!empty($sched['work_start']) && !empty($sched['work_end'])) {
                    $newStart  = $timeToMin($time);
                    $newEnd    = $newStart + $duration;
                    $workStart = $timeToMin(substr($sched['work_start'], 0, 5));
                    $workEnd   = $timeToMin(substr($sched['work_end'],   0, 5));
                    if ($newStart < $workStart || $newEnd > $workEnd) {
                        $response->getBody()->write(json_encode(['error' => 'Doctor is not available at this time. Please choose a slot within working hours.']));
                        return $response->withStatus(422)->withHeader('Content-Type', 'application/json');
                    }
                }
            }
        } catch (\Exception $ignored) {
            // work_days/work_start/work_end columns not yet added — skip schedule validation
        }

        // ── Clash + 5-minute buffer check ────────────────────────────────────
        $buffer   = 5;
        $newStart = $timeToMin($time);
        $newEnd   = $newStart + $duration;
        try {
            $clashStmt = $pdo->prepare("
                SELECT time, duration_minutes FROM appointments
                WHERE doctor_id = ? AND date = ? AND status != 'cancelled'
            ");
            $clashStmt->execute([(int) $doctorId, $date]);
            foreach ($clashStmt->fetchAll() as $appt) {
                $exStart = $timeToMin(substr($appt['time'], 0, 5));
                $exEnd   = $exStart + (int) ($appt['duration_minutes'] ?? 30);
                if ($newStart < $exEnd + $buffer && $newEnd > $exStart - $buffer) {
                    $response->getBody()->write(json_encode(['error' => 'This time slot is not available. Please choose another time.']));
                    return $response->withStatus(422)->withHeader('Content-Type', 'application/json');
                }
            }
        } catch (\Exception $ignored) {
            // duration_minutes column not yet added — fall back to basic time clash
            try {
                $clashStmt = $pdo->prepare("
                    SELECT time FROM appointments
                    WHERE doctor_id = ? AND date = ? AND status != 'cancelled'
                ");
                $clashStmt->execute([(int) $doctorId, $date]);
                foreach ($clashStmt->fetchAll() as $appt) {
                    $exStart = $timeToMin(substr($appt['time'], 0, 5));
                    $exEnd   = $exStart + 30;
                    if ($newStart < $exEnd + $buffer && $newEnd > $exStart - $buffer) {
                        $response->getBody()->write(json_encode(['error' => 'This time slot is not available. Please choose another time.']));
                        return $response->withStatus(422)->withHeader('Content-Type', 'application/json');
                    }
                }
            } catch (\Exception $ignored2) {}
        }

        // ── Insert — try with new columns, fall back to base columns ─────────
        try {
            $stmt = $pdo->prepare('
                INSERT INTO appointments (patient_id, doctor_id, date, time, appointment_type, duration_minutes, notes, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, \'pending\')
            ');
            $stmt->execute([(int) $jwt['id'], (int) $doctorId, $date, $time, $appointmentType ?: null, $duration, $data['notes'] ?? null]);
        } catch (\Exception $ignored) {
            // appointment_type / duration_minutes columns not yet added — insert without them
            $stmt = $pdo->prepare('
                INSERT INTO appointments (patient_id, doctor_id, date, time, notes, status)
                VALUES (?, ?, ?, ?, ?, \'pending\')
            ');
            $stmt->execute([(int) $jwt['id'], (int) $doctorId, $date, $time, $data['notes'] ?? null]);
        }
        $id = (int) $pdo->lastInsertId();

        // Send booking confirmation email (fire-and-forget)
        try {
            $infoStmt = $pdo->prepare('
                SELECT p.email AS patient_email, p.name AS patient_name,
                       du.name AS doctor_name, d.specialization
                FROM users p, doctors d, users du
                WHERE p.id = ? AND d.id = ? AND du.id = d.user_id
            ');
            $infoStmt->execute([(int) $jwt['id'], (int) $doctorId]);
            $info = $infoStmt->fetch();
            if ($info) {
                Mailer::appointmentBooked($info['patient_email'], $info['patient_name'], [
                    'doctor_name'    => $info['doctor_name'],
                    'specialization' => $info['specialization'],
                    'date'           => $date,
                    'time'           => $time,
                ]);
            }
        } catch (\Exception $mailErr) {
            error_log('Email send failed: ' . $mailErr->getMessage());
        }

        $response->getBody()->write(json_encode(['message' => 'Appointment booked successfully', 'id' => $id]));
        return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
    } catch (\Exception $e) {
        $response->getBody()->write(json_encode(['error' => 'Failed to book appointment']));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
})->add(new JwtMiddleware());

// ─── PUT /appointments/{id}/complete  (doctor) ──────────────────────────────
$app->put('/appointments/{id}/complete', function ($request, $response, $args) use ($pdo) {
    $jwt = $request->getAttribute('jwt');
    if ($jwt['role'] !== 'doctor') {
        $response->getBody()->write(json_encode(['error' => 'Forbidden']));
        return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
    }

    try {
        $stmt = $pdo->prepare("UPDATE appointments SET status = 'completed' WHERE id = ?");
        $stmt->execute([$args['id']]);

        $response->getBody()->write(json_encode(['message' => 'Appointment marked as completed']));
        return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
    } catch (\Exception $e) {
        $response->getBody()->write(json_encode(['error' => 'Failed to complete appointment']));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
})->add(new JwtMiddleware());

// ─── PUT /appointments/{id}/comment  (doctor) ───────────────────────────────
$app->put('/appointments/{id}/comment', function ($request, $response, $args) use ($pdo) {
    $jwt = $request->getAttribute('jwt');
    if ($jwt['role'] !== 'doctor') {
        $response->getBody()->write(json_encode(['error' => 'Forbidden']));
        return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
    }

    $data    = (array) $request->getParsedBody();
    $comment = $data['doctor_comment'] ?? '';

    try {
        $stmt = $pdo->prepare('UPDATE appointments SET doctor_comment = ? WHERE id = ?');
        $stmt->execute([$comment, $args['id']]);

        $response->getBody()->write(json_encode(['message' => 'Comment updated successfully']));
        return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
    } catch (\Exception $e) {
        $response->getBody()->write(json_encode(['error' => 'Failed to update comment']));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
})->add(new JwtMiddleware());

// ─── PUT /appointments/{id}  (admin or doctor) ──────────────────────────────
$app->put('/appointments/{id}', function ($request, $response, $args) use ($pdo) {
    $jwt = $request->getAttribute('jwt');
    if ($jwt['role'] !== 'admin' && $jwt['role'] !== 'doctor') {
        $response->getBody()->write(json_encode(['error' => 'Forbidden']));
        return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
    }

    $data   = (array) $request->getParsedBody();
    $status = $data['status'] ?? '';

    if (!in_array($status, ['pending', 'confirmed', 'completed', 'cancelled'], true)) {
        $response->getBody()->write(json_encode(['error' => 'status must be pending, confirmed, completed, or cancelled']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    try {
        // Doctors can only update their own appointments
        if ($jwt['role'] === 'doctor') {
            $docStmt = $pdo->prepare('SELECT id FROM doctors WHERE user_id = ?');
            $docStmt->execute([$jwt['id']]);
            $doc = $docStmt->fetch();
            if (!$doc) {
                $response->getBody()->write(json_encode(['error' => 'Doctor profile not found']));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            }
            $checkStmt = $pdo->prepare('SELECT id FROM appointments WHERE id = ? AND doctor_id = ?');
            $checkStmt->execute([$args['id'], $doc['id']]);
            if (!$checkStmt->fetch()) {
                $response->getBody()->write(json_encode(['error' => 'Appointment not found or does not belong to you']));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            }
        }

        $stmt = $pdo->prepare('UPDATE appointments SET status = ? WHERE id = ?');
        $stmt->execute([$status, $args['id']]);

        // Send email when admin confirms or cancels
        if (in_array($status, ['confirmed', 'cancelled'], true)) {
            try {
                $apptStmt = $pdo->prepare('
                    SELECT p.email AS patient_email, p.name AS patient_name,
                           u.name AS doctor_name, a.date, a.time
                    FROM appointments a
                    JOIN users   p ON a.patient_id = p.id
                    JOIN doctors d ON a.doctor_id  = d.id
                    JOIN users   u ON d.user_id    = u.id
                    WHERE a.id = ?
                ');
                $apptStmt->execute([$args['id']]);
                $appt = $apptStmt->fetch();
                if ($appt) {
                    $apptData = [
                        'doctor_name' => $appt['doctor_name'],
                        'date'        => $appt['date'],
                        'time'        => $appt['time'],
                    ];
                    if ($status === 'confirmed') {
                        Mailer::appointmentConfirmed($appt['patient_email'], $appt['patient_name'], $apptData);
                    } else {
                        Mailer::appointmentCancelled($appt['patient_email'], $appt['patient_name'], $apptData);
                    }
                }
            } catch (\Exception $mailErr) {
                error_log('Email send failed: ' . $mailErr->getMessage());
            }
        }

        $response->getBody()->write(json_encode(['message' => 'Appointment updated successfully']));
        return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
    } catch (\Exception $e) {
        $response->getBody()->write(json_encode(['error' => 'Failed to update appointment']));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
})->add(new JwtMiddleware());

// ─── DELETE /appointments/{id}  (any authenticated) ─────────────────────────
$app->delete('/appointments/{id}', function ($request, $response, $args) use ($pdo) {
    try {
        $stmt = $pdo->prepare('DELETE FROM appointments WHERE id = ?');
        $stmt->execute([$args['id']]);

        $response->getBody()->write(json_encode(['message' => 'Appointment cancelled successfully']));
        return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
    } catch (\Exception $e) {
        $response->getBody()->write(json_encode(['error' => 'Failed to cancel appointment']));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
})->add(new JwtMiddleware());
