-- ═══════════════════════════════════════════════════════════════
-- FULL SEED DATA — 10 records per major table
-- Run AFTER database.sql and migrate_all.sql
-- ═══════════════════════════════════════════════════════════════
SET FOREIGN_KEY_CHECKS = 0;

-- ── Academic Years ────────────────────────────────────────────
INSERT IGNORE INTO academic_years (id,label,start_date,end_date,is_current) VALUES
(1,'2020-2021','2020-09-01','2021-06-30',0),
(2,'2021-2022','2021-09-01','2022-06-30',0),
(3,'2022-2023','2022-09-01','2023-06-30',0),
(4,'2023-2024','2023-09-01','2024-06-30',0),
(5,'2024-2025','2024-09-01','2025-06-30',1),
(6,'2025-2026','2025-09-01','2026-06-30',0),
(7,'2026-2027','2026-09-01','2027-06-30',0),
(8,'2027-2028','2027-09-01','2028-06-30',0),
(9,'2028-2029','2028-09-01','2029-06-30',0),
(10,'2029-2030','2029-09-01','2030-06-30',0);

-- ── Branches ─────────────────────────────────────────────────
INSERT IGNORE INTO branches (id,name,code,address,phone,email,principal,is_main,is_active) VALUES
(1,'Main Campus','MAIN','Addis Ababa, Bole Road','+251911000001','main@school.com','Dr. Abebe Girma',1,1),
(2,'North Branch','NORTH','Addis Ababa, Piassa','+251911000002','north@school.com','Ato Kebede Alemu',0,1),
(3,'South Branch','SOUTH','Addis Ababa, Megenagna','+251911000003','south@school.com','W/ro Tigist Haile',0,1),
(4,'East Branch','EAST','Dire Dawa','+251911000004','east@school.com','Ato Dawit Bekele',0,1),
(5,'West Branch','WEST','Bahir Dar','+251911000005','west@school.com','Dr. Meron Tadesse',0,1),
(6,'Central Branch','CENT','Addis Ababa, Merkato','+251911000006','central@school.com','Ato Yonas Alemu',0,1),
(7,'Airport Branch','AIRP','Addis Ababa, Airport','+251911000007','airport@school.com','W/ro Selam Worku',0,1),
(8,'Kazanchis Branch','KAZA','Addis Ababa, Kazanchis','+251911000008','kazanchis@school.com','Dr. Biruk Haile',0,1),
(9,'Bole Branch','BOLE','Addis Ababa, Bole','+251911000009','bole@school.com','Ato Liya Solomon',0,1),
(10,'Sarbet Branch','SARB','Addis Ababa, Sarbet','+251911000010','sarbet@school.com','Dr. Noah Tadesse',0,1);

-- ── Countries ────────────────────────────────────────────────
INSERT IGNORE INTO countries (id,name,code) VALUES
(1,'Ethiopia','ET'),(2,'Kenya','KE'),(3,'Uganda','UG'),(4,'Tanzania','TZ'),
(5,'Somalia','SO'),(6,'Eritrea','ER'),(7,'Sudan','SD'),(8,'Djibouti','DJ'),
(9,'Rwanda','RW'),(10,'Burundi','BI');

-- ── Courses ──────────────────────────────────────────────────
INSERT IGNORE INTO courses (id,name,code,credits,description,is_active) VALUES
(1,'Mathematics','MATH101',3,'Algebra, geometry and calculus',1),
(2,'English Language','ENG101',3,'Reading, writing and communication',1),
(3,'Physics','PHY101',4,'Mechanics, thermodynamics, electromagnetism',1),
(4,'Chemistry','CHEM101',4,'Organic and inorganic chemistry',1),
(5,'Biology','BIO101',3,'Cell biology, genetics and ecology',1),
(6,'History','HIST101',2,'World and Ethiopian history',1),
(7,'Geography','GEO101',2,'Physical and human geography',1),
(8,'Computer Science','CS101',3,'Programming and algorithms',1),
(9,'Economics','ECON101',3,'Micro and macroeconomics',1),
(10,'Physical Education','PE101',1,'Sports and fitness',1);

