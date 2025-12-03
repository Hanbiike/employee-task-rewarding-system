<?php
/**
 * Класс для работы с пользователями (CEO, Менеджеры, Сотрудники)
 */
class User {
    private $db;
    private $id;
    private $role; // 'CEO', 'Manager', 'Employee'
    private $data;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Авторизация пользователя
     */
    public function login($email, $password) {
        // Проверяем в таблице managers
        $manager = $this->db->fetchOne(
            "SELECT m.*, d.name as department_name 
             FROM managers m 
             JOIN departments d ON m.department_id = d.id 
             WHERE m.email = ?",
            [$email]
        );

        if ($manager && password_verify($password, $manager['password'])) {
            $this->id = $manager['id'];
            $this->role = $manager['position']; // CEO или Manager
            $this->data = $manager;
            $this->createSession();
            return true;
        }

        // Проверяем в таблице employees
        $employee = $this->db->fetchOne(
            "SELECT e.*, d.name as department_name 
             FROM employees e 
             JOIN departments d ON e.department_id = d.id 
             WHERE e.email = ?",
            [$email]
        );

        if ($employee && password_verify($password, $employee['password'])) {
            $this->id = $employee['id'];
            $this->role = 'Employee';
            $this->data = $employee;
            $this->createSession();
            return true;
        }

        return false;
    }

    /**
     * Создать сессию
     */
    private function createSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $_SESSION['user_id'] = $this->id;
        $_SESSION['user_role'] = $this->role;
        $_SESSION['user_data'] = $this->data;
        $_SESSION['login_time'] = time();
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    /**
     * Проверить авторизацию
     */
    public static function isAuthenticated() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['login_time'])) {
            return false;
        }

        // Проверка таймаута сессии
        if (time() - $_SESSION['login_time'] > SESSION_TIMEOUT) {
            self::logout();
            return false;
        }

        $_SESSION['login_time'] = time(); // Обновляем время активности
        return true;
    }

    /**
     * Получить данные текущего пользователя
     */
    public static function getCurrentUser() {
        if (!self::isAuthenticated()) {
            return null;
        }
        return $_SESSION['user_data'];
    }

    /**
     * Получить роль текущего пользователя
     */
    public static function getCurrentRole() {
        if (!self::isAuthenticated()) {
            return null;
        }
        return $_SESSION['user_role'];
    }

    /**
     * Получить ID текущего пользователя
     */
    public static function getCurrentUserId() {
        if (!self::isAuthenticated()) {
            return null;
        }
        return $_SESSION['user_id'];
    }

    /**
     * Проверить права доступа
     */
    public static function hasRole($requiredRole) {
        $currentRole = self::getCurrentRole();
        
        if ($requiredRole === 'CEO') {
            return $currentRole === 'CEO';
        }
        
        if ($requiredRole === 'Manager') {
            return in_array($currentRole, ['CEO', 'Manager']);
        }
        
        return true; // Employee
    }

    /**
     * Выход из системы
     */
    public static function logout() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        session_unset();
        session_destroy();
    }

    /**
     * Регистрация нового менеджера (только для CEO)
     */
    public function registerManager($data) {
        $hashedPassword = password_hash($data['password'], HASH_ALGO, ['cost' => HASH_COST]);
        
        $sql = "INSERT INTO managers (first_name, last_name, email, password, phone_number, 
                department_id, position, hire_date) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        return $this->db->insert($sql, [
            $data['first_name'],
            $data['last_name'],
            $data['email'],
            $hashedPassword,
            $data['phone_number'],
            $data['department_id'],
            $data['position'],
            $data['hire_date']
        ]);
    }

    /**
     * Регистрация нового сотрудника (для CEO и Manager)
     */
    public function registerEmployee($data) {
        $hashedPassword = password_hash($data['password'], HASH_ALGO, ['cost' => HASH_COST]);
        
        $sql = "INSERT INTO employees (first_name, last_name, email, password, phone_number, 
                department_id, hire_date, manager_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        return $this->db->insert($sql, [
            $data['first_name'],
            $data['last_name'],
            $data['email'],
            $hashedPassword,
            $data['phone_number'],
            $data['department_id'],
            $data['hire_date'],
            $data['manager_id'] ?? null
        ]);
    }

    /**
     * Получить список всех менеджеров
     */
    public function getAllManagers() {
        return $this->db->fetchAll(
            "SELECT m.*, d.name as department_name 
             FROM managers m 
             JOIN departments d ON m.department_id = d.id 
             ORDER BY m.last_name, m.first_name"
        );
    }

    /**
     * Получить список сотрудников отдела
     */
    public function getEmployeesByDepartment($departmentId) {
        return $this->db->fetchAll(
            "SELECT e.*, d.name as department_name, 
                    CONCAT(m.first_name, ' ', m.last_name) as manager_name
             FROM employees e 
             JOIN departments d ON e.department_id = d.id 
             LEFT JOIN managers m ON e.manager_id = m.id
             WHERE e.department_id = ?
             ORDER BY e.last_name, e.first_name",
            [$departmentId]
        );
    }

    /**
     * Получить список всех сотрудников
     */
    public function getAllEmployees() {
        return $this->db->fetchAll(
            "SELECT e.*, d.name as department_name, 
                    CONCAT(m.first_name, ' ', m.last_name) as manager_name
             FROM employees e 
             JOIN departments d ON e.department_id = d.id 
             LEFT JOIN managers m ON e.manager_id = m.id
             ORDER BY e.last_name, e.first_name"
        );
    }

    /**
     * Генерация CSRF токена
     */
    public static function getCsrfToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['csrf_token'];
    }

    /**
     * Проверка CSRF токена
     */
    public static function verifyCsrfToken($token) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}
