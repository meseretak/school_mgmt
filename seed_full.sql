-- ═══════════════════════════════════════════════════════════════
-- FULL SEED: 15 Students, 10 Teachers, 10 Courses, 15 Classes
-- Password for all: password
-- ═══════════════════════════════════════════════════════════════
SET FOREIGN_KEY_CHECKS = 0;

-- Academic Year updated
INSERT IGNORE INTO academic_years (id,label,start_date,end_date,is_current) VALUES
(1,'2023-2024','2023-09-01','2024-06-30',0),
(2,'2024-2025','2024-09-01','2025-06-30',1),
(3,'2025-2026','2025-09-01','2026-06-30',0);

-- Branch
INSERT IGNORE INTO branches (id,name,code,is_main,is_active) VALUES
(1,'Main Campus','MAIN',1,1),
(2,'North Branch','NORTH',0,1);

-- Countries
INSERT IGNORE INTO countries (id,name,code) VALUES
(1,'Ethiopia','ET'),(2,'Kenya','KE'),(3,'Uganda','UG'),(4,'Tanzania','TZ'),(5,'Somalia','SO');

-- Grade Scale
INSERT IGNORE INTO grade_scales (id,name,pass_percentage,is_default) VALUES (1,'Standard',50.00,1);
INSERT IGNORE INTO grade_scale_items (scale_id,grade_letter,min_pct,max_pct,gpa_points) VALUES
(1,'A+',95,100,4.0),(1,'A',90,94,4.0),(1,'A-',85,89,3.7),
(1,'B+',80,84,3.3),(1,'B',75,79,3.0),(1,'B-',70,74,2.7),
(1,'C+',65,69,2.3),(1,'C',60,64,2.0),(1,'D',50,59,1.0),(1,'F',0,49,0.0);

-- Fee Types
INSERT IGNORE INTO fee_types (id,name,amount,is_active) VALUES
(1,'Tuition Fee',5000.00,1),(2,'Registration Fee',500.00,1),
(3,'Library Fee',200.00,1),(4,'Lab Fee',300.00,1),(5,'Activity Fee',150.00,1);

-- ── DEMO USERS (for testing) ──────────────────────────────────
INSERT IGNORE INTO users (id,name,email,password,role,branch_id,is_active) VALUES
(1,'Super Admin','superadmin@school.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','super_admin',NULL,1),
(2,'Admin User','admin@school.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','admin',1,1),
(3,'John Smith','teacher@school.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','teacher',1,1),
(4,'Alice Johnson','student@school.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','student',1,1),
(5,'Mary Librarian','librarian@school.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','librarian',1,1),
(6,'Demo Parent','parent@school.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','parent',1,1);

-- ── TEACHER USERS (10) ────────────────────────────────────────
INSERT IGNORE INTO users (id,name,email,password,role,branch_id,is_active) VALUES
(10,'Dr. Abebe Girma','abebe.girma@school.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','teacher',1,1),
(11,'Prof. Tigist Haile','tigist.haile@school.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','teacher',1,1),
(12,'Mr. Dawit Bekele','dawit.bekele@school.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','teacher',1,1),
(13,'Ms. Hana Tadesse','hana.tadesse@school.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','teacher',1,1),
(14,'Dr. Yonas Alemu','yonas.alemu@school.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','teacher',1,1),
(15,'Mr. Samuel Worku','samuel.worku@school.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','teacher',1,1),
(16,'Ms. Meron Kebede','meron.kebede@school.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','teacher',1,1),
(17,'Dr. Biruk Tesfaye','biruk.tesfaye@school.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','teacher',1,1),
(18,'Mr. Liya Solomon','liya.solomon@school.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','teacher',2,1),
(19,'Ms. Selam Mengistu','selam.mengistu@school.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','teacher',2,1);