-- ── Fee Types ────────────────────────────────────────────────
INSERT IGNORE INTO fee_types (id,name,amount,description,is_active) VALUES
(1,'Tuition Fee',5000.00,'Semester tuition fee',1),
(2,'Registration Fee',500.00,'One-time registration fee',1),
(3,'Library Fee',200.00,'Annual library access fee',1),
(4,'Lab Fee',300.00,'Science laboratory usage fee',1),
(5,'Activity Fee',150.00,'Sports and extracurricular activities',1),
(6,'Exam Fee',250.00,'Examination administration fee',1),
(7,'Transport Fee',800.00,'School bus service fee',1),
(8,'Uniform Fee',600.00,'School uniform fee',1),
(9,'Technology Fee',400.00,'Computer lab and internet access',1),
(10,'Graduation Fee',1000.00,'Graduation ceremony fee',1);

-- ── Grade Scale ──────────────────────────────────────────────
INSERT IGNORE INTO grade_scales (id,name,pass_percentage,is_default) VALUES (1,'Standard Scale',50.00,1);
INSERT IGNORE INTO grade_scale_items (scale_id,grade_letter,min_pct,max_pct,gpa_points,description) VALUES
(1,'A+',95,100,4.0,'Outstanding'),(1,'A',90,94,4.0,'Excellent'),
(1,'A-',85,89,3.7,'Very Good'),(1,'B+',80,84,3.3,'Good'),
(1,'B',75,79,3.0,'Above Average'),(1,'B-',70,74,2.7,'Average'),
(1,'C+',65,69,2.3,'Below Average'),(1,'C',60,64,2.0,'Satisfactory'),
(1,'D',50,59,1.0,'Pass'),(1,'F',0,49,0.0,'Fail');

-- ── Library Settings ─────────────────────────────────────────
INSERT IGNORE INTO library_settings (id,fine_per_day,max_borrow_days,max_books_student,max_books_teacher,max_renewals,lost_penalty_multiplier,lost_after_days,currency) VALUES
(1,0.50,14,3,5,2,1.5,30,'USD');

-- ── Library Books ────────────────────────────────────────────
INSERT IGNORE INTO library_books (id,isbn,title,author,publisher,publish_year,language,category,subject,total_copies,available_copies,price,currency,location,is_active,added_by) VALUES
(1,'9780061965784','The Alchemist','Paulo Coelho','HarperOne',1988,'English','Fiction','Literature',5,5,12.99,'USD','A-1',1,1),
(2,'9780743273565','The Great Gatsby','F. Scott Fitzgerald','Scribner',1925,'English','Fiction','Literature',3,3,9.99,'USD','A-2',1,1),
(3,'9780131103627','The C Programming Language','Kernighan & Ritchie','Prentice Hall',1988,'English','Computers','Programming',4,4,45.00,'USD','B-1',1,1),
(4,'9780201633610','Design Patterns','Gang of Four','Addison-Wesley',1994,'English','Computers','Software',2,2,55.00,'USD','B-2',1,1),
(5,'9780385333481','The Handmaid''s Tale','Margaret Atwood','Anchor Books',1985,'English','Fiction','Dystopian',3,3,14.99,'USD','A-3',1,1),
(6,'9780316769174','The Catcher in the Rye','J.D. Salinger','Little Brown',1951,'English','Fiction','Classic',4,4,11.99,'USD','A-4',1,1),
(7,'9780062316097','The Alchemist (Amharic)','Paulo Coelho','HarperOne',2010,'Amharic','Fiction','Literature',2,2,8.99,'USD','A-5',1,1),
(8,'9780140449136','Crime and Punishment','Fyodor Dostoevsky','Penguin',1866,'English','Fiction','Classic',3,3,13.99,'USD','A-6',1,1),
(9,'9780679720201','In Search of Lost Time','Marcel Proust','Modern Library',1913,'English','Fiction','Classic',2,2,25.99,'USD','A-7',1,1),
(10,'9780743477123','The Da Vinci Code','Dan Brown','Doubleday',2003,'English','Fiction','Thriller',5,5,15.99,'USD','A-8',1,1);

