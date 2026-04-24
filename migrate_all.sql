-- ═══════════════════════════════════════════════════════════════
-- MASTER MIGRATION — EduManage Pro
-- Combines all migrations (1-7) into one file
-- Run this on a FRESH database only
-- ═══════════════════════════════════════════════════════════════

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = '';

-- ═══ FROM migrate.sql ═══
-- ═══════════════════════════════════════════════════════════
-- MIGRATION: Branches, Grade Config, Messaging, Assignments,
--            Certificates, Teacher Assignment, Pass/Fail
-- ═══════════════════════════════════════════════════════════
-- 1. SCHOOL BRANCHES
CREATE TABLE IF NOT EXISTS branches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    code VARCHAR(20) UNIQUE NOT NULL,
    address TEXT,
    phone VARCHAR(30),
    email VARCHAR(120),
    principal VARCHAR(120),
    is_main TINYINT(1) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Add branch_id to key tables
ALTER TABLE students  ADD COLUMN IF NOT EXISTS branch_id INT NULL AFTER user_id,
                      ADD FOREIGN KEY IF NOT EXISTS fk_stu_branch (branch_id) REFERENCES branches(id);
ALTER TABLE teachers  ADD COLUMN IF NOT EXISTS branch_id INT NULL AFTER user_id,
                      ADD FOREIGN KEY IF NOT EXISTS fk_tch_branch (branch_id) REFERENCES branches(id);
ALTER TABLE classes   ADD COLUMN IF NOT EXISTS branch_id INT NULL AFTER academic_year_id,
                      ADD FOREIGN KEY IF NOT EXISTS fk_cls_branch (branch_id) REFERENCES branches(id);
ALTER TABLE users     ADD COLUMN IF NOT EXISTS branch_id INT NULL AFTER role;

-- 2. CONFIGURABLE GRADE SCALE
CREATE TABLE IF NOT EXISTS grade_scales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL,
    is_default TINYINT(1) DEFAULT 0,
    pass_percentage DECIMAL(5,2) DEFAULT 50.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS grade_scale_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    scale_id INT NOT NULL,
    grade_letter VARCHAR(5) NOT NULL,
    min_pct DECIMAL(5,2) NOT NULL,
    max_pct DECIMAL(5,2) NOT NULL,
    gpa_points DECIMAL(3,2) DEFAULT 0.00,
    description VARCHAR(50),
    FOREIGN KEY (scale_id) REFERENCES grade_scales(id) ON DELETE CASCADE
);

-- Link grade scale to class (optional override)
ALTER TABLE classes ADD COLUMN IF NOT EXISTS grade_scale_id INT NULL,
                    ADD FOREIGN KEY IF NOT EXISTS fk_cls_scale (grade_scale_id) REFERENCES grade_scales(id);

-- 3. MESSAGING / GROUP MESSAGES
CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    subject VARCHAR(200),
    body TEXT NOT NULL,
    recipient_type ENUM('user','group_students','group_teachers','group_class','broadcast') DEFAULT 'user',
    class_id INT NULL,
    branch_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id),
    FOREIGN KEY (class_id) REFERENCES classes(id),
    FOREIGN KEY (branch_id) REFERENCES branches(id)
);

CREATE TABLE IF NOT EXISTS message_recipients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT NOT NULL,
    user_id INT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    read_at TIMESTAMP NULL,
    UNIQUE KEY uq_msg_user (message_id, user_id),
    FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- 4. ASSIGNMENTS
CREATE TABLE IF NOT EXISTS assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    teacher_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    due_date DATE,
    due_time TIME,
    total_marks DECIMAL(6,2) DEFAULT 100,
    pass_marks DECIMAL(6,2) DEFAULT 50,
    allow_late TINYINT(1) DEFAULT 0,
    status ENUM('Draft','Published','Closed') DEFAULT 'Published',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(id),
    FOREIGN KEY (teacher_id) REFERENCES teachers(id)
);

CREATE TABLE IF NOT EXISTS assignment_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT NOT NULL,
    student_id INT NOT NULL,
    submission_text TEXT,
    file_path VARCHAR(255),
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    marks_obtained DECIMAL(6,2) NULL,
    grade_letter VARCHAR(5) NULL,
    feedback TEXT,
    graded_by INT NULL,
    graded_at TIMESTAMP NULL,
    status ENUM('Submitted','Late','Graded','Missing') DEFAULT 'Submitted',
    UNIQUE KEY uq_sub (assignment_id, student_id),
    FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id),
    FOREIGN KEY (graded_by) REFERENCES users(id)
);

