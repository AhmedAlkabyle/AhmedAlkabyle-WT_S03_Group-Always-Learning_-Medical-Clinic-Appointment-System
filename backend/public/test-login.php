<?php
require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

try {
    $pdo = new PDO(
        'mysql:host=' . $_ENV['DB_HOST'] . ';dbname=' . $_ENV['DB_NAME'],
        $_ENV['DB_USER'],
        $_ENV['DB_PASS']
    );
    echo "DB Connected OK\n";

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute(['admin@medicare.com']);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        echo "User found: " . $user['name'] . "\n";
        echo "Role: " . $user['role'] . "\n";
        echo "Hash in DB: " . $user['password'] . "\n";

        $testPassword = 'password';
        if (password_verify($testPassword, $user['password'])) {
            echo "PASSWORD VERIFY: SUCCESS\n";
        } else {
            echo "PASSWORD VERIFY: FAILED\n";
            echo "Generating new hash...\n";
            echo password_hash($testPassword, PASSWORD_BCRYPT) . "\n";
        }
    } else {
        echo "User NOT found in database\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
