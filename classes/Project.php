<?php
/**
 * Класс для работы с проектами
 */
class Project {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Создать новый проект (CEO)
     */
    public function create($data) {
        $sql = "INSERT INTO projects (name, description, deadline, department_id, 
                status, importance, created_by_manager_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        // Для обратной совместимости берем первый департамент или NULL
        $firstDepartmentId = null;
        if (!empty($data['department_ids']) && is_array($data['department_ids'])) {
            $firstDepartmentId = $data['department_ids'][0];
        } elseif (!empty($data['department_id'])) {
            $firstDepartmentId = $data['department_id'];
        }
        
        $projectId = $this->db->insert($sql, [
            $data['name'],
            $data['description'] ?? null,
            $data['deadline'] ?? null,
            $firstDepartmentId,
            $data['status'] ?? 'Not Started',
            $data['importance'] ?? 'Medium',
            $data['created_by_manager_id']
        ]);

        // Назначить департаменты на проект
        if (!empty($data['department_ids']) && is_array($data['department_ids'])) {
            $this->assignDepartments($projectId, $data['department_ids']);
        } elseif (!empty($firstDepartmentId)) {
            $this->assignDepartments($projectId, [$firstDepartmentId]);
        }

        // Назначить менеджеров на проект
        if (!empty($data['manager_ids']) && is_array($data['manager_ids'])) {
            $this->assignManagers($projectId, $data['manager_ids']);
        }

        return $projectId;
    }

    /**
     * Назначить департаменты на проект
     */
    public function assignDepartments($projectId, $departmentIds) {
        // Удаляем старые назначения
        $this->db->delete("DELETE FROM project_departments WHERE project_id = ?", [$projectId]);

        // Добавляем новые
        $sql = "INSERT INTO project_departments (project_id, department_id) VALUES (?, ?)";
        foreach ($departmentIds as $departmentId) {
            $this->db->insert($sql, [$projectId, $departmentId]);
        }
    }

    /**
     * Назначить менеджеров на проект
     */
    public function assignManagers($projectId, $managerIds) {
        // Удаляем старые назначения
        $this->db->delete("DELETE FROM manager_projects WHERE project_id = ?", [$projectId]);

        // Добавляем новые
        $sql = "INSERT INTO manager_projects (manager_id, project_id) VALUES (?, ?)";
        foreach ($managerIds as $managerId) {
            $this->db->insert($sql, [$managerId, $projectId]);
        }
    }

    /**
     * Обновить проект
     */
    public function update($projectId, $data) {
        $sql = "UPDATE projects SET 
                name = ?, 
                description = ?, 
                deadline = ?, 
                status = ?, 
                importance = ?,
                end_date = ?
                WHERE id = ?";
        
        return $this->db->update($sql, [
            $data['name'],
            $data['description'] ?? null,
            $data['deadline'] ?? null,
            $data['status'],
            $data['importance'],
            $data['end_date'] ?? null,
            $projectId
        ]);
    }

    /**
     * Получить все проекты (для CEO)
     */
    public function getAll() {
        $projects = $this->db->fetchAll(
            "SELECT p.*, 
                    CONCAT(m.first_name, ' ', m.last_name) as created_by
             FROM projects p
             JOIN managers m ON p.created_by_manager_id = m.id
             ORDER BY p.created_at DESC"
        );
        
        // Добавляем информацию о департаментах для каждого проекта
        foreach ($projects as &$project) {
            $departments = $this->getProjectDepartments($project['id']);
            $project['departments'] = $departments;
            $project['department_names'] = implode(', ', array_column($departments, 'name'));
        }
        
        return $projects;
    }