-- 5. YEAR-END RESULTS (Pass/Fail per student per academic year)
CREATE TABLE IF NOT EXISTS year_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    academic_year_id INT NOT NULL,
    branch_id INT NULL,
    total_subjects INT DEFAULT 0,
    passed_subjects INT DEFAULT 0,
    failed_subjects INT DEFAULT 0,
    overall_pct DECIMAL(5,2),
    gpa DECIMAL(3,2),
    result ENUM('Pass','Fail','Incomplete','Distinction','Merit') DEFAULT 'Incomplete',
    remarks TEXT,
    generated_by INT,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_yr (student_id, academic_year_id),
    FOREIGN KEY (student_id) REFERENCES students(id),
    FOREIGN KEY (academic_year_id) REFERENCES academic_years(id)
);

-- 6. CERTIFICATES
CREATE TABLE IF NOT EXISTS certificates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    type ENUM('Completion','Achievement','Participation','Distinction','Custom') DEFAULT 'Completion',
    title VARCHAR(200),
    academic_year_id INT NULL,
    issued_date DATE,
    issued_by INT,
    certificate_no VARCHAR(50) UNIQUE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id),
    FOREIGN KEY (academic_year_id) REFERENCES academic_years(id),
    FOREIGN KEY (issued_by) REFERENCES users(id)
);

-- 7. TEACHER CLASS ASSIGNMENTS (explicit many-to-many for easy management)
-- (classes table already has teacher_id, this is for co-teachers / assistants)
CREATE TABLE IF NOT EXISTS class_teachers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    teacher_id INT NOT NULL,
    role ENUM('Primary','Assistant','Substitute') DEFAULT 'Primary',
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_ct (class_id, teacher_id),
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id)
);

-- Seed: default grade scale (international standard)
INSERT IGNORE INTO grade_scales (id, name, is_default, pass_percentage) VALUES (1, 'Standard International Scale', 1, 50.00);
INSERT IGNORE INTO grade_scale_items (scale_id, grade_letter, min_pct, max_pct, gpa_points, description) VALUES
(1,'A+', 90, 100, 4.00, 'Outstanding'),
(1,'A',  85,  89, 4.00, 'Excellent'),
(1,'A-', 80,  84, 3.70, 'Very Good'),
(1,'B+', 75,  79, 3.30, 'Good'),
(1,'B',  70,  74, 3.00, 'Above Average'),
(1,'B-', 65,  69, 2.70, 'Average'),
(1,'C+', 60,  64, 2.30, 'Satisfactory'),
(1,'C',  55,  59, 2.00, 'Pass'),
(1,'D',  50,  54, 1.00, 'Minimum Pass'),
(1,'F',   0,  49, 0.00, 'Fail');

-- Seed: main branch
INSERT IGNORE INTO branches (id, name, code, is_main, is_active) VALUES (1, 'Main Campus', 'MAIN', 1, 1);

-- ═══ FROM migrate2.sql ═══
-- Activity log
CREATE TABLE IF NOT EXISTS activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    role VARCHAR(20),
    action VARCHAR(100) NOT NULL,
    description TEXT,
    ip VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Notice board
CREATE TABLE IF NOT EXISTS notices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    body TEXT NOT NULL,
    audience ENUM('all','teachers','students','admin') DEFAULT 'all',
    posted_by INT NOT NULL,
    post_date DATE NOT NULL,
    expiry_date DATE,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (posted_by) REFERENCES users(id)
);

-- Login attempts (rate limiting)
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(120),
    ip VARCHAR(45),
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Teacher work log (what teacher is doing)
ALTER TABLE teachers
    ADD COLUMN IF NOT EXISTS start_date DATE NULL AFTER hire_date,
    ADD COLUMN IF NOT EXISTS contract_type ENUM('Full-time','Part-time','Contract','Visiting') DEFAULT 'Full-time' AFTER start_date,
    ADD COLUMN IF NOT EXISTS qualifications TEXT NULL AFTER contract_type,
    ADD COLUMN IF NOT EXISTS bio TEXT NULL AFTER qualifications;

-- ═══ FROM migrate3.sql ═══
-- ═══════════════════════════════════════════════════════════
-- MIGRATION 3: Super Admin Role & Branch Enhancements
-- ═══════════════════════════════════════════════════════════
-- Add super_admin to users role ENUM (safe: only if column exists)
ALTER TABLE `school_mgmt`.`users` MODIFY COLUMN `role` ENUM('super_admin','admin','teacher','student','accountant') DEFAULT 'student';