-- ── Users (demo accounts) ────────────────────────────────────
-- password = 'password' hashed
INSERT IGNORE INTO users (id,name,email,password,role,branch_id,is_active,created_at) VALUES
(1,'Super Admin','superadmin@school.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','super_admin',1,1,'2024-01-01 08:00:00'),
(2,'Admin User','admin@school.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','admin',1,1,'2024-01-01 08:00:00'),
(3,'Alice Johnson','student@school.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','student',1,1,'2024-09-01 09:00:00'),
(4,'John Smith','teacher@school.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','teacher',1,1,'2024-01-15 08:00:00'),
(5,'Librarian','librarian@school.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','librarian',1,1,'2024-01-15 08:00:00'),
(6,'Demo Parent','parent@school.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','parent',1,1,'2024-09-01 09:00:00'),
(7,'Samuel Tesfaye','samuel.tesfaye@school.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','teacher',1,1,'2023-08-15 08:00:00'),
(8,'Hana Girma','hana.girma@school.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','teacher',1,1,'2023-08-15 08:00:00'),
(9,'Meron Tadesse','meron.tadesse@student.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','student',1,1,'2024-09-01 09:00:00'),
(10,'Yonas Abebe','yonas.abebe@student.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','student',1,1,'2024-09-01 09:00:00'),
(11,'Selam Worku','selam.worku@student.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','student',1,1,'2024-09-01 09:00:00'),
(12,'Biruk Mengistu','biruk.mengistu@student.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','student',1,1,'2024-09-01 09:00:00');

-- ── Teachers ─────────────────────────────────────────────────
INSERT IGNORE INTO teachers (id,user_id,teacher_code,first_name,last_name,specialization,phone,hire_date,status) VALUES
(1,4,'EMP-TCH-2024-0004','John','Smith','Mathematics & Statistics','+251911101004','2024-01-15','Active'),
(2,7,'EMP-TCH-2023-0007','Samuel','Tesfaye','Physics & Applied Sciences','+251911101007','2023-08-20','Active'),
(3,8,'EMP-TCH-2023-0008','Hana','Girma','English Language & Literature','+251911101008','2023-08-20','Active');

-- ── Students ─────────────────────────────────────────────────
INSERT IGNORE INTO students (id,user_id,student_code,branch_id,first_name,last_name,dob,gender,nationality,country_id,phone,address,enrollment_date,status) VALUES
(1,3,'EMP-STU-2024-0003',1,'Alice','Johnson','2005-03-14','Female','Ethiopian',1,'+251922201003','Addis Ababa, Bole','2024-09-01','Active'),
(2,9,'EMP-STU-2024-0009',1,'Meron','Tadesse','2005-03-14','Female','Ethiopian',1,'+251922201009','Addis Ababa, Bole','2024-09-01','Active'),
(3,10,'EMP-STU-2024-0010',1,'Yonas','Abebe','2004-07-22','Male','Ethiopian',1,'+251922201010','Addis Ababa, Kirkos','2024-09-01','Active'),
(4,11,'EMP-STU-2024-0011',1,'Selam','Worku','2005-11-05','Female','Ethiopian',1,'+251922201011','Addis Ababa, Yeka','2024-09-01','Active'),
(5,12,'EMP-STU-2024-0012',1,'Biruk','Mengistu','2004-01-30','Male','Ethiopian',1,'+251922201012','Addis Ababa, Nifas Silk','2024-09-01','Active');

