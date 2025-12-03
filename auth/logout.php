<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/User.php';

// Проверяем авторизацию
if (!User::isAuthenticated()) {
    header('Location: ' . APP_URL . '/auth/login.php');
    exit;
}

// Выполняем выход
User::logout();

// Перенаправляем на страницу входа
header('Location: ' . APP_URL . '/auth/login.php');
exit;
