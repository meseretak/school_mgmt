<?php
require_once '../../includes/config.php';
auth_check(['librarian','admin','super_admin']);
$page_title = 'Library Dashboard'; $active_page = 'librarian_dash';
$me = $_SESSION['user']['id'];

// Auto-mark overdue
try { $pdo->query("UPDATE library_borrows SET status='Overdue' WHERE status='Borrowed' AND due_date < CURDATE()"); } catch(Exception $e){}

// All stats in minimal queries
$stats = $pdo->query("SELECT
    (SELECT COUNT(*) FROM library_books WHERE is_active=1) AS total_titles,
    (SELECT COALESCE(SUM(total_copies),0) FROM library_books WHERE is_active=1) AS total_copies,
    (SELECT COALESCE(SUM(available_copies),0) FROM library_books WHERE is_active=1) AS available,
    (SELECT COUNT(*) FROM library_borrows WHERE status IN('Borrowed','Overdue')) AS borrowed,
    (SELECT COUNT(*) FROM library_borrows WHERE status='Overdue') AS overdue,
    (SELECT COUNT(*) FROM library_borrows WHERE status='Returned') AS total_returned,
    (SELECT COUNT(*) FROM library_borrows WHERE status='Returned' AND DATE(returned_at)=CURDATE()) AS returned_today,
    (SELECT COUNT(*) FROM library_borrows WHERE status='Lost') AS lost,
    (SELECT COALESCE(SUM(fine_amount+damage_fee),0) FROM library_borrows WHERE fine_amount+damage_fee>0 AND fine_paid=0) AS fines_due,
    (SELECT COALESCE(SUM(fine_amount+damage_fee),0) FROM library_borrows WHERE fine_paid=1) AS fines_paid,
    (SELECT COUNT(*) FROM library_requests WHERE status='Pending') AS pending_requests,
    (SELECT COUNT(*) FROM library_borrows WHERE status='Return Requested') AS return_requests,
    (SELECT COUNT(*) FROM students WHERE status='Active') AS total_students,
    (SELECT COUNT(*) FROM teachers WHERE status='Active') AS total_teachers,
    (SELECT COUNT(DISTINCT student_id) FROM library_borrows WHERE borrower_type='student' AND status IN('Borrowed','Overdue')) AS students_with_books,
    (SELECT COUNT(DISTINCT teacher_id) FROM library_borrows WHERE borrower_type='teacher' AND status IN('Borrowed','Overdue')) AS teachers_with_books
")->fetch();

$top_books = $pdo->query("SELECT bk.title, bk.author, COUNT(lb.id) AS cnt
    FROM library_borrows lb JOIN library_books bk ON lb.book_id=bk.id
    GROUP BY lb.book_id ORDER BY cnt DESC LIMIT 8")->fetchAll();

$recent_activity = $pdo->query("SELECT lb.*, bk.title,
    COALESCE(CONCAT(s.first_name,' ',s.last_name), CONCAT(t.first_name,' ',t.last_name)) AS borrower_name,
    lb.borrower_type
    FROM library_borrows lb
    JOIN library_books bk ON lb.book_id=bk.id
    LEFT JOIN students s ON lb.student_id=s.id
    LEFT JOIN teachers t ON lb.teacher_id=t.id
    ORDER BY lb.borrowed_at DESC LIMIT 8")->fetchAll();

require_once '../../includes/header.php';