-- ── Classes ──────────────────────────────────────────────────
INSERT IGNORE INTO classes (id,course_id,teacher_id,academic_year_id,section,room,schedule,status,branch_id) VALUES
(1,1,1,5,'A','Room 101','Mon/Wed 8:00-9:30','Open',1),
(2,2,3,5,'B','Room 102','Tue/Thu 8:00-9:30','Open',1),
(3,3,2,5,'A','Lab 201','Mon/Wed 10:00-11:30','Open',1),
(4,4,2,5,'A','Lab 202','Tue/Thu 10:00-11:30','Open',1),
(5,5,3,5,'B','Room 103','Fri 8:00-11:00','Open',1),
(6,6,1,5,'A','Room 104','Mon/Wed 13:00-14:30','Open',1),
(7,7,3,5,'A','Room 105','Tue/Thu 13:00-14:30','Open',1),
(8,8,1,5,'A','Lab 301','Mon/Wed 15:00-16:30','Open',1),
(9,9,2,5,'A','Room 106','Tue/Thu 15:00-16:30','Open',1),
(10,10,3,5,'A','Gym','Fri 13:00-15:00','Open',1);

-- ── Enrollments ──────────────────────────────────────────────
INSERT IGNORE INTO enrollments (student_id,class_id,status,enrolled_at) VALUES
(1,1,'Enrolled','2024-09-02'),(1,2,'Enrolled','2024-09-02'),(1,3,'Enrolled','2024-09-02'),
(2,1,'Enrolled','2024-09-02'),(2,4,'Enrolled','2024-09-02'),(2,5,'Enrolled','2024-09-02'),
(3,2,'Enrolled','2024-09-02'),(3,3,'Enrolled','2024-09-02'),(3,6,'Enrolled','2024-09-02'),
(4,1,'Enrolled','2024-09-02'),(4,7,'Enrolled','2024-09-02'),(4,8,'Enrolled','2024-09-02'),
(5,2,'Enrolled','2024-09-02'),(5,9,'Enrolled','2024-09-02'),(5,10,'Enrolled','2024-09-02');

-- ── Exams ────────────────────────────────────────────────────
INSERT IGNORE INTO exams (id,class_id,title,type,exam_date,start_time,duration,total_marks,pass_marks,room) VALUES
(1,1,'Math Midterm','Midterm','2024-10-15','09:00:00',120,100,50,'Hall A'),
(2,2,'English Essay','Quiz','2024-10-16','09:00:00',60,50,25,'Room 102'),
(3,3,'Physics Lab Test','Practical','2024-10-17','10:00:00',90,100,50,'Lab 201'),
(4,4,'Chemistry Final','Final','2025-01-14','09:00:00',180,100,50,'Hall B'),
(5,5,'Biology Quiz 1','Quiz','2024-10-18','08:00:00',45,30,15,'Room 103'),
(6,1,'Math Final','Final','2025-01-13','09:00:00',180,100,50,'Hall A'),
(7,2,'English Final','Final','2025-01-15','09:00:00',120,100,50,'Hall B'),
(8,6,'History Midterm','Midterm','2024-10-20','09:00:00',90,100,50,'Room 104'),
(9,8,'CS Practical','Practical','2024-10-22','10:00:00',120,100,50,'Lab 301'),
(10,9,'Economics Quiz','Quiz','2024-10-25','09:00:00',60,50,25,'Room 106');

-- ── Grades ───────────────────────────────────────────────────
INSERT IGNORE INTO grades (enrollment_id,exam_id,marks_obtained,grade_letter,remarks,graded_by,graded_at)
SELECT en.id,1,CASE en.student_id WHEN 1 THEN 88 WHEN 2 THEN 72 WHEN 4 THEN 91 ELSE 65 END,
CASE en.student_id WHEN 1 THEN 'B+' WHEN 2 THEN 'B-' WHEN 4 THEN 'A+' ELSE 'C+' END,'Graded',1,'2024-10-16 14:00:00'
FROM enrollments en WHERE en.class_id=1 AND en.student_id IN (1,2,4);

