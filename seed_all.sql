-- ═══════════════════════════════════════════════════════════════
-- MASTER SEED DATA — EduManage Pro
-- Run AFTER migrate_all.sql
-- Includes: base data, test data, 5 students + 5 teachers
-- ═══════════════════════════════════════════════════════════════

SET FOREIGN_KEY_CHECKS = 0;

-- ═══ BASE DATA ═══
-- TEST SEED DATA — 5 records per table
-- Run this in phpMyAdmin after all migrations
-- ── Academic Years ────────────────────────────────────────────
INSERT IGNORE INTO academic_years (id, label, start_date, end_date, is_current) VALUES
(1, '2022-2023', '2022-09-01', '2023-06-30', 0),
(2, '2023-2024', '2023-09-01', '2024-06-30', 0),
(3, '2024-2025', '2024-09-01', '2025-06-30', 1),
(4, '2025-2026', '2025-09-01', '2026-06-30', 0),
(5, '2026-2027', '2026-09-01', '2027-06-30', 0);

-- ── Branches ─────────────────────────────────────────────────
INSERT IGNORE INTO branches (id, name, code, address, phone, email, principal, is_main, is_active) VALUES
(1, 'Main Campus', 'MAIN', 'Addis Ababa, Bole Road', '+251911000001', 'main@school.com', 'Dr. Abebe Girma', 1, 1),
(2, 'North Branch', 'NORTH', 'Addis Ababa, Piassa', '+251911000002', 'north@school.com', 'Ato Kebede Alemu', 0, 1),
(3, 'South Branch', 'SOUTH', 'Addis Ababa, Megenagna', '+251911000003', 'south@school.com', 'W/ro Tigist Haile', 0, 1),
(4, 'East Branch', 'EAST', 'Dire Dawa', '+251911000004', 'east@school.com', 'Ato Dawit Bekele', 0, 1),
(5, 'West Branch', 'WEST', 'Bahir Dar', '+251911000005', 'west@school.com', 'Dr. Meron Tadesse', 0, 1);

-- ── Countries ────────────────────────────────────────────────
INSERT IGNORE INTO countries (id, name, code) VALUES
(1, 'Ethiopia', 'ET'),
(2, 'Kenya', 'KE'),
(3, 'Uganda', 'UG'),
(4, 'Tanzania', 'TZ'),
(5, 'Somalia', 'SO');

-- ── Courses ──────────────────────────────────────────────────
INSERT IGNORE INTO courses (id, name, code, credits, description, is_active) VALUES
(1, 'Mathematics', 'MATH101', 3, 'Fundamentals of algebra, geometry and calculus', 1),
(2, 'English Language', 'ENG101', 3, 'Reading, writing and communication skills', 1),
(3, 'Physics', 'PHY101', 4, 'Mechanics, thermodynamics and electromagnetism', 1),
(4, 'Chemistry', 'CHEM101', 4, 'Organic and inorganic chemistry fundamentals', 1),
(5, 'Biology', 'BIO101', 3, 'Cell biology, genetics and ecology', 1);

-- ── Fee Types ────────────────────────────────────────────────
INSERT IGNORE INTO fee_types (id, name, amount, description, is_active) VALUES
(1, 'Tuition Fee', 5000.00, 'Semester tuition fee', 1),
(2, 'Registration Fee', 500.00, 'One-time registration fee', 1),
(3, 'Library Fee', 200.00, 'Annual library access fee', 1),
(4, 'Lab Fee', 300.00, 'Science laboratory usage fee', 1),
(5, 'Activity Fee', 150.00, 'Sports and extracurricular activities', 1);

-- ── Grade Scales ─────────────────────────────────────────────
INSERT IGNORE INTO grade_scales (id, name, pass_percentage, is_default) VALUES
(1, 'Standard Scale', 50.00, 1);

