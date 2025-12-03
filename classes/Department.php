<?php
/**
 * Класс для работы с отделами
 */
class Department {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Получить все отделы
     */
    public function getAll() {
        return $this->db->fetchAll(
            "SELECT d.*, 
                    COUNT(DISTINCT e.id) as employee_count,
                    COUNT(DISTINCT m.id) as manager_count
             FROM departments d
             LEFT JOIN employees e ON d.id = e.department_id
             LEFT JOIN managers m ON d.id = m.department_id
             GROUP BY d.id
             ORDER BY d.name"
        );
    }

    /**
     * Получить отдел по ID
     */
    public function getById($departmentId) {
        return $this->db->fetchOne(
            "SELECT d.*, 
                    COUNT(DISTINCT e.id) as employee_count,
                    COUNT(DISTINCT m.id) as manager_count
             FROM departments d
             LEFT JOIN employees e ON d.id = e.department_id
             LEFT JOIN managers m ON d.id = m.department_id
             WHERE d.id = ?
             GROUP BY d.id",
            [$departmentId]
        );
    }

    /**
     * Создать новый отдел
     */
    public function create($name) {
        // Проверяем, не существует ли уже отдел с таким именем
        $existing = $this->db->fetchOne("SELECT id FROM departments WHERE name = ?", [$name]);
        if ($existing) {
            throw new Exception("Отдел с таким названием уже существует");
        }

        return $this->db->insert("INSERT INTO departments (name) VALUES (?)", [$name]);
    }

    /**
     * Обновить отдел
     */
    public function update($departmentId, $name) {
        // Проверяем, не существует ли другой отдел с таким именем
        $existing = $this->db->fetchOne(
            "SELECT id FROM departments WHERE name = ? AND id != ?", 
            [$name, $departmentId]
        );
        if ($existing) {
            throw new Exception("Отдел с таким названием уже существует");
        }

        return $this->db->update(
            "UPDATE departments SET name = ? WHERE id = ?",
            [$name, $departmentId]
        );
    }

    /**
     * Удалить отдел (только если нет сотрудников и менеджеров)
     */
    public function delete($departmentId) {
        // Проверяем, есть ли сотрудники в отделе
        $employeeCount = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM employees WHERE department_id = ?",
            [$departmentId]
        )['count'];

        $managerCount = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM managers WHERE department_id = ?",
            [$departmentId]
        )['count'];

        if ($employeeCount > 0 || $managerCount > 0) {
            throw new Exception("Невозможно удалить отдел с сотрудниками или менеджерами");
        }

        return $this->db->delete("DELETE FROM departments WHERE id = ?", [$departmentId]);
    }

    /**
     * Получить сотрудников отдела
     */
    public function getEmployees($departmentId) {
        return $this->db->fetchAll(
            "SELECT e.*, 
                    CONCAT(m.first_name, ' ', m.last_name) as manager_name
             FROM employees e
             LEFT JOIN managers m ON e.manager_id = m.id
             WHERE e.department_id = ?
             ORDER BY e.last_name, e.first_name",
            [$departmentId]
        );
    }

    /**
     * Получить менеджеров отдела
     */
    public function getManagers($departmentId) {
        return $this->db->fetchAll(
            "SELECT * FROM managers 
             WHERE department_id = ?
             ORDER BY last_name, first_name",
            [$departmentId]
        );
    }

    /**
     * Получить статистику отдела
     */
    public function getStatistics($departmentId) {
        // Количество проектов
        $projectCount = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM projects WHERE department_id = ?",
            [$departmentId]
        )['count'];

        // Количество активных проектов
        $activeProjects = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM projects 
             WHERE department_id = ? AND status = 'In Progress'",
            [$departmentId]
        )['count'];

        // Количество задач
        $taskCount = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM tasks t
             JOIN projects p ON t.project_id = p.id
             WHERE p.department_id = ?",
            [$departmentId]
        )['count'];

        return [
            'project_count' => $projectCount,
            'active_projects' => $activeProjects,
            'task_count' => $taskCount
        ];
    }
}