INSERT IGNORE INTO teachers (id,user_id,teacher_code,first_name,last_name,specialization,phone,hire_date,status) VALUES
(1,3,'EMP-TCH-2024-0003','John','Smith','General Studies','+251911000003','2023-09-01','Active'),
(10,10,'EMP-TCH-2024-0010','Abebe','Girma','Mathematics & Statistics','+251911100010','2020-09-01','Active'),
(11,11,'EMP-TCH-2024-0011','Tigist','Haile','English Language & Literature','+251911100011','2019-09-01','Active'),
(12,12,'EMP-TCH-2024-0012','Dawit','Bekele','Physics & Applied Sciences','+251911100012','2021-09-01','Active'),
(13,13,'EMP-TCH-2024-0013','Hana','Tadesse','Chemistry & Biology','+251911100013','2022-09-01','Active'),
(14,14,'EMP-TCH-2024-0014','Yonas','Alemu','Computer Science & ICT','+251911100014','2020-09-01','Active'),
(15,15,'EMP-TCH-2024-0015','Samuel','Worku','History & Social Studies','+251911100015','2021-09-01','Active'),
(16,16,'EMP-TCH-2024-0016','Meron','Kebede','Geography & Environment','+251911100016','2022-09-01','Active'),
(17,17,'EMP-TCH-2024-0017','Biruk','Tesfaye','Economics & Business','+251911100017','2019-09-01','Active'),
(18,18,'EMP-TCH-2024-0018','Liya','Solomon','Art & Design','+251911100018','2023-09-01','Active'),
(19,19,'EMP-TCH-2024-0019','Selam','Mengistu','Physical Education','+251911100019','2023-09-01','Active');

-- ── STUDENT USERS (15) ───────────────────────────────────────
INSERT IGNORE INTO users (id,name,email,password,role,branch_id,is_active) VALUES
(20,'Meron Tadesse','meron.t@student.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','student',1,1),
(21,'Yonas Abebe','yonas.a@student.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','student',1,1),
(22,'Selam Worku','selam.w@student.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','student',1,1),
(23,'Biruk Mengistu','biruk.m@student.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','student',1,1),
(24,'Liya Solomon','liya.s@student.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','student',2,1),
(25,'Dawit Haile','dawit.h@student.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','student',1,1),
(26,'Tigist Bekele','tigist.b@student.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','student',1,1),
(27,'Samuel Girma','samuel.g@student.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','student',1,1),
(28,'Hana Alemu','hana.a@student.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','student',1,1),
(29,'Abebe Kebede','abebe.k@student.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','student',2,1),
(30,'Rahel Tesfaye','rahel.t@student.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','student',1,1),
(31,'Daniel Worku','daniel.w@student.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','student',1,1),
(32,'Sara Mengistu','sara.m@student.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','student',1,1),
(33,'Kidus Solomon','kidus.s@student.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','student',2,1),
(34,'Bethel Haile','bethel.h@student.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','student',1,1);

INSERT IGNORE INTO students (id,user_id,student_code,branch_id,first_name,last_name,dob,gender,nationality,country_id,phone,enrollment_date,status) VALUES
(4,4,'EMP-STU-2024-0004',1,'Alice','Johnson','2006-05-10','Female','Ethiopian',1,'+251922000004','2024-09-01','Active'),
(20,20,'EMP-STU-2024-0020',1,'Meron','Tadesse','2005-03-14','Female','Ethiopian',1,'+251922200020','2024-09-01','Active'),
(21,21,'EMP-STU-2024-0021',1,'Yonas','Abebe','2004-07-22','Male','Ethiopian',1,'+251922200021','2024-09-01','Active'),
(22,22,'EMP-STU-2024-0022',1,'Selam','Worku','2005-11-05','Female','Ethiopian',1,'+251922200022','2024-09-01','Active'),
(23,23,'EMP-STU-2024-0023',1,'Biruk','Mengistu','2004-01-30','Male','Ethiopian',1,'+251922200023','2024-09-01','Active'),
(24,24,'EMP-STU-2024-0024',2,'Liya','Solomon','2005-06-18','Female','Kenyan',2,'+251922200024','2024-09-01','Active'),
(25,25,'EMP-STU-2024-0025',1,'Dawit','Haile','2004-09-12','Male','Ethiopian',1,'+251922200025','2024-09-01','Active'),
(26,26,'EMP-STU-2024-0026',1,'Tigist','Bekele','2005-02-28','Female','Ethiopian',1,'+251922200026','2024-09-01','Active'),
(27,27,'EMP-STU-2024-0027',1,'Samuel','Girma','2004-05-15','Male','Ethiopian',1,'+251922200027','2024-09-01','Active'),
(28,28,'EMP-STU-2024-0028',1,'Hana','Alemu','2005-08-20','Female','Ethiopian',1,'+251922200028','2024-09-01','Active'),
(29,29,'EMP-STU-2024-0029',2,'Abebe','Kebede','2004-12-03','Male','Ugandan',3,'+251922200029','2024-09-01','Active'),
(30,30,'EMP-STU-2024-0030',1,'Rahel','Tesfaye','2005-04-10','Female','Ethiopian',1,'+251922200030','2024-09-01','Active'),
(31,31,'EMP-STU-2024-0031',1,'Daniel','Worku','2004-11-25','Male','Ethiopian',1,'+251922200031','2024-09-01','Active'),
(32,32,'EMP-STU-2024-0032',1,'Sara','Mengistu','2005-07-08','Female','Ethiopian',1,'+251922200032','2024-09-01','Active'),
(33,33,'EMP-STU-2024-0033',2,'Kidus','Solomon','2004-03-17','Male','Tanzanian',4,'+251922200033','2024-09-01','Active'),
(34,34,'EMP-STU-2024-0034',1,'Bethel','Haile','2005-10-22','Female','Ethiopian',1,'+251922200034','2024-09-01','Active');

