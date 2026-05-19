CREATE TABLE problem_areas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    area_name VARCHAR(100) NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    display_order TINYINT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    mobile VARCHAR(15) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    area_id INT,
    tutorial_done TINYINT(1) DEFAULT 0,
    is_graduated TINYINT(1) DEFAULT 0,
    graduation_notes TEXT NULL,
    must_change_password TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (area_id) REFERENCES problem_areas(id) ON DELETE SET NULL
);

CREATE TABLE otps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    otp_code CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    is_used TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE question_sections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    area_id INT NOT NULL,
    section_name VARCHAR(100) NOT NULL,
    display_order TINYINT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (area_id) REFERENCES problem_areas(id) ON DELETE CASCADE
);

CREATE TABLE questions (
    sno INT AUTO_INCREMENT PRIMARY KEY,
    question_text TEXT NOT NULL,
    question_type ENUM('mcq','text','multi_select','rating') NOT NULL,
    rating_min TINYINT DEFAULT 1,
    rating_max TINYINT DEFAULT 5,
    flag TINYINT(1) DEFAULT 1,
    section_id INT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (section_id) REFERENCES question_sections(id) ON DELETE SET NULL
);

CREATE TABLE question_area_map (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question_id INT NOT NULL,
    area_id INT NOT NULL,
    FOREIGN KEY (question_id) REFERENCES questions(sno) ON DELETE CASCADE,
    FOREIGN KEY (area_id) REFERENCES problem_areas(id) ON DELETE CASCADE,
    UNIQUE KEY (question_id, area_id)
);

CREATE TABLE options (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question_id INT NOT NULL,
    option_text VARCHAR(255) NOT NULL,
    points INT DEFAULT 0,
    display_order TINYINT DEFAULT 0,
    FOREIGN KEY (question_id) REFERENCES questions(sno) ON DELETE CASCADE
);

CREATE TABLE admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','team_member') NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_by INT,
    login_attempts INT DEFAULT 0,
    locked_until DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE SET NULL
);

CREATE TABLE audit_windows (
    id INT AUTO_INCREMENT PRIMARY KEY,
    audit_type ENUM('mid_month','month_end') NOT NULL,
    audit_month TINYINT(2) NOT NULL,
    audit_year SMALLINT NOT NULL,
    start_date DATE NULL,
    end_date DATE NULL,
    is_open TINYINT(1) DEFAULT 1,
    opened_by INT,
    opened_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    closed_at DATETIME NULL,
    FOREIGN KEY (opened_by) REFERENCES admin_users(id) ON DELETE SET NULL,
    UNIQUE KEY (audit_type, audit_month, audit_year)
);

CREATE TABLE audit_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    audit_window_id INT NOT NULL,
    area_id INT,
    total_score INT DEFAULT 0,
    max_score INT DEFAULT 0,
    is_perfect TINYINT(1) DEFAULT 0,
    notification_sent TINYINT(1) DEFAULT 0,
    status ENUM('in_progress','completed') NOT NULL,
    started_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    admin_feedback TEXT NULL,
    admin_feedback_by INT NULL,
    admin_feedback_at DATETIME NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (audit_window_id) REFERENCES audit_windows(id) ON DELETE CASCADE,
    FOREIGN KEY (area_id) REFERENCES problem_areas(id) ON DELETE SET NULL,
    UNIQUE KEY (user_id, audit_window_id)
);

CREATE TABLE audit_responses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    audit_session_id INT NOT NULL,
    question_id INT NOT NULL,
    option_id INT NULL,
    text_response TEXT NULL,
    numeric_response TINYINT NULL,
    points_earned INT DEFAULT 0,
    max_question_points INT DEFAULT 0,
    answered_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (audit_session_id) REFERENCES audit_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES questions(sno) ON DELETE CASCADE,
    FOREIGN KEY (option_id) REFERENCES options(id) ON DELETE SET NULL
);

CREATE TABLE audit_response_selections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    audit_response_id INT NOT NULL,
    option_id INT NOT NULL,
    points_earned INT NOT NULL,
    FOREIGN KEY (audit_response_id) REFERENCES audit_responses(id) ON DELETE CASCADE,
    FOREIGN KEY (option_id) REFERENCES options(id) ON DELETE CASCADE
);

CREATE TABLE client_feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    audit_session_id INT NOT NULL,
    suggested_area_id INT NULL,
    feedback_text TEXT NULL,
    is_reviewed TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (audit_session_id) REFERENCES audit_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (suggested_area_id) REFERENCES problem_areas(id) ON DELETE SET NULL
);

CREATE TABLE admin_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('perfect_score','area_feedback','audit_completed','new_registration',
              'team_created','team_updated','team_deactivated','team_feedback_saved',
              'team_feedback_reviewed','team_area_changed','team_client_assigned') NOT NULL,
    category ENUM('client','team') NOT NULL DEFAULT 'client',
    message VARCHAR(255) NOT NULL,
    related_user_id INT NULL,
    related_audit_session_id INT NULL,
    related_feedback_id INT NULL,
    related_admin_id INT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (related_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (related_audit_session_id) REFERENCES audit_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (related_feedback_id) REFERENCES client_feedback(id) ON DELETE CASCADE
);

CREATE TABLE audit_reminder_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    audit_window_id INT NOT NULL,
    reminder_type ENUM('not_started','in_progress') NOT NULL,
    sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (audit_window_id) REFERENCES audit_windows(id) ON DELETE CASCADE,
    UNIQUE KEY user_window_type (user_id, audit_window_id, reminder_type)
);

CREATE TABLE admin_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_user_id INT NOT NULL,
    permission VARCHAR(60) NOT NULL,
    FOREIGN KEY (admin_user_id) REFERENCES admin_users(id) ON DELETE CASCADE
);

CREATE TABLE client_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    team_member_id INT NOT NULL,
    assigned_by INT NOT NULL,
    assigned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (team_member_id) REFERENCES admin_users(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES admin_users(id) ON DELETE CASCADE,
    UNIQUE KEY (client_id)
);

-- Performance indexes on frequently queried columns
CREATE INDEX idx_users_area_id ON users(area_id);
CREATE INDEX idx_otps_user_id ON otps(user_id);
CREATE INDEX idx_question_area_map_area_id ON question_area_map(area_id);
CREATE INDEX idx_options_question_id ON options(question_id);
CREATE INDEX idx_audit_sessions_user_id ON audit_sessions(user_id);
CREATE INDEX idx_audit_sessions_window_id ON audit_sessions(audit_window_id);
CREATE INDEX idx_audit_sessions_area_id ON audit_sessions(area_id);
CREATE INDEX idx_audit_responses_session_id ON audit_responses(audit_session_id);
CREATE INDEX idx_audit_response_selections_response_id ON audit_response_selections(audit_response_id);
CREATE INDEX idx_client_feedback_user_id ON client_feedback(user_id);
CREATE INDEX idx_client_feedback_session_id ON client_feedback(audit_session_id);
CREATE INDEX idx_admin_notifications_is_read ON admin_notifications(is_read);
CREATE INDEX idx_admin_notifications_user_id ON admin_notifications(related_user_id);
CREATE INDEX idx_admin_permissions_user_id ON admin_permissions(admin_user_id);
CREATE INDEX idx_client_assignments_member_id ON client_assignments(team_member_id);
