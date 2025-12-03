<?php
/**
 * Класс для работы с задачами
 */
class Task {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Создать новую задачу
     */
    public function create($data) {
        $sql = "INSERT INTO tasks (name, description, deadline, project_id, 
                created_by_manager_id, status, importance) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $taskId = $this->db->insert($sql, [
            $data['name'],
            $data['description'] ?? null,
            $data['deadline'] ?? null,
            $data['project_id'],
            $data['created_by_manager_id'],
            $data['status'] ?? 'Not Started',
            $data['importance'] ?? 'Medium'
        ]);

        // Назначить сотрудников на задачу
        if (!empty($data['employee_ids']) && is_array($data['employee_ids'])) {
            $this->assignEmployees($taskId, $data['employee_ids']);
        }

        return $taskId;
    }

    /**
     * Назначить сотрудников на задачу
     */
    public function assignEmployees($taskId, $employeeIds) {
        // Удаляем старые назначения
        $this->db->delete("DELETE FROM employee_tasks WHERE task_id = ?", [$taskId]);

        // Добавляем новые
        $sql = "INSERT INTO employee_tasks (employee_id, task_id) VALUES (?, ?)";
        foreach ($employeeIds as $employeeId) {
            $this->db->insert($sql, [$employeeId, $taskId]);
        }
    }

    /**
     * Обновить задачу
     */
    public function update($taskId, $data) {
        $sql = "UPDATE tasks SET 
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
            $taskId
        ]);
    }

    /**
     * Обновить статус задачи
     */
    public function updateStatus($taskId, $status, $endDate = null) {
        $sql = "UPDATE tasks SET status = ?, end_date = ? WHERE id = ?";
        return $this->db->update($sql, [$status, $endDate, $taskId]);
    }

    /**
     * Получить все задачи проекта
     */
    public function getByProject($projectId) {
        return $this->db->fetchAll(
            "SELECT t.*, 
                    CONCAT(m.first_name, ' ', m.last_name) as created_by,
                    p.name as project_name
             FROM tasks t
             JOIN managers m ON t.created_by_manager_id = m.id
             JOIN projects p ON t.project_id = p.id
             WHERE t.project_id = ?
             ORDER BY t.created_at DESC",
            [$projectId]
        );
    }

    /**
     * Получить задачи сотрудника
     */
    public function getByEmployee($employeeId) {
        return $this->db->fetchAll(
            "SELECT t.*, 
                    CONCAT(m.first_name, ' ', m.last_name) as created_by,
                    p.name as project_name,
                    p.department_id
             FROM tasks t
             JOIN managers m ON t.created_by_manager_id = m.id
             JOIN projects p ON t.project_id = p.id
             JOIN employee_tasks et ON t.id = et.task_id
             WHERE et.employee_id = ?
             ORDER BY t.created_at DESC",
            [$employeeId]
        );
    }

    /**
     * Получить задачу по ID
     */
    public function getById($taskId) {
        return $this->db->fetchOne(
            "SELECT t.*, 
                    CONCAT(m.first_name, ' ', m.last_name) as created_by,
                    p.name as project_name,
                    p.department_id
             FROM tasks t
             JOIN managers m ON t.created_by_manager_id = m.id
             JOIN projects p ON t.project_id = p.id
             WHERE t.id = ?",
            [$taskId]
        );
    }

    /**
     * Получить исполнителей задачи
     */
    public function getTaskEmployees($taskId) {
        return $this->db->fetchAll(
            "SELECT e.*, d.name as department_name
             FROM employees e
             JOIN employee_tasks et ON e.id = et.employee_id
             JOIN departments d ON e.department_id = d.id
             WHERE et.task_id = ?",
            [$taskId]
        );
    }

    /**
     * Получить все задачи менеджера
     */
    public function getByManager($managerId) {
        return $this->db->fetchAll(
            "SELECT t.*, 
                    p.name as project_name,
                    p.department_id
             FROM tasks t
             JOIN projects p ON t.project_id = p.id
             WHERE t.created_by_manager_id = ?
             ORDER BY t.created_at DESC",
            [$managerId]
        );
    }

    /**
     * Удалить задачу
     */
    public function delete($taskId) {
        $this->db->beginTransaction();
        try {
            $this->db->delete("DELETE FROM employee_tasks WHERE task_id = ?", [$taskId]);
            $this->db->delete("DELETE FROM tasks WHERE id = ?", [$taskId]);
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Получить статистику по задачам
     */
    public function getStatistics($projectId = null, $employeeId = null) {
        $sql = "SELECT 
                    status,
                    COUNT(*) as count,
                    importance
                FROM tasks t";
        
        $params = [];
        $conditions = [];
        
        if ($projectId) {
            $conditions[] = "t.project_id = ?";
            $params[] = $projectId;
        }
        
        if ($employeeId) {
            $sql .= " JOIN employee_tasks et ON t.id = et.task_id";
            $conditions[] = "et.employee_id = ?";
            $params[] = $employeeId;
        }
        
        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(" AND ", $conditions);
        }
        
        $sql .= " GROUP BY status, importance";
        
        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Получить статистику выполненных задач с учетом весов важности для KPI
     * @param int $employeeId ID сотрудника
     * @param string $period Период в формате YYYY-MM или YYYY-MM-DD
     * @param string $periodType Тип периода: monthly, quarterly, yearly
     */
    public function getTasksKPIData($employeeId, $period, $periodType = 'monthly') {
        // Нормализуем период к формату YYYY-MM-01
        if (strlen($period) == 7) {
            $period = $period . '-01';
        }
        
        $date = new DateTime($period);
        $month = (int)$date->format('m');
        $year = (int)$date->format('Y');
        
        // Определяем диапазон дат в зависимости от типа периода
        if ($periodType === 'yearly') {
            // Годовая премия - весь год (январь - декабрь)
            $startDate = $year . '-01-01';
            $endDate = $year . '-12-31';
        } elseif ($periodType === 'quarterly') {
            // Квартальная премия - весь квартал (3 месяца)
            if ($month <= 3) {
                $startDate = $year . '-01-01';
                $endDate = $year . '-03-31';
            } elseif ($month <= 6) {
                $startDate = $year . '-04-01';
                $endDate = $year . '-06-30';
            } elseif ($month <= 9) {
                $startDate = $year . '-07-01';
                $endDate = $year . '-09-30';
            } else {
                $startDate = $year . '-10-01';
                $endDate = $year . '-12-31';
            }
        } else {
            // Месячная премия - только текущий месяц
            $startDate = date('Y-m-01', strtotime($period));
            $endDate = date('Y-m-t', strtotime($period));
        }
        
        $sql = "SELECT 
                    t.importance,
                    tiw.weight as importance_weight,
                    COUNT(*) as task_count,
                    SUM(CASE WHEN t.status = 'Completed' THEN 1 ELSE 0 END) as completed_count,
                    SUM(CASE WHEN t.status = 'Completed' THEN tiw.weight ELSE 0 END) as completed_weight_sum,
                    SUM(tiw.weight) as total_weight_sum
                FROM tasks t
                JOIN employee_tasks et ON t.id = et.task_id
                LEFT JOIN task_importance_weights tiw ON t.importance = tiw.importance
                WHERE et.employee_id = ? 
                AND t.created_at >= ? 
                AND t.created_at <= ?
                GROUP BY t.importance, tiw.weight";
        
        return $this->db->fetchAll($sql, [$employeeId, $startDate, $endDate]);
    }

    /**
     * Рассчитать процент KPI на основе задач (сумма весов выполненных / сумма всех весов)
     * @param int $employeeId ID сотрудника
     * @param string $period Период в формате YYYY-MM
     * @param string $periodType Тип периода: monthly, quarterly, yearly
     */
    public function calculateTasksKPIPercentage($employeeId, $period, $periodType = 'monthly') {
        $data = $this->getTasksKPIData($employeeId, $period, $periodType);
        
        if (empty($data)) {
            return 0;
        }

        $totalCompletedWeight = 0;
        $totalWeight = 0;

        foreach ($data as $row) {
            $totalCompletedWeight += $row['completed_weight_sum'] ?? 0;
            $totalWeight += $row['total_weight_sum'] ?? 0;
        }

        if ($totalWeight == 0) {
            return 0;
        }

        // Возвращаем процент (от 0 до 100)
        return round(($totalCompletedWeight / $totalWeight) * 100, 2);
    }

    /**
     * Получить веса важности задач
     */
    public function getImportanceWeights() {
        return $this->db->fetchAll(
            "SELECT importance, weight FROM task_importance_weights ORDER BY weight"
        );
    }

    /**
     * Обновить вес важности задачи (только для CEO)
     */
    public function updateImportanceWeight($importance, $weight) {
        $sql = "UPDATE task_importance_weights SET weight = ? WHERE importance = ?";
        return $this->db->update($sql, [$weight, $importance]);
    }
}