INSERT IGNORE INTO grades (enrollment_id,exam_id,marks_obtained,grade_letter,remarks,graded_by,graded_at)
SELECT en.id,2,CASE en.student_id WHEN 1 THEN 42 WHEN 3 THEN 38 WHEN 5 THEN 45 ELSE 35 END,
CASE en.student_id WHEN 1 THEN 'A-' WHEN 3 THEN 'B' WHEN 5 THEN 'A+' ELSE 'B-' END,'Graded',1,'2024-10-17 14:00:00'
FROM enrollments en WHERE en.class_id=2 AND en.student_id IN (1,3,5);

-- ── Attendance ───────────────────────────────────────────────
INSERT IGNORE INTO attendance (enrollment_id,date,status)
SELECT en.id,dates.d,ELT(FLOOR(1+RAND()*4),'Present','Present','Present','Absent')
FROM enrollments en
CROSS JOIN (SELECT '2024-09-09' AS d UNION SELECT '2024-09-11' UNION SELECT '2024-09-16'
UNION SELECT '2024-09-18' UNION SELECT '2024-09-23' UNION SELECT '2024-09-25'
UNION SELECT '2024-09-30' UNION SELECT '2024-10-02' UNION SELECT '2024-10-07' UNION SELECT '2024-10-09') dates
WHERE en.student_id IN (1,2,3,4,5)
ON DUPLICATE KEY UPDATE status=VALUES(status);

-- ── Payments ─────────────────────────────────────────────────
INSERT IGNORE INTO payments (student_id,fee_type_id,academic_year_id,amount_due,amount_paid,due_date,status,method,notes,created_by) VALUES
(1,1,5,5000.00,5000.00,'2024-09-30','Paid','Bank Transfer','Semester 1 tuition',1),
(1,2,5,500.00,500.00,'2024-09-15','Paid','Cash','Registration fee',1),
(1,3,5,200.00,0.00,'2024-10-31','Pending','Pending','Library fee',1),
(2,1,5,5000.00,2500.00,'2024-09-30','Partial','Cash','Partial payment',1),
(2,2,5,500.00,500.00,'2024-09-15','Paid','Cash','Registration fee',1),
(3,1,5,5000.00,0.00,'2024-09-30','Overdue','Pending','No payment received',1),
(4,1,5,5000.00,5000.00,'2024-09-30','Paid','Chapa','Online payment',1),
(4,4,5,300.00,300.00,'2024-09-30','Paid','Cash','Lab fee',1),
(5,1,5,5000.00,0.00,'2024-10-31','Pending','Pending','International student',1),
(5,2,5,500.00,500.00,'2024-09-15','Paid','Bank Transfer','Registration fee',1);

-- ── Notices ──────────────────────────────────────────────────
INSERT IGNORE INTO notices (id,title,body,audience,posted_by,post_date,expiry_date,is_active) VALUES
(1,'Welcome Back!','Welcome all students back for the new semester.','all',1,'2024-09-02','2024-09-30',1),
(2,'Library Hours Extended','Library open until 8 PM during exam period.','all',1,'2024-10-10','2024-10-31',1),
(3,'Fee Payment Deadline','All fees must be paid by October 31st.','students',1,'2024-10-01','2024-10-31',1),
(4,'Staff Meeting','Monthly meeting on Friday at 3 PM.','teachers',1,'2024-10-14','2024-10-18',1),
(5,'Sports Day','Annual sports day on November 20th.','students',1,'2024-11-01','2024-11-20',1),
(6,'Exam Schedule Released','Final exam schedule is now available.','all',1,'2024-12-01','2025-01-20',1),
(7,'Holiday Notice','School closed for Ethiopian Christmas.','all',1,'2025-01-05','2025-01-08',1),
(8,'New Semester Registration','Register for Semester 2 by February 1st.','students',1,'2025-01-15','2025-02-01',1),
(9,'Parent Meeting','Parent-teacher conference on March 15th.','all',1,'2025-03-01','2025-03-15',1),
(10,'Graduation Ceremony','Graduation ceremony on June 30th.','all',1,'2025-06-01','2025-06-30',1);

