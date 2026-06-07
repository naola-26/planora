<?php

function startSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function loginUser(int $id, string $name, string $email): void {
    startSession();
    session_regenerate_id(true);
    $_SESSION['user_id']    = $id;
    $_SESSION['user_name']  = $name;
    $_SESSION['user_email'] = $email;
}

function isLoggedIn(): bool {
    startSession();
    return isset($_SESSION['user_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: /auth/');
        exit;
    }
}

function logoutUser(): void {
    startSession();
    session_destroy();
    header('Location: /auth/');
    exit;
}

function currentUser(): array {
    startSession();
    return [
        'id'    => $_SESSION['user_id']    ?? null,
        'name'  => $_SESSION['user_name']  ?? null,
        'email' => $_SESSION['user_email'] ?? null,
    ];
}