INSERT IGNORE INTO grade_scale_items (scale_id, grade_letter, min_pct, max_pct, gpa_points, description) VALUES
(1, 'A+', 95, 100, 4.0, 'Outstanding'),
(1, 'A',  90, 94,  4.0, 'Excellent'),
(1, 'A-', 85, 89,  3.7, 'Very Good'),
(1, 'B+', 80, 84,  3.3, 'Good'),
(1, 'B',  75, 79,  3.0, 'Above Average'),
(1, 'B-', 70, 74,  2.7, 'Average'),
(1, 'C+', 65, 69,  2.3, 'Below Average'),
(1, 'C',  60, 64,  2.0, 'Satisfactory'),
(1, 'D',  50, 59,  1.0, 'Pass'),
(1, 'F',  0,  49,  0.0, 'Fail');

-- ── Library Settings handled by migrate5.sql ─────────────────

-- ── Library Books ────────────────────────────────────────────
INSERT IGNORE INTO library_books (id, isbn, title, author, publisher, publish_year, language, category, subject, total_copies, available_copies, price, currency, location, is_active, added_by) VALUES
(1, '9780061965784', 'The Alchemist', 'Paulo Coelho', 'HarperOne', 1988, 'English', 'Fiction', 'Literature', 5, 4, 12.99, 'USD', 'A-1', 1, 1),
(2, '9780743273565', 'The Great Gatsby', 'F. Scott Fitzgerald', 'Scribner', 1925, 'English', 'Fiction', 'Literature', 3, 3, 9.99, 'USD', 'A-2', 1, 1),
(3, '9780131103627', 'The C Programming Language', 'Kernighan & Ritchie', 'Prentice Hall', 1988, 'English', 'Computers', 'Programming', 4, 4, 45.00, 'USD', 'B-1', 1, 1),
(4, '9780201633610', 'Design Patterns', 'Gang of Four', 'Addison-Wesley', 1994, 'English', 'Computers', 'Software Engineering', 2, 2, 55.00, 'USD', 'B-2', 1, 1),
(5, '9780385333481', 'The Handmaid''s Tale', 'Margaret Atwood', 'Anchor Books', 1985, 'English', 'Fiction', 'Dystopian', 3, 3, 14.99, 'USD', 'A-3', 1, 1);

-- ── Calendar Events (requires migrate6.sql first) ────────────
INSERT IGNORE INTO calendar_events (id, title, description, event_date, end_date, event_type, audience, created_by) VALUES
(1, 'Semester 1 Begins', 'First day of Semester 1 classes', '2024-09-02', '2024-09-02', 'Event', 'all', 1),
(2, 'Mid-Term Exams', 'Mid-semester examination period', '2024-10-14', '2024-10-18', 'Exam', 'students', 1),
(3, 'Ethiopian New Year Holiday', 'Enkutatash - public holiday', '2024-09-11', '2024-09-11', 'Holiday', 'all', 1),
(4, 'Parent-Teacher Meeting', 'Quarterly parent-teacher conference', '2024-11-15', '2024-11-15', 'Meeting', 'all', 1),
(5, 'Final Exams Semester 1', 'End of semester examinations', '2025-01-13', '2025-01-17', 'Exam', 'students', 1);

-- ── Clearance Departments ────────────────────────────────────
INSERT IGNORE INTO clearance_departments (id, name, description, responsible_role, sort_order, is_active) VALUES
(1, 'Academic Office', 'Verify academic records and grades', 'admin', 1, 1),
(2, 'Library', 'Return all borrowed books and clear fines', 'librarian', 2, 1),
(3, 'Finance', 'Clear all outstanding fees and payments', 'admin', 3, 1),
(4, 'Student Affairs', 'Return ID card and school property', 'admin', 4, 1),
(5, 'Dormitory', 'Return room keys and clear dues', 'admin', 5, 1);