-- ── PARENTS ───────────────────────────────────────────────────
INSERT IGNORE INTO parents (id,user_id,first_name,last_name,phone,relationship) VALUES
(1,6,'Demo','Parent','+251911000006','Father');

-- Link demo parent to demo student
INSERT IGNORE INTO student_parents (student_id,parent_id) VALUES (4,1);

-- ── COURSES (10) ─────────────────────────────────────────────
INSERT IGNORE INTO courses (id,name,code,credits,description,is_active) VALUES
(1,'Mathematics','MATH101',4,'Algebra, Geometry and Calculus',1),
(2,'English Language','ENG101',3,'Reading, Writing and Communication',1),
(3,'Physics','PHY101',4,'Mechanics, Thermodynamics, Electromagnetism',1),
(4,'Chemistry','CHEM101',4,'Organic and Inorganic Chemistry',1),
(5,'Biology','BIO101',3,'Cell Biology, Genetics and Ecology',1),
(6,'Computer Science','CS101',3,'Programming, Algorithms and Data Structures',1),
(7,'History','HIST101',2,'World and Ethiopian History',1),
(8,'Geography','GEO101',2,'Physical and Human Geography',1),
(9,'Economics','ECON101',3,'Micro and Macroeconomics',1),
(10,'Physical Education','PE101',1,'Sports, Fitness and Health',1);

-- ── CLASSES (15) ─────────────────────────────────────────────
INSERT IGNORE INTO classes (id,course_id,teacher_id,academic_year_id,section,room,schedule,status,branch_id) VALUES
(1,1,10,2,'A','Room 101','Mon/Wed 8:00-9:30','Open',1),
(2,1,10,2,'B','Room 102','Tue/Thu 8:00-9:30','Open',1),
(3,2,11,2,'A','Room 103','Mon/Wed 10:00-11:30','Open',1),
(4,3,12,2,'A','Lab 201','Tue/Thu 10:00-11:30','Open',1),
(5,4,13,2,'A','Lab 202','Mon/Wed 13:00-14:30','Open',1),
(6,5,13,2,'B','Lab 203','Tue/Thu 13:00-14:30','Open',1),
(7,6,14,2,'A','Computer Lab','Mon/Wed 15:00-16:30','Open',1),
(8,7,15,2,'A','Room 104','Tue/Thu 15:00-16:30','Open',1),
(9,8,16,2,'A','Room 105','Fri 8:00-11:00','Open',1),
(10,9,17,2,'A','Room 106','Mon/Wed 8:00-9:30','Open',2),
(11,10,19,2,'A','Sports Hall','Tue/Thu 7:00-8:00','Open',1),
(12,2,11,2,'B','Room 107','Fri 13:00-16:00','Open',2),
(13,6,14,2,'B','Computer Lab','Tue/Thu 8:00-9:30','Open',1),
(14,3,12,2,'B','Lab 204','Mon/Wed 13:00-14:30','Open',2),
(15,1,10,2,'C','Room 108','Fri 8:00-11:00','Open',1);

