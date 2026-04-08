-- ============================================================
-- TPMS - Tactical Planning Monitoring System
-- Database Schema
-- ============================================================

CREATE DATABASE IF NOT EXISTS tpms_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tpms_db;

-- Divisions
CREATE TABLE divisions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(200) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Departments
CREATE TABLE departments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(200) NOT NULL,
    code VARCHAR(50),
    division_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (division_id) REFERENCES divisions(id) ON DELETE SET NULL
);

-- Users
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(200) NOT NULL,
    email VARCHAR(200) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('process_owner','division_chief','iqao','president') NOT NULL,
    department_id INT NULL,
    division_id INT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    FOREIGN KEY (division_id) REFERENCES divisions(id) ON DELETE SET NULL
);

-- Tactical Plans
CREATE TABLE tactical_plans (
    id INT PRIMARY KEY AUTO_INCREMENT,
    reference_no VARCHAR(100) UNIQUE,
    department_id INT NOT NULL,
    created_by INT NOT NULL,
    academic_year INT NOT NULL,
    status ENUM(
        'draft',
        'submitted',
        'returned_to_po',
        'dc_approved',
        'returned_to_dc',
        'iqao_approved',
        'returned_to_iqao',
        'signed'
    ) DEFAULT 'draft',
    revision_notes TEXT NULL,
    returned_by INT NULL,
    submitted_at TIMESTAMP NULL,
    dc_approved_at TIMESTAMP NULL,
    dc_approved_by INT NULL,
    iqao_approved_at TIMESTAMP NULL,
    iqao_approved_by INT NULL,
    signed_at TIMESTAMP NULL,
    signed_by INT NULL,
    president_signature MEDIUMTEXT NULL,
    is_controlled_copy TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (dc_approved_by) REFERENCES users(id),
    FOREIGN KEY (iqao_approved_by) REFERENCES users(id),
    FOREIGN KEY (signed_by) REFERENCES users(id),
    FOREIGN KEY (returned_by) REFERENCES users(id)
);

-- Quality Objectives
CREATE TABLE objectives (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tactical_plan_id INT NOT NULL,
    quality_objective TEXT NOT NULL,
    success_indicator TEXT,
    target TEXT,
    program_activity TEXT,
    timeline_q1 TINYINT(1) DEFAULT 0,
    timeline_q2 TINYINT(1) DEFAULT 0,
    timeline_q3 TINYINT(1) DEFAULT 0,
    timeline_q4 TINYINT(1) DEFAULT 0,
    person_responsible VARCHAR(300),
    budget DECIMAL(15,2) DEFAULT 0,
    status ENUM('not_set','accomplished','ongoing','not_accomplished') DEFAULT 'not_set',
    tagged_by INT NULL,
    tagged_at TIMESTAMP NULL,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tactical_plan_id) REFERENCES tactical_plans(id) ON DELETE CASCADE,
    FOREIGN KEY (tagged_by) REFERENCES users(id)
);

-- Evidence
CREATE TABLE evidence (
    id INT PRIMARY KEY AUTO_INCREMENT,
    objective_id INT NOT NULL,
    uploaded_by INT NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_type VARCHAR(100),
    file_size INT,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (objective_id) REFERENCES objectives(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id)
);

-- Comments / Review Remarks
CREATE TABLE comments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tactical_plan_id INT NOT NULL,
    user_id INT NOT NULL,
    comment TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tactical_plan_id) REFERENCES tactical_plans(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Audit Log (read-only, no delete)
CREATE TABLE audit_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tactical_plan_id INT NULL,
    user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tactical_plan_id) REFERENCES tactical_plans(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- In-App Notifications
CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    link VARCHAR(500),
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Deadlines (set by IQAO)
CREATE TABLE deadlines (
    id INT PRIMARY KEY AUTO_INCREMENT,
    type ENUM('objective_setting','evidence_upload','final_evaluation') NOT NULL,
    deadline_date DATE NOT NULL,
    academic_year INT NOT NULL,
    set_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (set_by) REFERENCES users(id),
    UNIQUE KEY unique_deadline (type, academic_year)
);

-- Evaluations
CREATE TABLE evaluations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    objective_id INT NOT NULL UNIQUE,
    type ENUM('accomplished','not_accomplished') NOT NULL,
    impact_benefits TEXT,
    reason TEXT,
    prepared_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (objective_id) REFERENCES objectives(id) ON DELETE CASCADE,
    FOREIGN KEY (prepared_by) REFERENCES users(id)
);

-- ============================================================
-- SAMPLE DATA
-- ============================================================

-- Divisions
INSERT INTO divisions (name) VALUES
('Academic Affairs Division'),
('Administrative Division'),
('Research and Extension Division');

-- Departments
INSERT INTO departments (name, code, division_id) VALUES
('College of Information and Communications Technology', 'CICT', 1),
('College of Engineering', 'COE', 1),
('College of Business Administration', 'CBA', 1),
('Human Resource Management Office', 'HRMO', 2),
('Finance Office', 'FO', 2),
('Research Center', 'RC', 3);

-- Users (passwords are bcrypt of 'password123')
INSERT INTO users (name, email, password, role, department_id, division_id, is_active) VALUES
('IQAO Admin', 'iqao@university.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'iqao', NULL, NULL, 1),
('Dr. Juan dela Cruz', 'president@university.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'president', NULL, NULL, 1),
('Prof. Maria Santos', 'dc.academic@university.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'division_chief', NULL, 1, 1),
('Prof. Roberto Reyes', 'dc.admin@university.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'division_chief', NULL, 2, 1),
('Engr. Ana Gomez', 'po.cict@university.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'process_owner', 1, 1, 1),
('Engr. Pedro Lim', 'po.coe@university.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'process_owner', 2, 1, 1),
('Ms. Lucia Tan', 'po.cba@university.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'process_owner', 3, 1, 1),
('Ms. Rosa Cruz', 'po.hrmo@university.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'process_owner', 4, 2, 1);

-- Note: Default password for all sample users is 'password' (Laravel default hash used above)
-- The hash above '$2y$10$92IXUNpkjO0rOQ5byMi...' is bcrypt of 'password'
