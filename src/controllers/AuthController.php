<?php

require_once __DIR__ . '/../../src/models/User.php';
require_once __DIR__ . '/../../src/helpers/auth.php';

class AuthController {

    public static function handleRegister(): array {
        $name     = trim($_POST['name']     ?? '');
        $email    = trim($_POST['email']    ?? '');
        $password =      $_POST['password'] ?? '';

        // Validate
        if (!$name || !$email || !$password) {
            return ['error' => 'All fields are required.'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['error' => 'Invalid email address.'];
        }
        if (strlen($password) < 8) {
            return ['error' => 'Password must be at least 8 characters.'];
        }
        if (User::emailExists($email)) {
            return ['error' => 'An account with that email already exists.'];
        }

        // Create + login
        $id = User::create($name, $email, $password);
        loginUser($id, $name, $email);

        return ['success' => true];
    }

    public static function handleLogin(): array {
        $email    = trim($_POST['email']    ?? '');
        $password =      $_POST['password'] ?? '';

        if (!$email || !$password) {
            return ['error' => 'All fields are required.'];
        }

        $user = User::findByEmail($email);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return ['error' => 'Invalid email or password.'];
        }

        loginUser($user['id'], $user['name'], $user['email']);

        return ['success' => true];
    }
}