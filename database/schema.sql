CREATE DATABASE IF NOT EXISTS restaurant_workforce_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE restaurant_workforce_db;

SET NAMES utf8mb4;

-- 1. JOB CATEGORIES

CREATE TABLE IF NOT EXISTS job_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT IGNORE INTO job_categories (category_name, description)
VALUES
    ('Utilities and Cleaning', 'Janitors, cleaners, and related support work'),
    ('Kitchen', 'Kitchen and food preparation work'),
    ('Cashier', 'Cash handling and cashier duties'),
    ('Security/Guard', 'Security and guard duties');


-- 2. USERS

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id VARCHAR(50) NOT NULL UNIQUE,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(254) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,

    role ENUM('admin', 'staff') NOT NULL DEFAULT 'staff',

    primary_category_id INT UNSIGNED NULL,
    position VARCHAR(100) NULL,

    account_status ENUM('active', 'inactive')
        NOT NULL DEFAULT 'active',

    must_change_password TINYINT(1)
        NOT NULL DEFAULT 0,

    last_login_at DATETIME NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_users_primary_category
        FOREIGN KEY (primary_category_id)
        REFERENCES job_categories(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,

    INDEX idx_users_role_status (role, account_status),
    INDEX idx_users_name (full_name)
) ENGINE=InnoDB;


-- 3. STAFF PERMITTED JOB CATEGORIES

CREATE TABLE IF NOT EXISTS user_permitted_categories (
    user_id INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    authorized_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (user_id, category_id),

    CONSTRAINT fk_permitted_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_permitted_category
        FOREIGN KEY (category_id)
        REFERENCES job_categories(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_permitted_authorizer
        FOREIGN KEY (authorized_by)
        REFERENCES users(id)
        ON DELETE SET NULL,

    INDEX idx_permitted_category (category_id)
) ENGINE=InnoDB;


-- 4. SCHEDULES


CREATE TABLE IF NOT EXISTS schedules (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    staff_id INT UNSIGNED NOT NULL,
    schedule_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,

    task_description TEXT NOT NULL,
    assigned_category_id INT UNSIGNED NOT NULL,

    work_location VARCHAR(150) NULL,
    notes TEXT NULL,

    status ENUM('scheduled', 'completed', 'cancelled')
        NOT NULL DEFAULT 'scheduled',

    created_by INT UNSIGNED NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    -- Supports attendance foreign-key validation.
    UNIQUE KEY uq_schedule_staff (id, staff_id),

    CONSTRAINT fk_schedule_staff
        FOREIGN KEY (staff_id)
        REFERENCES users(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_schedule_category
        FOREIGN KEY (assigned_category_id)
        REFERENCES job_categories(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_schedule_creator
        FOREIGN KEY (created_by)
        REFERENCES users(id)
        ON DELETE SET NULL,

    INDEX idx_schedule_date_status (schedule_date, status),
    INDEX idx_schedule_staff_date (staff_id, schedule_date),
    INDEX idx_schedule_category_date
        (assigned_category_id, schedule_date),

    CONSTRAINT chk_schedule_times
        CHECK (start_time <> end_time)
) ENGINE=InnoDB;


-- 5. ATTENDANCE

CREATE TABLE IF NOT EXISTS attendance (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    schedule_id INT UNSIGNED NOT NULL UNIQUE,
    staff_id INT UNSIGNED NOT NULL,

    check_in_time DATETIME NULL,
    check_out_time DATETIME NULL,

    attendance_status ENUM(
        'not_started',
        'present',
        'completed',
        'late',
        'absent',
        'incomplete'
    ) NOT NULL DEFAULT 'not_started',

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_attendance_schedule_staff
        FOREIGN KEY (schedule_id, staff_id)
        REFERENCES schedules(id, staff_id)
        ON DELETE RESTRICT
        ON UPDATE RESTRICT,

    INDEX idx_attendance_staff (staff_id),
    INDEX idx_attendance_checkin (check_in_time),
    INDEX idx_attendance_status (attendance_status)
) ENGINE=InnoDB;


-- 6. AUDIT LOGS


CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_user_id INT UNSIGNED NULL,

    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(100) NULL,
    entity_id VARCHAR(100) NULL,
    description TEXT NULL,

    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_audit_actor
        FOREIGN KEY (actor_user_id)
        REFERENCES users(id)
        ON DELETE SET NULL,

    INDEX idx_audit_actor_date (actor_user_id, created_at),
    INDEX idx_audit_action_date (action, created_at),
    INDEX idx_audit_entity (entity_type, entity_id)
) ENGINE=InnoDB;


-- 7. SYSTEM SETTINGS


CREATE TABLE IF NOT EXISTS system_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NULL,

    updated_by INT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_setting_updater
        FOREIGN KEY (updated_by)
        REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB;


-- 8. STAFF PASSWORD-ASSISTANCE REQUESTS


CREATE TABLE IF NOT EXISTS password_assistance_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    user_id INT UNSIGNED NULL,
    submitted_email VARCHAR(254) NOT NULL,
    submitted_employee_id VARCHAR(50) NULL,
    message TEXT NULL,

    status ENUM('pending', 'in_progress', 'resolved', 'dismissed')
        NOT NULL DEFAULT 'pending',

    is_read TINYINT(1) NOT NULL DEFAULT 0,

    resolved_by INT UNSIGNED NULL,
    resolved_at DATETIME NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_assistance_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE SET NULL,

    CONSTRAINT fk_assistance_resolver
        FOREIGN KEY (resolved_by)
        REFERENCES users(id)
        ON DELETE SET NULL,

    INDEX idx_assistance_status_date (status, created_at),
    INDEX idx_assistance_read (is_read, created_at),
    INDEX idx_assistance_user (user_id)
) ENGINE=InnoDB;


-- 9. ADMIN RECOVERY CODES


CREATE TABLE IF NOT EXISTS admin_recovery_codes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_user_id INT UNSIGNED NOT NULL,

    code_hash VARCHAR(255) NOT NULL,
    consumed_at DATETIME NULL,
    revoked_at DATETIME NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_recovery_admin
        FOREIGN KEY (admin_user_id)
        REFERENCES users(id)
        ON DELETE CASCADE,

    INDEX idx_recovery_admin_status
        (admin_user_id, consumed_at, revoked_at)
) ENGINE=InnoDB;


-- 10. INTERNAL ADMIN NOTIFICATIONS


CREATE TABLE IF NOT EXISTS notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    recipient_user_id INT UNSIGNED NOT NULL,
    request_id BIGINT UNSIGNED NULL,

    notification_type VARCHAR(100) NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NULL,

    is_read TINYINT(1) NOT NULL DEFAULT 0,
    read_at DATETIME NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_notification_recipient
        FOREIGN KEY (recipient_user_id)
        REFERENCES users(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_notification_request
        FOREIGN KEY (request_id)
        REFERENCES password_assistance_requests(id)
        ON DELETE SET NULL,

    INDEX idx_notification_recipient_read
        (recipient_user_id, is_read, created_at)
) ENGINE=InnoDB;


-- 11. STAFF PASSWORD RESET TOKENS


CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    user_id INT UNSIGNED NOT NULL,
    token_hash VARCHAR(255) NOT NULL UNIQUE,

    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_by INT UNSIGNED NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_reset_token_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_reset_token_creator
        FOREIGN KEY (created_by)
        REFERENCES users(id)
        ON DELETE SET NULL,

    INDEX idx_reset_user_expiry (user_id, expires_at),
    INDEX idx_reset_expiry (expires_at)
) ENGINE=InnoDB;