-- ═══ CLASSES, EXAMS, NOTICES, ASSIGNMENTS, PAYMENTS ═══
-- TEST SEED DATA PART 2 — Requires users/students/teachers to exist
-- Run AFTER seed_test_data.sql and after you have users in DB
-- ── Classes (uses existing teacher IDs and course IDs) ────────
-- Assumes teacher user IDs 2,5,8,9,20 exist from your current data
INSERT IGNORE INTO classes (id, course_id, teacher_id, academic_year_id, section, room, schedule, status, branch_id) VALUES
(1, 1, 1, 3, 'A', 'Room 101', 'Mon/Wed 8:00-9:30', 'Open', 1),
(2, 2, 1, 3, 'B', 'Room 102', 'Tue/Thu 8:00-9:30', 'Open', 1),
(3, 3, 1, 3, 'A', 'Lab 201', 'Mon/Wed 10:00-11:30', 'Open', 1),
(4, 4, 1, 3, 'A', 'Lab 202', 'Tue/Thu 10:00-11:30', 'Open', 1),
(5, 5, 1, 3, 'B', 'Room 103', 'Fri 8:00-11:00', 'Open', 1);

-- ── Exams ────────────────────────────────────────────────────
INSERT IGNORE INTO exams (id, class_id, title, type, exam_date, start_time, duration, total_marks, pass_marks, room) VALUES
(1, 1, 'Math Midterm', 'Midterm', '2024-10-15', '09:00:00', 120, 100, 50, 'Hall A'),
(2, 2, 'English Essay', 'Quiz', '2024-10-16', '09:00:00', 60, 50, 25, 'Room 102'),
(3, 3, 'Physics Lab Test', 'Practical', '2024-10-17', '10:00:00', 90, 100, 50, 'Lab 201'),
(4, 4, 'Chemistry Final', 'Final', '2025-01-14', '09:00:00', 180, 100, 50, 'Hall B'),
(5, 5, 'Biology Quiz 1', 'Quiz', '2024-10-18', '08:00:00', 45, 30, 15, 'Room 103');

-- ── Notices ──────────────────────────────────────────────────
INSERT IGNORE INTO notices (id, title, body, audience, posted_by, post_date, expiry_date, is_active) VALUES
(1, 'Welcome Back Students!', 'We are excited to welcome all students back for the new semester. Please check your class schedules.', 'all', 1, '2024-09-02', '2024-09-30', 1),
(2, 'Library Hours Extended', 'The library will now be open until 8 PM on weekdays during exam period.', 'all', 1, '2024-10-10', '2024-10-31', 1),
(3, 'Fee Payment Deadline', 'All outstanding fees must be paid by October 31st to avoid late penalties.', 'students', 1, '2024-10-01', '2024-10-31', 1),
(4, 'Staff Meeting', 'All teaching staff are required to attend the monthly meeting on Friday at 3 PM.', 'teachers', 1, '2024-10-14', '2024-10-18', 1),
(5, 'Sports Day Announcement', 'Annual sports day will be held on November 20th. All students are encouraged to participate.', 'students', 1, '2024-11-01', '2024-11-20', 1);

-- ── Assignments ──────────────────────────────────────────────
INSERT IGNORE INTO assignments (id, class_id, teacher_id, title, description, due_date, total_marks, pass_marks, status) VALUES
(1, 1, 1, 'Algebra Problem Set', 'Solve problems 1-20 from Chapter 3. Show all working.', '2024-10-20', 100, 50, 'Published'),
(2, 2, 1, 'Essay: My Future Goals', 'Write a 500-word essay about your career aspirations.', '2024-10-22', 50, 25, 'Published'),
(3, 3, 1, 'Newton''s Laws Report', 'Write a lab report on the Newton''s Laws experiment conducted in class.', '2024-10-25', 100, 50, 'Published'),
(4, 4, 1, 'Periodic Table Study', 'Memorize and explain the first 20 elements of the periodic table.', '2024-10-28', 50, 25, 'Published'),
(5, 5, 1, 'Ecosystem Diagram', 'Draw and label a complete food web for a forest ecosystem.', '2024-10-30', 30, 15, 'Published');

