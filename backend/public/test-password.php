<?php
$hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
$password = 'password';
if (password_verify($password, $hash)) {
    echo "PASSWORD HASH WORKS OK";
} else {
    echo "HASH DOES NOT MATCH - need to regenerate";
    $newHash = password_hash('password', PASSWORD_BCRYPT);
    echo " | New hash: " . $newHash;
}