-- ── Assignments ──────────────────────────────────────────────
INSERT IGNORE INTO assignments (id,class_id,teacher_id,title,description,due_date,total_marks,pass_marks,status) VALUES
(1,1,1,'Algebra Problem Set','Solve problems 1-20 from Chapter 3.','2024-10-20',100,50,'Published'),
(2,2,3,'Essay: My Future Goals','Write a 500-word essay about career aspirations.','2024-10-22',50,25,'Published'),
(3,3,2,'Newton''s Laws Report','Lab report on Newton''s Laws experiment.','2024-10-25',100,50,'Published'),
(4,4,2,'Periodic Table Study','Memorize first 20 elements.','2024-10-28',50,25,'Published'),
(5,5,3,'Ecosystem Diagram','Draw a complete food web for a forest ecosystem.','2024-10-30',30,15,'Published'),
(6,6,1,'History Essay','Write about the Ethiopian Empire.','2024-11-05',100,50,'Published'),
(7,8,1,'Programming Project','Create a simple calculator in Python.','2024-11-10',100,50,'Published'),
(8,9,2,'Economics Analysis','Analyze supply and demand in Ethiopia.','2024-11-12',50,25,'Published'),
(9,1,1,'Math Quiz Prep','Practice problems for upcoming quiz.','2024-11-15',50,25,'Published'),
(10,2,3,'Reading Comprehension','Read Chapter 5 and answer questions.','2024-11-18',30,15,'Published');

-- ── Calendar Events ──────────────────────────────────────────
INSERT IGNORE INTO calendar_events (id,title,description,event_date,end_date,event_type,audience,created_by) VALUES
(1,'Semester 1 Begins','First day of Semester 1','2024-09-02','2024-09-02','Event','all',1),
(2,'Mid-Term Exams','Mid-semester examination period','2024-10-14','2024-10-18','Exam','students',1),
(3,'Ethiopian New Year','Enkutatash - public holiday','2024-09-11','2024-09-11','Holiday','all',1),
(4,'Parent-Teacher Meeting','Quarterly conference','2024-11-15','2024-11-15','Meeting','all',1),
(5,'Final Exams Semester 1','End of semester examinations','2025-01-13','2025-01-17','Exam','students',1),
(6,'Christmas Holiday','Ethiopian Christmas break','2025-01-07','2025-01-08','Holiday','all',1),
(7,'Semester 2 Begins','First day of Semester 2','2025-02-03','2025-02-03','Event','all',1),
(8,'Sports Day','Annual sports competition','2025-03-20','2025-03-20','Event','all',1),
(9,'Final Exams Semester 2','End of year examinations','2025-05-26','2025-05-30','Exam','students',1),
(10,'Graduation Ceremony','Annual graduation','2025-06-30','2025-06-30','Event','all',1);

-- ── Clearance Departments ────────────────────────────────────
INSERT IGNORE INTO clearance_departments (id,name,description,responsible_role,sort_order,is_active) VALUES
(1,'Academic Office','Verify academic records','admin',1,1),
(2,'Library','Return books and clear fines','librarian',2,1),
(3,'Finance','Clear outstanding fees','admin',3,1),
(4,'Student Affairs','Return ID and school property','admin',4,1),
(5,'Dormitory','Return room keys','admin',5,1),
(6,'Sports','Return sports equipment','admin',6,1),
(7,'IT Department','Return devices and clear accounts','admin',7,1),
(8,'Health Center','Medical clearance','admin',8,1),
(9,'Cafeteria','Clear cafeteria balance','admin',9,1),
(10,'Security','Return access cards','admin',10,1);

-- ── Parent (demo) ────────────────────────────────────────────
INSERT IGNORE INTO parents (id,user_id,first_name,last_name,phone,email,relationship,occupation) VALUES
(1,6,'Demo','Parent','+251911000099','parent@school.com','Father','Engineer');
INSERT IGNORE INTO student_parents (student_id,parent_id,is_primary) VALUES (1,1,1),(2,1,0);

SET FOREIGN_KEY_CHECKS = 1;
