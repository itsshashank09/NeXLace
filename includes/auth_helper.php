<?php
function requireAuth()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        header('Location: login.html');
        exit();
    }
}

function getCurrentUser()
{
    return [
        'id' => $_SESSION['user_id'] ?? null,
        'name' => $_SESSION['name'] ?? null,
        'email' => $_SESSION['email'] ?? null
    ];
}
?>
