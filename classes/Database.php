<?php
/**
 * Класс для работы с базой данных
 */
class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->pdo;
    }

    /**
     * Выполнить подготовленный запрос
     */
    public function query($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Query error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Получить одну запись
     */
    public function fetchOne($sql, $params = []) {
        return $this->query($sql, $params)->fetch();
    }

    /**
     * Получить все записи
     */
    public function fetchAll($sql, $params = []) {
        return $this->query($sql, $params)->fetchAll();
    }

    /**
     * Вставить запись и вернуть ID
     */
    public function insert($sql, $params = []) {
        $this->query($sql, $params);
        return $this->pdo->lastInsertId();
    }

    /**
     * Обновить записи
     */
    public function update($sql, $params = []) {
        return $this->query($sql, $params)->rowCount();
    }

    /**
     * Удалить записи
     */
    public function delete($sql, $params = []) {
        return $this->query($sql, $params)->rowCount();
    }

    /**
     * Начать транзакцию
     */
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }

    /**
     * Зафиксировать транзакцию
     */
    public function commit() {
        return $this->pdo->commit();
    }

    /**
     * Откатить транзакцию
     */
    public function rollback() {
        return $this->pdo->rollback();
    }
}
