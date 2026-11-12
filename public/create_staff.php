<?php

require_once __DIR__ . '/../config/database.php';

$name = 'Staff';
$email = 'staff@inventory.test';
$password = 'staff12345';
$role = 'staff';

$check = $pdo->prepare(
    "SELECT id FROM users WHERE email = :email"
);
$check->execute(['email' => $email]);

if ($check->fetch()) {
    exit('Akun Staff sudah tersedia.');
}

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare(
    "INSERT INTO users (name, email, password, role)
     VALUES (:name, :email, :password, :role)"
);

$stmt->execute([
    'name' => $name,
    'email' => $email,
    'password' => $hashedPassword,
    'role' => $role
]);

echo 'Akun Staff berhasil dibuat!';