-- Add logo and description to branches
ALTER TABLE branches
    ADD COLUMN IF NOT EXISTS description TEXT NULL AFTER email,
    ADD COLUMN IF NOT EXISTS logo VARCHAR(255) NULL AFTER description,
    ADD COLUMN IF NOT EXISTS established_date DATE NULL AFTER logo,
    ADD COLUMN IF NOT EXISTS capacity INT DEFAULT 0 AFTER established_date;

-- Seed: default super admin (password: password)
INSERT IGNORE INTO users (name, email, password, role, is_active)
VALUES ('Super Admin', 'superadmin@school.com',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        'super_admin', 1);

-- ═══ FROM migrate4.sql ═══
-- ═══════════════════════════════════════════════════════════
-- MIGRATION 4: Exercises Module
-- Each exercise belongs to a class (which has exactly one teacher).
-- Students enrolled in that class can submit answers.
-- ═══════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS exercises (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    instructions TEXT,
    due_date DATE NULL,
    due_time TIME NULL,
    total_marks DECIMAL(6,2) DEFAULT 100,
    pass_marks DECIMAL(6,2) DEFAULT 50,
    allow_late TINYINT(1) DEFAULT 0,
    status ENUM('Draft','Published','Closed') DEFAULT 'Published',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS exercise_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exercise_id INT NOT NULL,
    student_id INT NOT NULL,
    answer TEXT,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    marks_obtained DECIMAL(6,2) NULL,
    grade_letter VARCHAR(5) NULL,
    feedback TEXT NULL,
    graded_by INT NULL,
    graded_at TIMESTAMP NULL,
    status ENUM('Submitted','Late','Graded','Missing') DEFAULT 'Submitted',
    UNIQUE KEY uq_ex_sub (exercise_id, student_id),
    FOREIGN KEY (exercise_id) REFERENCES exercises(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id),
    FOREIGN KEY (graded_by) REFERENCES users(id)
);

-- ═══ FROM migrate5.sql ═══
-- ═══════════════════════════════════════════════════════════
-- MIGRATION 5: Library Management System
-- ═══════════════════════════════════════════════════════════
-- Books catalog
CREATE TABLE IF NOT EXISTS library_books (
    id INT AUTO_INCREMENT PRIMARY KEY,
    isbn VARCHAR(30) UNIQUE,
    title VARCHAR(255) NOT NULL,
    author VARCHAR(200) NOT NULL,
    publisher VARCHAR(150),
    publish_year YEAR,
    edition VARCHAR(30),
    language VARCHAR(50) DEFAULT 'English',
    category VARCHAR(80),
    subject VARCHAR(100),
    description TEXT,
    total_copies SMALLINT DEFAULT 1,
    available_copies SMALLINT DEFAULT 1,
    price DECIMAL(10,2) DEFAULT 0.00,
    currency CHAR(3) DEFAULT 'USD',
    location VARCHAR(80) COMMENT 'Shelf/Row/Section',
    cover_url VARCHAR(255),
    is_active TINYINT(1) DEFAULT 1,
    branch_id INT NULL,
    added_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(id),
    FOREIGN KEY (added_by) REFERENCES users(id)
);

-- Borrow / Return transactions
CREATE TABLE IF NOT EXISTS library_borrows (
    id INT AUTO_INCREMENT PRIMARY KEY,
    book_id INT NOT NULL,
    borrower_type ENUM('student','teacher') NOT NULL,
    student_id INT NULL,
    teacher_id INT NULL,
    borrowed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    due_date DATE NOT NULL,
    returned_at TIMESTAMP NULL,
    status ENUM('Borrowed','Returned','Overdue','Lost') DEFAULT 'Borrowed',
    fine_amount DECIMAL(8,2) DEFAULT 0.00,
    fine_paid TINYINT(1) DEFAULT 0,
    notes TEXT,
    issued_by INT NULL,
    returned_to INT NULL,
    FOREIGN KEY (book_id) REFERENCES library_books(id),
    FOREIGN KEY (student_id) REFERENCES students(id),
    FOREIGN KEY (teacher_id) REFERENCES teachers(id),
    FOREIGN KEY (issued_by) REFERENCES users(id),
    FOREIGN KEY (returned_to) REFERENCES users(id)
);