-- ── ENROLLMENTS ───────────────────────────────────────────────
INSERT IGNORE INTO enrollments (student_id,class_id,status,enrolled_at) VALUES
(20,1,'Enrolled','2024-09-02'),(20,3,'Enrolled','2024-09-02'),(20,5,'Enrolled','2024-09-02'),(20,7,'Enrolled','2024-09-02'),
(21,1,'Enrolled','2024-09-02'),(21,4,'Enrolled','2024-09-02'),(21,6,'Enrolled','2024-09-02'),(21,8,'Enrolled','2024-09-02'),
(22,2,'Enrolled','2024-09-02'),(22,3,'Enrolled','2024-09-02'),(22,7,'Enrolled','2024-09-02'),(22,9,'Enrolled','2024-09-02'),
(23,1,'Enrolled','2024-09-02'),(23,4,'Enrolled','2024-09-02'),(23,5,'Enrolled','2024-09-02'),(23,11,'Enrolled','2024-09-02'),
(24,10,'Enrolled','2024-09-02'),(24,12,'Enrolled','2024-09-02'),(24,14,'Enrolled','2024-09-02'),
(25,2,'Enrolled','2024-09-02'),(25,4,'Enrolled','2024-09-02'),(25,8,'Enrolled','2024-09-02'),(25,11,'Enrolled','2024-09-02'),
(26,3,'Enrolled','2024-09-02'),(26,5,'Enrolled','2024-09-02'),(26,9,'Enrolled','2024-09-02'),(26,13,'Enrolled','2024-09-02'),
(27,1,'Enrolled','2024-09-02'),(27,6,'Enrolled','2024-09-02'),(27,7,'Enrolled','2024-09-02'),(27,11,'Enrolled','2024-09-02'),
(28,2,'Enrolled','2024-09-02'),(28,3,'Enrolled','2024-09-02'),(28,5,'Enrolled','2024-09-02'),(28,9,'Enrolled','2024-09-02'),
(29,10,'Enrolled','2024-09-02'),(29,12,'Enrolled','2024-09-02'),(29,14,'Enrolled','2024-09-02'),
(30,1,'Enrolled','2024-09-02'),(30,4,'Enrolled','2024-09-02'),(30,7,'Enrolled','2024-09-02'),(30,13,'Enrolled','2024-09-02'),
(31,2,'Enrolled','2024-09-02'),(31,6,'Enrolled','2024-09-02'),(31,8,'Enrolled','2024-09-02'),(31,11,'Enrolled','2024-09-02'),
(32,3,'Enrolled','2024-09-02'),(32,5,'Enrolled','2024-09-02'),(32,9,'Enrolled','2024-09-02'),(32,15,'Enrolled','2024-09-02'),
(33,10,'Enrolled','2024-09-02'),(33,12,'Enrolled','2024-09-02'),(33,14,'Enrolled','2024-09-02'),
(34,1,'Enrolled','2024-09-02'),(34,3,'Enrolled','2024-09-02'),(34,7,'Enrolled','2024-09-02'),(34,15,'Enrolled','2024-09-02');

-- ── EXAMS ─────────────────────────────────────────────────────
INSERT IGNORE INTO exams (id,class_id,title,type,exam_date,total_marks,pass_marks,room) VALUES
(1,1,'Math Midterm','Midterm','2024-10-15',100,50,'Hall A'),
(2,1,'Math Final','Final','2025-01-14',100,50,'Hall A'),
(3,3,'English Essay','Quiz','2024-10-16',50,25,'Room 103'),
(4,4,'Physics Lab Test','Practical','2024-10-17',100,50,'Lab 201'),
(5,5,'Chemistry Midterm','Midterm','2024-10-18',100,50,'Lab 202'),
(6,7,'CS Programming Test','Midterm','2024-10-20',100,50,'Computer Lab'),
(7,2,'Math B Midterm','Midterm','2024-10-15',100,50,'Hall B'),
(8,6,'Biology Quiz','Quiz','2024-10-19',30,15,'Lab 203');

-- ── PAYMENTS ─────────────────────────────────────────────────
INSERT IGNORE INTO payments (student_id,fee_type_id,academic_year_id,amount_due,amount_paid,due_date,status,method,created_by) VALUES
(20,1,2,5000,5000,'2024-09-30','Paid','Bank Transfer',1),
(20,2,2,500,500,'2024-09-15','Paid','Cash',1),
(21,1,2,5000,2500,'2024-09-30','Partial','Cash',1),
(21,2,2,500,500,'2024-09-15','Paid','Cash',1),
(22,1,2,5000,0,'2024-09-30','Overdue','Pending',1),
(23,1,2,5000,5000,'2024-09-30','Paid','Chapa',1),
(24,1,2,5000,0,'2024-10-31','Pending','Pending',1),
(25,1,2,5000,5000,'2024-09-30','Paid','Bank Transfer',1),
(26,1,2,5000,2500,'2024-09-30','Partial','Cash',1),
(27,1,2,5000,5000,'2024-09-30','Paid','Chapa',1),
(28,1,2,5000,0,'2024-09-30','Overdue','Pending',1),
(29,1,2,5000,5000,'2024-09-30','Paid','Bank Transfer',1),
(30,1,2,5000,0,'2024-10-31','Pending','Pending',1),
(31,1,2,5000,5000,'2024-09-30','Paid','Cash',1),
(32,1,2,5000,2500,'2024-09-30','Partial','Cash',1),
(33,1,2,5000,5000,'2024-09-30','Paid','Bank Transfer',1),
(34,1,2,5000,0,'2024-09-30','Overdue','Pending',1);

