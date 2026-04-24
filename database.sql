-- SCHOOL MANAGEMENT SYSTEM — MySQL Schema
CREATE DATABASE IF NOT EXISTS school_mgmt CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE school_mgmt;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(120) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','teacher','student','accountant') DEFAULT 'student',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE countries (
    id SMALLINT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL,
    code CHAR(3)
);

CREATE TABLE students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    student_code VARCHAR(30) UNIQUE,
    first_name VARCHAR(80) NOT NULL,
    last_name VARCHAR(80) NOT NULL,
    dob DATE,
    gender ENUM('Male','Female','Other'),
    nationality VARCHAR(80),
    country_id SMALLINT,
    passport_no VARCHAR(40),
    visa_type VARCHAR(40),
    visa_expiry DATE,
    phone VARCHAR(30),
    address TEXT,
    emergency_contact VARCHAR(120),
    emergency_phone VARCHAR(30),
    enrollment_date DATE DEFAULT (CURRENT_DATE),
    status ENUM('Active','Inactive','Graduated','Suspended') DEFAULT 'Active',
    photo VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (country_id) REFERENCES countries(id)
);

CREATE TABLE teachers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    teacher_code VARCHAR(30) UNIQUE,
    first_name VARCHAR(80) NOT NULL,
    last_name VARCHAR(80) NOT NULL,
    specialization VARCHAR(120),
    phone VARCHAR(30),
    hire_date DATE,
    status ENUM('Active','Inactive') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    credits TINYINT DEFAULT 3,
    level ENUM('Beginner','Intermediate','Advanced','All'),
    category VARCHAR(80),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE academic_years (
    id INT AUTO_INCREMENT PRIMARY KEY,
    label VARCHAR(20) NOT NULL,
    start_date DATE,
    end_date DATE,
    is_current TINYINT(1) DEFAULT 0
);

CREATE TABLE classes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    teacher_id INT NOT NULL,
    academic_year_id INT NOT NULL,
    section VARCHAR(20),
    room VARCHAR(40),
    schedule VARCHAR(120),
    max_students SMALLINT DEFAULT 30,
    status ENUM('Open','Closed','Completed') DEFAULT 'Open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id),
    FOREIGN KEY (teacher_id) REFERENCES teachers(id),
    FOREIGN KEY (academic_year_id) REFERENCES academic_years(id)
);

CREATE TABLE enrollments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    class_id INT NOT NULL,
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('Enrolled','Dropped','Completed') DEFAULT 'Enrolled',
    UNIQUE KEY uq_enroll (student_id, class_id),
    FOREIGN KEY (student_id) REFERENCES students(id),
    FOREIGN KEY (class_id) REFERENCES classes(id)
);

CREATE TABLE exams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    exam_date DATE,
    start_time TIME,
    duration SMALLINT COMMENT 'minutes',
    total_marks DECIMAL(6,2) DEFAULT 100,
    pass_marks DECIMAL(6,2) DEFAULT 50,
    type ENUM('Quiz','Midterm','Final','Assignment','Project') DEFAULT 'Midterm',
    room VARCHAR(40),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(id)
);

CREATE TABLE grades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    enrollment_id INT NOT NULL,
    exam_id INT NOT NULL,
    marks_obtained DECIMAL(6,2),
    grade_letter VARCHAR(5),
    remarks TEXT,
    graded_by INT,
    graded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_grade (enrollment_id, exam_id),
    FOREIGN KEY (enrollment_id) REFERENCES enrollments(id),
    FOREIGN KEY (exam_id) REFERENCES exams(id),
    FOREIGN KEY (graded_by) REFERENCES users(id)
);

CREATE TABLE fee_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency CHAR(3) DEFAULT 'USD',
    description TEXT,
    is_active TINYINT(1) DEFAULT 1
);

CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    fee_type_id INT NOT NULL,
    academic_year_id INT,
    amount_due DECIMAL(10,2) NOT NULL,
    amount_paid DECIMAL(10,2) DEFAULT 0,
    due_date DATE,
    paid_date DATE,
    method ENUM('Cash','Bank Transfer','Card','Online','Cheque') DEFAULT 'Cash',
    reference_no VARCHAR(80),
    status ENUM('Pending','Partial','Paid','Overdue','Waived') DEFAULT 'Pending',
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id),
    FOREIGN KEY (fee_type_id) REFERENCES fee_types(id),
    FOREIGN KEY (academic_year_id) REFERENCES academic_years(id)
);

CREATE TABLE attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    enrollment_id INT NOT NULL,
    date DATE NOT NULL,
    status ENUM('Present','Absent','Late','Excused') DEFAULT 'Present',
    UNIQUE KEY uq_att (enrollment_id, date),
    FOREIGN KEY (enrollment_id) REFERENCES enrollments(id)
);

CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(150),
    message TEXT,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Seed Data
INSERT INTO users (name,email,password,role) VALUES
('Admin User','admin@school.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','admin'),
('John Smith','teacher@school.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','teacher'),
('Alice Johnson','student@school.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','student');

INSERT INTO countries (name,code) VALUES
('Ethiopia','ETH'),('United States','USA'),('United Kingdom','GBR'),
('Kenya','KEN'),('Nigeria','NGA'),('India','IND'),('China','CHN'),
('Germany','DEU'),('France','FRA'),('Canada','CAN'),('Australia','AUS'),
('Brazil','BRA'),('South Africa','ZAF'),('Japan','JPN'),('UAE','ARE');

INSERT INTO academic_years (label,start_date,end_date,is_current) VALUES
('2024-2025','2024-09-01','2025-06-30',0),
('2025-2026','2025-09-01','2026-06-30',1);

INSERT INTO fee_types (name,amount,currency,description) VALUES
('Tuition Fee',5000.00,'USD','Annual tuition fee'),
('Registration Fee',200.00,'USD','One-time registration'),
('Library Fee',50.00,'USD','Annual library access'),
('Lab Fee',150.00,'USD','Laboratory usage fee'),
('International Student Fee',500.00,'USD','International student surcharge'),
('Exam Fee',100.00,'USD','Per semester exam fee');

INSERT INTO courses (code,name,credits,level,category) VALUES
('CS101','Introduction to Computer Science',3,'Beginner','Technology'),
('MATH201','Calculus I',4,'Intermediate','Mathematics'),
('ENG101','English Composition',3,'Beginner','Language'),
('BUS301','Business Management',3,'Advanced','Business'),
('SCI201','General Physics',4,'Intermediate','Science'),
('ART101','Introduction to Fine Arts',2,'Beginner','Arts'),
('CS201','Data Structures & Algorithms',3,'Intermediate','Technology'),
('ECON101','Principles of Economics',3,'Beginner','Business');