-- Book reservations (queue)
CREATE TABLE IF NOT EXISTS library_reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    book_id INT NOT NULL,
    borrower_type ENUM('student','teacher') NOT NULL,
    student_id INT NULL,
    teacher_id INT NULL,
    reserved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at DATE,
    status ENUM('Pending','Ready','Cancelled','Fulfilled') DEFAULT 'Pending',
    FOREIGN KEY (book_id) REFERENCES library_books(id),
    FOREIGN KEY (student_id) REFERENCES students(id),
    FOREIGN KEY (teacher_id) REFERENCES teachers(id)
);

-- Book requests (students/teachers request books not in catalog)
CREATE TABLE IF NOT EXISTS library_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    borrower_type ENUM('student','teacher') NOT NULL,
    student_id INT NULL,
    teacher_id INT NULL,
    book_title VARCHAR(255) NOT NULL,
    author VARCHAR(255),
    isbn VARCHAR(30),
    reason TEXT,
    status ENUM('Pending','Approved','Rejected','Fulfilled') DEFAULT 'Pending',
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_by INT NULL,
    reviewed_at TIMESTAMP NULL,
    notes TEXT,
    FOREIGN KEY (student_id) REFERENCES students(id),
    FOREIGN KEY (teacher_id) REFERENCES teachers(id),
    FOREIGN KEY (reviewed_by) REFERENCES users(id)
);

-- ═══ FROM migrate6.sql ═══
-- ═══════════════════════════════════════════════════════════════
-- Migration 6: Parent Portal, Feedback, Clearance, Transfer, Registrar
-- ═══════════════════════════════════════════════════════════════

-- Parent/Guardian accounts linked to students
CREATE TABLE IF NOT EXISTS parents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    phone VARCHAR(30),
    email VARCHAR(150),
    relationship ENUM('Father','Mother','Guardian','Other') DEFAULT 'Guardian',
    occupation VARCHAR(100),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS student_parents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    parent_id INT NOT NULL,
    is_primary TINYINT(1) DEFAULT 0,
    UNIQUE KEY (student_id, parent_id),
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES parents(id) ON DELETE CASCADE
);

-- Student feedback from teachers (per term/semester)
CREATE TABLE IF NOT EXISTS student_feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    teacher_id INT NOT NULL,
    class_id INT NOT NULL,
    academic_year_id INT NOT NULL,
    semester ENUM('Semester 1','Semester 2','Full Year') DEFAULT 'Semester 1',
    behavior_rating TINYINT COMMENT '1-5',
    participation_rating TINYINT COMMENT '1-5',
    effort_rating TINYINT COMMENT '1-5',
    comments TEXT,
    strengths TEXT,
    areas_for_improvement TEXT,
    recommendation ENUM('Excellent','Good','Satisfactory','Needs Improvement','Unsatisfactory') DEFAULT 'Good',
    is_shared_with_parent TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE CASCADE
);

-- Semester/term grade reports (archived)
CREATE TABLE IF NOT EXISTS grade_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    academic_year_id INT NOT NULL,
    semester ENUM('Semester 1','Semester 2','Full Year') DEFAULT 'Semester 1',
    total_subjects INT DEFAULT 0,
    passed_subjects INT DEFAULT 0,
    failed_subjects INT DEFAULT 0,
    overall_pct DECIMAL(5,2) DEFAULT 0,
    gpa DECIMAL(3,2) DEFAULT 0,
    rank_in_class INT,
    total_in_class INT,
    result ENUM('Pass','Fail','Distinction','Merit','Incomplete') DEFAULT 'Incomplete',
    remarks TEXT,
    generated_by INT,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_published TINYINT(1) DEFAULT 0,
    published_at TIMESTAMP NULL,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE CASCADE
);

-- Clearance system
CREATE TABLE IF NOT EXISTS clearance_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    reason ENUM('Graduation','Transfer','Withdrawal','Suspension','Other') DEFAULT 'Transfer',
    initiated_by INT NOT NULL COMMENT 'user_id',
    initiated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('Pending','In Progress','Completed','Rejected') DEFAULT 'Pending',
    notes TEXT,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS clearance_departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description VARCHAR(255),
    responsible_role ENUM('admin','librarian','teacher','accountant','registrar') DEFAULT 'admin',
    sort_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1
);