    /**
     * Получить проекты по отделу
     */
    public function getByDepartment($departmentId) {
        $projects = $this->db->fetchAll(
            "SELECT p.*, 
                    CONCAT(m.first_name, ' ', m.last_name) as created_by
             FROM projects p
             JOIN managers m ON p.created_by_manager_id = m.id
             JOIN project_departments pd ON p.id = pd.project_id
             WHERE pd.department_id = ?
             GROUP BY p.id
             ORDER BY p.created_at DESC",
            [$departmentId]
        );
        
        // Добавляем информацию о департаментах для каждого проекта
        foreach ($projects as &$project) {
            $departments = $this->getProjectDepartments($project['id']);
            $project['departments'] = $departments;
            $project['department_names'] = implode(', ', array_column($departments, 'name'));
        }
        
        return $projects;
    }

    /**
     * Получить проекты менеджера
     */
    public function getByManager($managerId) {
        $projects = $this->db->fetchAll(
            "SELECT p.*, 
                    CONCAT(m.first_name, ' ', m.last_name) as created_by
             FROM projects p
             JOIN managers m ON p.created_by_manager_id = m.id
             JOIN manager_projects mp ON p.id = mp.project_id
             WHERE mp.manager_id = ?
             ORDER BY p.created_at DESC",
            [$managerId]
        );
        
        // Добавляем информацию о департаментах для каждого проекта
        foreach ($projects as &$project) {
            $departments = $this->getProjectDepartments($project['id']);
            $project['departments'] = $departments;
            $project['department_names'] = implode(', ', array_column($departments, 'name'));
        }
        
        return $projects;
    }

    /**
     * Получить один проект по ID
     */
    public function getById($projectId) {
        $project = $this->db->fetchOne(
            "SELECT p.*, 
                    CONCAT(m.first_name, ' ', m.last_name) as created_by
             FROM projects p
             JOIN managers m ON p.created_by_manager_id = m.id
             WHERE p.id = ?",
            [$projectId]
        );
        
        if ($project) {
            // Добавляем информацию о департаментах
            $departments = $this->getProjectDepartments($projectId);
            $project['departments'] = $departments;
            $project['department_names'] = implode(', ', array_column($departments, 'name'));
        }
        
        return $project;
    }

    /**
     * Получить департаменты проекта
     */
    public function getProjectDepartments($projectId) {
        return $this->db->fetchAll(
            "SELECT d.*
             FROM departments d
             JOIN project_departments pd ON d.id = pd.department_id
             WHERE pd.project_id = ?
             ORDER BY d.name",
            [$projectId]
        );
    }

    /**
     * Получить менеджеров проекта
     */
    public function getProjectManagers($projectId) {
        return $this->db->fetchAll(
            "SELECT m.*, d.name as department_name
             FROM managers m
             JOIN manager_projects mp ON m.id = mp.manager_id
             JOIN departments d ON m.department_id = d.id
             WHERE mp.project_id = ?",
            [$projectId]
        );
    }

    /**
     * Удалить проект
     */
    public function delete($projectId) {
        // Сначала удаляем связанные задачи и назначения
        $this->db->beginTransaction();
        try {
            $this->db->delete("DELETE FROM project_departments WHERE project_id = ?", [$projectId]);
            $this->db->delete("DELETE FROM manager_projects WHERE project_id = ?", [$projectId]);
            
            // Получаем все задачи проекта
            $tasks = $this->db->fetchAll("SELECT id FROM tasks WHERE project_id = ?", [$projectId]);
            foreach ($tasks as $task) {
                $this->db->delete("DELETE FROM employee_tasks WHERE task_id = ?", [$task['id']]);
            }
            
            $this->db->delete("DELETE FROM tasks WHERE project_id = ?", [$projectId]);
            $this->db->delete("DELETE FROM projects WHERE id = ?", [$projectId]);
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Получить статистику по проектам
     */
    public function getStatistics($departmentId = null) {
        $sql = "SELECT 
                    status,
                    COUNT(*) as count,
                    importance
                FROM projects";
        
        $params = [];
        if ($departmentId) {
            $sql .= " WHERE department_id = ?";
            $params[] = $departmentId;
        }
        
        $sql .= " GROUP BY status, importance";
        
        return $this->db->fetchAll($sql, $params);
    }
}