-- ── NOTICES ──────────────────────────────────────────────────
INSERT IGNORE INTO notices (title,body,audience,posted_by,post_date,is_active) VALUES
('Welcome to 2024-2025 Academic Year','We welcome all students and staff to the new academic year.','all',1,'2024-09-01',1),
('Fee Payment Deadline','All fees must be paid by October 31st.','students',1,'2024-10-01',1),
('Staff Meeting','Monthly staff meeting on Friday at 3 PM.','teachers',1,'2024-10-14',1),
('Library Hours Extended','Library open until 8 PM during exam period.','all',1,'2024-10-10',1),
('Sports Day','Annual sports day on November 20th.','students',1,'2024-11-01',1);

-- ── LIBRARY BOOKS ────────────────────────────────────────────
INSERT IGNORE INTO library_books (isbn,title,author,publisher,publish_year,language,category,total_copies,available_copies,price,currency,location,is_active,added_by) VALUES
('9780061965784','The Alchemist','Paulo Coelho','HarperOne',1988,'English','Fiction',5,4,12.99,'USD','A-1',1,1),
('9780743273565','The Great Gatsby','F. Scott Fitzgerald','Scribner',1925,'English','Fiction',3,3,9.99,'USD','A-2',1,1),
('9780131103627','The C Programming Language','Kernighan & Ritchie','Prentice Hall',1988,'English','Computers',4,4,45.00,'USD','B-1',1,1),
('9780201633610','Design Patterns','Gang of Four','Addison-Wesley',1994,'English','Computers',2,2,55.00,'USD','B-2',1,1),
('9780385333481','The Handmaids Tale','Margaret Atwood','Anchor Books',1985,'English','Fiction',3,3,14.99,'USD','A-3',1,1),
('9780062316097','The Alchemist 2','Paulo Coelho','HarperOne',2014,'English','Fiction',2,2,11.99,'USD','A-4',1,1),
('9780140449136','Crime and Punishment','Fyodor Dostoevsky','Penguin',1866,'English','Classic',3,3,8.99,'USD','A-5',1,1),
('9780679720201','In Search of Lost Time','Marcel Proust','Modern Library',1913,'English','Classic',2,2,25.00,'USD','A-6',1,1);

-- ── LIBRARY SETTINGS ─────────────────────────────────────────
INSERT IGNORE INTO library_settings (id,fine_per_day,max_borrow_days,max_books_student,max_books_teacher,max_renewals,lost_penalty_multiplier,lost_after_days,currency) VALUES
(1,0.50,14,3,5,2,1.5,30,'USD');

-- ── CALENDAR EVENTS ──────────────────────────────────────────
INSERT IGNORE INTO calendar_events (title,description,event_date,end_date,event_type,audience,created_by) VALUES
('Semester 1 Begins','First day of classes','2024-09-02','2024-09-02','Event','all',1),
('Mid-Term Exams','Mid-semester examinations','2024-10-14','2024-10-18','Exam','students',1),
('Ethiopian New Year','Enkutatash holiday','2024-09-11','2024-09-11','Holiday','all',1),
('Parent-Teacher Meeting','Quarterly conference','2024-11-15','2024-11-15','Meeting','all',1),
('Final Exams','End of semester examinations','2025-01-13','2025-01-17','Exam','students',1),
('Graduation Ceremony','Annual graduation','2025-06-15','2025-06-15','Event','all',1);

-- ── CLEARANCE DEPARTMENTS ────────────────────────────────────
INSERT IGNORE INTO clearance_departments (id,name,description,responsible_role,sort_order,is_active) VALUES
(1,'Academic Office','Verify academic records','admin',1,1),
(2,'Library','Return books and clear fines','librarian',2,1),
(3,'Finance','Clear outstanding fees','admin',3,1),
(4,'Student Affairs','Return ID and property','admin',4,1),
(5,'Dormitory','Return room keys','admin',5,1);

SET FOREIGN_KEY_CHECKS = 1;