CREATE TABLE IF NOT EXISTS clearance_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    clearance_id INT NOT NULL,
    department_id INT NOT NULL,
    status ENUM('Pending','Cleared','Rejected') DEFAULT 'Pending',
    signed_by INT NULL COMMENT 'user_id',
    signed_at TIMESTAMP NULL,
    remarks TEXT,
    properties_returned TEXT COMMENT 'List of items returned',
    FOREIGN KEY (clearance_id) REFERENCES clearance_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES clearance_departments(id) ON DELETE CASCADE
);

-- Transfer certificates
CREATE TABLE IF NOT EXISTS transfer_certificates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    clearance_id INT NULL,
    destination_school VARCHAR(255),
    transfer_date DATE,
    reason TEXT,
    academic_summary TEXT COMMENT 'JSON snapshot of grades',
    issued_by INT NOT NULL COMMENT 'user_id',
    issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    certificate_no VARCHAR(50) UNIQUE,
    status ENUM('Draft','Issued','Revoked') DEFAULT 'Draft',
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

-- Registrar: semester enrollment records
CREATE TABLE IF NOT EXISTS semester_registrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    academic_year_id INT NOT NULL,
    semester ENUM('Semester 1','Semester 2','Full Year') DEFAULT 'Semester 1',
    registration_date DATE NOT NULL,
    registered_by INT NOT NULL COMMENT 'user_id',
    status ENUM('Registered','Withdrawn','Deferred') DEFAULT 'Registered',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (student_id, academic_year_id, semester),
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE CASCADE
);

-- Payment reminders log
CREATE TABLE IF NOT EXISTS payment_reminders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    payment_id INT NULL,
    sent_by INT NOT NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    method ENUM('notification','email','sms') DEFAULT 'notification',
    message TEXT,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

-- Default clearance departments
INSERT IGNORE INTO clearance_departments (name, description, responsible_role, sort_order) VALUES
('Academic Office', 'Verify academic records and grades', 'admin', 1),
('Library', 'Return all borrowed books and clear fines', 'librarian', 2),
('Finance', 'Clear all outstanding fees and payments', 'admin', 3),
('Student Affairs', 'Return ID card, uniform and school property', 'admin', 4),
('Dormitory', 'Return room keys and clear dormitory dues', 'admin', 5),
('Sports & Activities', 'Return sports equipment and club materials', 'admin', 6);

-- Add parent role to users
ALTER TABLE users MODIFY COLUMN role ENUM('super_admin','admin','teacher','student','librarian','parent','registrar','accountant') DEFAULT 'student';

-- Calendar events
CREATE TABLE IF NOT EXISTS calendar_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    event_date DATE NOT NULL,
    end_date DATE NULL,
    event_type ENUM('General','Holiday','Exam','Meeting','Event') DEFAULT 'General',
    audience ENUM('all','students','teachers','staff') DEFAULT 'all',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Add is_archived to academic_years for long-term archive support
ALTER TABLE academic_years ADD COLUMN IF NOT EXISTS is_archived TINYINT(1) DEFAULT 0;

-- Add branch_id to students if missing
ALTER TABLE students ADD COLUMN IF NOT EXISTS branch_id INT NULL;

-- ═══ FROM migrate7.sql ═══
-- ═══════════════════════════════════════════════════════════════
-- Migration 7: Online Examination System
-- ═══════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS online_exams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    class_id INT NULL,
    created_by INT NOT NULL,
    academic_year_id INT NOT NULL,
    exam_type ENUM('MCQ','Short Answer','Mixed') DEFAULT 'MCQ',
    duration_minutes INT DEFAULT 60,
    total_marks INT DEFAULT 100,
    pass_marks INT DEFAULT 50,
    start_datetime DATETIME NULL,
    end_datetime DATETIME NULL,
    shuffle_questions TINYINT(1) DEFAULT 1,
    show_result_immediately TINYINT(1) DEFAULT 1,
    max_attempts TINYINT DEFAULT 1,
    status ENUM('Draft','Published','Active','Closed') DEFAULT 'Draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE SET NULL,
    FOREIGN KEY (academic_year_id) REFERENCES academic_years(id)
);

