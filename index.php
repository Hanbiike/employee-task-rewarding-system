<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/User.php';

// Если пользователь уже авторизован, перенаправляем в dashboard
if (User::isAuthenticated()) {
    $role = User::getCurrentRole();
    header('Location: ' . APP_URL . '/dashboard/' . strtolower($role) . '.php');
    exit;
}

// Перенаправляем на страницу входа
header('Location: ' . APP_URL . '/auth/login.php');
exit;
