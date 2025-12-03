CREATE DATABASE IF NOT EXISTS `AEA_DB`;
USE `AEA_DB`;

CREATE TABLE IF NOT EXISTS `departments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS `managers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `first_name` VARCHAR(50) NOT NULL,
    `last_name` VARCHAR(50) NOT NULL,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `phone_number` VARCHAR(15) NOT NULL UNIQUE,
    `department_id` INT NOT NULL,
    `position` ENUM('CEO', 'Manager') DEFAULT 'Manager',
    `hire_date` DATE NOT NULL,
    `base_salary` DECIMAL(10,2) NOT NULL,

    FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`)
);

CREATE TABLE IF NOT EXISTS `employees` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `first_name` VARCHAR(50) NOT NULL,
    `last_name` VARCHAR(50) NOT NULL,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `phone_number` VARCHAR(15) NOT NULL UNIQUE,
    `department_id` INT NOT NULL,
    `hire_date` DATE NOT NULL,
    `base_salary` DECIMAL(10,2) NOT NULL,
    `manager_id` INT,

    FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`),
    FOREIGN KEY (`manager_id`) REFERENCES `managers`(`id`)
);

CREATE TABLE IF NOT EXISTS `projects` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `end_date` DATE,
    `deadline` DATE,
    `department_id` INT NOT NULL,
    `status` ENUM('Not Started', 'In Progress', 'Completed', 'Frozen', 'On Moderation', 'Archived', 'Canceled') DEFAULT 'Not Started',
    `importance` ENUM('Low', 'Medium', 'High', 'Critical') DEFAULT 'Medium',
    `created_by_manager_id` INT NOT NULL,

    FOREIGN KEY (`created_by_manager_id`) REFERENCES `managers`(`id`),
    FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`)
);

CREATE TABLE IF NOT EXISTS `tasks` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `end_date` DATE,
    `deadline` DATE,
    `project_id` INT NOT NULL,
    `created_by_manager_id` INT NOT NULL,
    `status` ENUM('Not Started', 'In Progress', 'Completed', 'Frozen', 'On Moderation', 'Archived', 'Canceled') DEFAULT 'Not Started',
    `importance` ENUM('Low', 'Medium', 'High', 'Critical') DEFAULT 'Medium',

    FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`),
    FOREIGN KEY (`created_by_manager_id`) REFERENCES `managers`(`id`)
);

CREATE TABLE IF NOT EXISTS `employee_tasks` (
    `employee_id` INT NOT NULL,
    `task_id` INT NOT NULL,

    PRIMARY KEY (`employee_id`, `task_id`),
    FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`),
    FOREIGN KEY (`task_id`) REFERENCES `tasks`(`id`)
);

CREATE TABLE IF NOT EXISTS `manager_projects` (
    `manager_id` INT NOT NULL,
    `project_id` INT NOT NULL,

    PRIMARY KEY (`manager_id`, `project_id`),
    FOREIGN KEY (`manager_id`) REFERENCES `managers`(`id`),
    FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`)
);

CREATE TABLE IF NOT EXISTS `project_departments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `project_id` INT NOT NULL,
    `department_id` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE KEY `unique_project_department` (`project_id`, `department_id`),
    FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS `kpi` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT,
    `weight` INT NOT NULL COMMENT 'Вес в процентах (25 = 25%)',
    `target_value` FLOAT DEFAULT 100,
    `measurement_unit` VARCHAR(20) DEFAULT '%',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Таблица для хранения настроек системы KPI (устанавливается CEO)
CREATE TABLE IF NOT EXISTS `kpi_settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `tasks_weight_percentage` INT NOT NULL DEFAULT 50 COMMENT 'Процент веса задач в общем KPI (N%)',
    `manager_evaluation_percentage` INT NOT NULL DEFAULT 50 COMMENT 'Процент веса оценки менеджера (100-N%)',
    `manager_bonus_percentage` DECIMAL(5,2) NOT NULL DEFAULT 100.00 COMMENT 'Базовый процент для расчета премии менеджера (100/количество сотрудников * этот коэффициент)',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `updated_by_manager_id` INT NOT NULL,
    FOREIGN KEY (`updated_by_manager_id`) REFERENCES `managers`(`id`)
);

-- Таблица для весов важности задач (настраивается CEO)
CREATE TABLE IF NOT EXISTS `task_importance_weights` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `importance` ENUM('Low', 'Medium', 'High', 'Critical') NOT NULL UNIQUE,
    `weight` INT NOT NULL DEFAULT 1,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `kpi_values` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `employee_id` INT,
    `kpi_id` INT,
    `value` FLOAT,
    `period` DATE,
    FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`),
    FOREIGN KEY (`kpi_id`) REFERENCES `kpi`(`id`)
);

CREATE TABLE IF NOT EXISTS `rewards` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `employee_id` INT,
    `period` DATE,
    `period_type` ENUM('monthly', 'quarterly', 'yearly') DEFAULT 'monthly',
    `base_salary` DECIMAL(10,2) NOT NULL,
    `kpi_total` FLOAT,
    `bonus_amount` DECIMAL(10,2),
    `total_amount` DECIMAL(10,2),
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`)
);

CREATE TABLE IF NOT EXISTS `manager_rewards` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `manager_id` INT NOT NULL,
    `period` DATE NOT NULL,
    `period_type` ENUM('monthly', 'quarterly', 'yearly') DEFAULT 'monthly',
    `base_salary` DECIMAL(10,2) NOT NULL,
    `department_id` INT NOT NULL,
    `employees_count` INT NOT NULL COMMENT 'Количество сотрудников в отделе',
    `total_employee_bonuses` DECIMAL(10,2) NOT NULL COMMENT 'Общая сумма премий сотрудников',
    `bonus_percentage` DECIMAL(5,2) NOT NULL COMMENT 'Процент от премий (100/количество сотрудников)',
    `bonus_amount` DECIMAL(10,2) NOT NULL COMMENT 'Премия менеджера',
    `total_amount` DECIMAL(10,2) NOT NULL COMMENT 'Базовая зарплата + премия',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`manager_id`) REFERENCES `managers`(`id`),
    FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`),
    UNIQUE KEY `unique_manager_period` (`manager_id`, `period`, `period_type`)
);