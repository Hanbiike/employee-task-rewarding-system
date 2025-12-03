<?php
/**
 * Конфигурация базы данных
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'AEA_DB');
define('DB_USER', 'root');
define('DB_PASS', 'root'); // Измените на ваш пароль MAMP
define('DB_CHARSET', 'utf8mb4');

// Настройки приложения
define('APP_NAME', 'AEA System');
define('APP_URL', 'http://localhost:8888/employee-rewarding-system');
define('SESSION_TIMEOUT', 3600); // 1 час

// Настройки безопасности
define('HASH_ALGO', PASSWORD_BCRYPT);
define('HASH_COST', 12);