CREATE TABLE IF NOT EXISTS online_exam_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exam_id INT NOT NULL,
    question_text TEXT NOT NULL,
    question_type ENUM('MCQ','True/False','Short Answer') DEFAULT 'MCQ',
    marks INT DEFAULT 1,
    explanation TEXT,
    sort_order INT DEFAULT 0,
    FOREIGN KEY (exam_id) REFERENCES online_exams(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS online_exam_options (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question_id INT NOT NULL,
    option_text TEXT NOT NULL,
    is_correct TINYINT(1) DEFAULT 0,
    sort_order INT DEFAULT 0,
    FOREIGN KEY (question_id) REFERENCES online_exam_questions(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS online_exam_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exam_id INT NOT NULL,
    student_id INT NOT NULL,
    started_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    submitted_at DATETIME NULL,
    score DECIMAL(8,2) DEFAULT 0,
    total_marks INT DEFAULT 0,
    percentage DECIMAL(5,2) DEFAULT 0,
    grade_letter VARCHAR(5),
    status ENUM('In Progress','Submitted','Graded','Timed Out') DEFAULT 'In Progress',
    ip_address VARCHAR(45),
    UNIQUE KEY uq_attempt (exam_id, student_id),
    FOREIGN KEY (exam_id) REFERENCES online_exams(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS online_exam_answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    attempt_id INT NOT NULL,
    question_id INT NOT NULL,
    selected_option_id INT NULL,
    text_answer TEXT NULL,
    is_correct TINYINT(1) NULL,
    marks_awarded DECIMAL(5,2) DEFAULT 0,
    FOREIGN KEY (attempt_id) REFERENCES online_exam_attempts(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES online_exam_questions(id) ON DELETE CASCADE
);

-- Timetable slots
CREATE TABLE IF NOT EXISTS timetable_slots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    day_of_week ENUM('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    room VARCHAR(50),
    academic_year_id INT NOT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_slot (class_id, day_of_week, start_time),
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (academic_year_id) REFERENCES academic_years(id)
);

-- Calendar events
CREATE TABLE IF NOT EXISTS calendar_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    event_date DATE NOT NULL,
    end_date DATE NULL,
    event_type ENUM('General','Holiday','Exam','Meeting','Event') DEFAULT 'General',
    audience ENUM('all','students','teachers','staff') DEFAULT 'all',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Disciplinary records
CREATE TABLE IF NOT EXISTS disciplinary_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    incident_date DATE NOT NULL,
    incident_type ENUM('Misconduct','Cheating','Bullying','Vandalism','Absence','Dress Code','Other') DEFAULT 'Other',
    description TEXT NOT NULL,
    action_taken ENUM('Warning','Suspension','Expulsion','Counseling','Parent Meeting','Community Service','Other') DEFAULT 'Warning',
    suspension_days INT DEFAULT 0,
    reported_by INT NOT NULL,
    reviewed_by INT NULL,
    status ENUM('Open','Under Review','Resolved','Appealed') DEFAULT 'Open',
    resolution_notes TEXT,
    parent_notified TINYINT(1) DEFAULT 0,
    parent_notified_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

-- Alumni
CREATE TABLE IF NOT EXISTS alumni (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    graduation_year INT NOT NULL,
    graduation_date DATE NULL,
    final_gpa DECIMAL(3,2) NULL,
    degree_awarded VARCHAR(150) NULL,
    current_employer VARCHAR(255) NULL,
    current_position VARCHAR(150) NULL,
    current_city VARCHAR(100) NULL,
    current_country VARCHAR(100) NULL,
    linkedin_url VARCHAR(255) NULL,
    personal_email VARCHAR(150) NULL,
    phone VARCHAR(30) NULL,
    is_verified TINYINT(1) DEFAULT 0,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_student (student_id),
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

-- Student documents
CREATE TABLE IF NOT EXISTS student_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    document_type ENUM('ID Card','Birth Certificate','Passport','Transcript','Medical','Photo','Other') DEFAULT 'Other',
    title VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT NULL,
    uploaded_by INT NOT NULL,
    is_verified TINYINT(1) DEFAULT 0,
    verified_by INT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);


-- ═══════════════════════════════════════════════════════════════
-- ── LIBRARY SETTINGS ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS library_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fine_per_day DECIMAL(8,2) DEFAULT 0.50,
    max_borrow_days INT DEFAULT 14,
    max_books_student INT DEFAULT 3,
    max_books_teacher INT DEFAULT 5,
    max_renewals INT DEFAULT 2,
    lost_penalty_multiplier DECIMAL(5,2) DEFAULT 1.5,
    lost_after_days INT DEFAULT 30,
    currency VARCHAR(10) DEFAULT 'USD'
);

-- RE-ENABLE FOREIGN KEY CHECKS
-- ═══════════════════════════════════════════════════════════════
SET FOREIGN_KEY_CHECKS = 1;