-- ── Payments (uses existing student IDs) ─────────────────────
-- Assumes student IDs 1-5 exist
INSERT IGNORE INTO payments (id, student_id, fee_type_id, academic_year_id, amount_due, amount_paid, due_date, status, method, notes, created_by) VALUES
(1, 3, 1, 3, 5000.00, 5000.00, '2024-09-30', 'Paid', 'Bank Transfer', 'Semester 1 tuition', 1),
(2, 3, 2, 3, 500.00, 500.00, '2024-09-15', 'Paid', 'Cash', 'Registration fee', 1),
(3, 3, 3, 3, 200.00, 0.00, '2024-10-31', 'Pending', 'Pending', 'Library fee', 1),
(4, 3, 4, 3, 300.00, 150.00, '2024-10-31', 'Partial', 'Cash', 'Lab fee partial payment', 1),
(5, 3, 5, 3, 150.00, 0.00, '2024-11-30', 'Overdue', 'Pending', 'Activity fee overdue', 1);

-- ═══ STUDENTS & TEACHERS (5 each) ═══
-- SEED: 5 Students + 5 Teachers with full data
-- Password for all: password (hashed)
-- ── User accounts ────────────────────────────────────────────
-- password hash = password_hash('password', PASSWORD_DEFAULT)
INSERT IGNORE INTO users (id, name, email, password, role, branch_id, is_active, created_at) VALUES
-- Teachers
(101, 'Samuel Tesfaye',   'samuel.tesfaye@school.com',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teacher', 1, 1, '2023-08-15 08:00:00'),
(102, 'Hana Girma',       'hana.girma@school.com',       '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teacher', 1, 1, '2023-08-15 08:00:00'),
(103, 'Dawit Bekele',     'dawit.bekele@school.com',     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teacher', 1, 1, '2023-08-15 08:00:00'),
(104, 'Tigist Alemu',     'tigist.alemu@school.com',     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teacher', 1, 1, '2023-08-15 08:00:00'),
(105, 'Kebede Haile',     'kebede.haile@school.com',     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teacher', 1, 1, '2023-08-15 08:00:00'),
-- Students
(201, 'Meron Tadesse',    'meron.tadesse@student.com',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 1, 1, '2024-09-01 09:00:00'),
(202, 'Yonas Abebe',      'yonas.abebe@student.com',     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 1, 1, '2024-09-01 09:00:00'),
(203, 'Selam Worku',      'selam.worku@student.com',     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 1, 1, '2024-09-01 09:00:00'),
(204, 'Biruk Mengistu',   'biruk.mengistu@student.com',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 1, 1, '2024-09-01 09:00:00'),
(205, 'Liya Solomon',     'liya.solomon@student.com',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 2, 1, '2024-09-01 09:00:00'),
-- Parent (demo)
(301, 'Demo Parent',      'parent@school.com',           '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'parent',  1, 1, '2024-09-01 09:00:00');

-- ── Teachers ─────────────────────────────────────────────────
INSERT IGNORE INTO teachers (id, user_id, teacher_code, first_name, last_name, specialization, phone, hire_date, status, created_at) VALUES
(101, 101, 'EMP-TCH-2023-0101', 'Samuel',  'Tesfaye',  'Mathematics & Statistics',     '+251911101001', '2023-08-20', 'Active', '2023-08-20 08:00:00'),
(102, 102, 'EMP-TCH-2023-0102', 'Hana',    'Girma',    'English Language & Literature', '+251911101002', '2023-08-20', 'Active', '2023-08-20 08:00:00'),
(103, 103, 'EMP-TCH-2023-0103', 'Dawit',   'Bekele',   'Physics & Applied Sciences',   '+251911101003', '2023-08-20', 'Active', '2023-08-20 08:00:00'),
(104, 104, 'EMP-TCH-2023-0104', 'Tigist',  'Alemu',    'Chemistry & Biology',          '+251911101004', '2023-08-20', 'Active', '2023-08-20 08:00:00'),
(105, 105, 'EMP-TCH-2023-0105', 'Kebede',  'Haile',    'Computer Science & ICT',       '+251911101005', '2023-08-20', 'Active', '2023-08-20 08:00:00');

-- ── Students ─────────────────────────────────────────────────
INSERT IGNORE INTO students (id, user_id, student_code, branch_id, first_name, last_name, dob, gender, nationality, country_id, passport_no, phone, address, emergency_contact, emergency_phone, enrollment_date, status, created_at) VALUES
(101, 201, 'EMP-STU-2024-0201', 1, 'Meron',  'Tadesse',  '2005-03-14', 'Female', 'Ethiopian', 1, NULL,         '+251922201001', 'Addis Ababa, Bole Sub-city, Woreda 03', 'Tadesse Girma (Father)',  '+251922201010', '2024-09-01', 'Active', '2024-09-01 09:00:00'),
(102, 202, 'EMP-STU-2024-0202', 1, 'Yonas',  'Abebe',    '2004-07-22', 'Male',   'Ethiopian', 1, NULL,         '+251922201002', 'Addis Ababa, Kirkos Sub-city, Woreda 07', 'Abebe Kebede (Father)', '+251922201020', '2024-09-01', 'Active', '2024-09-01 09:00:00'),
(103, 203, 'EMP-STU-2024-0203', 1, 'Selam',  'Worku',    '2005-11-05', 'Female', 'Ethiopian', 1, NULL,         '+251922201003', 'Addis Ababa, Yeka Sub-city, Woreda 11',  'Worku Haile (Father)',   '+251922201030', '2024-09-01', 'Active', '2024-09-01 09:00:00'),
(104, 204, 'EMP-STU-2024-0204', 1, 'Biruk',  'Mengistu', '2004-01-30', 'Male',   'Ethiopian', 1, NULL,         '+251922201004', 'Addis Ababa, Nifas Silk, Woreda 02',     'Mengistu Alemu (Father)','+251922201040', '2024-09-01', 'Active', '2024-09-01 09:00:00'),
(105, 205, 'EMP-STU-2024-0205', 2, 'Liya',   'Solomon',  '2005-06-18', 'Female', 'Kenyan',    2, 'KE12345678', '+251922201005', 'Addis Ababa, Lideta Sub-city, Woreda 05', 'Solomon Tesfaye (Father)','+251922201050', '2024-09-01', 'Active', '2024-09-01 09:00:00');

-- ── Enroll students in classes ───────────────────────────────
-- Assumes classes 1-5 exist from seed_test_data2.sql
INSERT IGNORE INTO enrollments (student_id, class_id, status, enrolled_at) VALUES
(101, 1, 'Enrolled', '2024-09-02 10:00:00'),
(101, 2, 'Enrolled', '2024-09-02 10:00:00'),
(101, 3, 'Enrolled', '2024-09-02 10:00:00'),
(102, 1, 'Enrolled', '2024-09-02 10:00:00'),
(102, 2, 'Enrolled', '2024-09-02 10:00:00'),
(102, 4, 'Enrolled', '2024-09-02 10:00:00'),
(103, 1, 'Enrolled', '2024-09-02 10:00:00'),
(103, 3, 'Enrolled', '2024-09-02 10:00:00'),
(103, 5, 'Enrolled', '2024-09-02 10:00:00'),
(104, 2, 'Enrolled', '2024-09-02 10:00:00'),
(104, 4, 'Enrolled', '2024-09-02 10:00:00'),
(104, 5, 'Enrolled', '2024-09-02 10:00:00'),
(105, 1, 'Enrolled', '2024-09-02 10:00:00'),
(105, 2, 'Enrolled', '2024-09-02 10:00:00'),
(105, 5, 'Enrolled', '2024-09-02 10:00:00');

-- ── Grades for enrolled students ─────────────────────────────
-- Exam 1 (Math Midterm, class 1, total 100)
INSERT IGNORE INTO grades (enrollment_id, exam_id, marks_obtained, grade_letter, remarks, graded_by, graded_at)
SELECT en.id, 1,
    CASE en.student_id WHEN 101 THEN 88 WHEN 102 THEN 72 WHEN 103 THEN 95 WHEN 104 THEN 61 WHEN 105 THEN 79 END,
    CASE en.student_id WHEN 101 THEN 'B+' WHEN 102 THEN 'B-' WHEN 103 THEN 'A+' WHEN 104 THEN 'C+' WHEN 105 THEN 'B' END,
    'Graded',
    101,
    '2024-10-16 14:00:00'
FROM enrollments en WHERE en.class_id=1 AND en.student_id IN (101,102,103,104,105);

-- Exam 2 (English Essay, class 2, total 50)
INSERT IGNORE INTO grades (enrollment_id, exam_id, marks_obtained, grade_letter, remarks, graded_by, graded_at)
SELECT en.id, 2,
    CASE en.student_id WHEN 101 THEN 42 WHEN 102 THEN 38 WHEN 104 THEN 45 WHEN 105 THEN 35 END,
    CASE en.student_id WHEN 101 THEN 'A-' WHEN 102 THEN 'B' WHEN 104 THEN 'A+' WHEN 105 THEN 'B-' END,
    'Graded',
    102,
    '2024-10-17 14:00:00'
FROM enrollments en WHERE en.class_id=2 AND en.student_id IN (101,102,104,105);

-- ── Attendance records ───────────────────────────────────────
INSERT IGNORE INTO attendance (enrollment_id, date, status)
SELECT en.id,
    dates.att_date,
    ELT(FLOOR(1 + RAND()*4), 'Present','Present','Present','Absent')
FROM enrollments en
CROSS JOIN (
    SELECT '2024-09-09' AS att_date UNION SELECT '2024-09-11' UNION SELECT '2024-09-16'
    UNION SELECT '2024-09-18' UNION SELECT '2024-09-23'
) dates
WHERE en.student_id IN (101,102,103,104,105)
ON DUPLICATE KEY UPDATE status=VALUES(status);

-- ── Payments for new students ────────────────────────────────
INSERT IGNORE INTO payments (student_id, fee_type_id, academic_year_id, amount_due, amount_paid, due_date, status, method, notes, created_by, created_at) VALUES
(101, 1, 3, 5000.00, 5000.00, '2024-09-30', 'Paid',    'Bank Transfer', 'Semester 1 tuition paid in full', 1, '2024-09-05 10:00:00'),
(101, 2, 3,  500.00,  500.00, '2024-09-15', 'Paid',    'Cash',          'Registration fee',                1, '2024-09-01 10:00:00'),
(102, 1, 3, 5000.00, 2500.00, '2024-09-30', 'Partial', 'Cash',          'Partial payment received',        1, '2024-09-05 10:00:00'),
(102, 2, 3,  500.00,  500.00, '2024-09-15', 'Paid',    'Cash',          'Registration fee',                1, '2024-09-01 10:00:00'),
(103, 1, 3, 5000.00,    0.00, '2024-09-30', 'Overdue', 'Pending',       'No payment received',             1, '2024-09-05 10:00:00'),
(104, 1, 3, 5000.00, 5000.00, '2024-09-30', 'Paid',    'Chapa',         'Online payment via Chapa',        1, '2024-09-10 10:00:00'),
(105, 1, 3, 5000.00,    0.00, '2024-10-31', 'Pending', 'Pending',       'International student fee',       1, '2024-09-05 10:00:00'),
(105, 2, 3,  500.00,  500.00, '2024-09-15', 'Paid',    'Bank Transfer', 'Registration fee',                1, '2024-09-01 10:00:00');

SET FOREIGN_KEY_CHECKS = 1;

-- ── Demo Parent record (linked to Meron Tadesse, student ID 101) ──
INSERT IGNORE INTO parents (id, user_id, first_name, last_name, phone, email, relationship, occupation) VALUES
(1, 301, 'Demo', 'Parent', '+251911000099', 'parent@school.com', 'Father', 'Engineer');

INSERT IGNORE INTO student_parents (student_id, parent_id, is_primary) VALUES
(101, 1, 1);
