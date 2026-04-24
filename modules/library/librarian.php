<?php
require_once '../../includes/config.php';
auth_check(['librarian','admin','super_admin']);
$page_title = 'Library Dashboard'; $active_page = 'librarian_dash';
$me = $_SESSION['user']['id'];

// Auto-mark overdue
try { $pdo->query("UPDATE library_borrows SET status='Overdue' WHERE status='Borrowed' AND due_date < CURDATE()"); } catch(Exception $e){}

// All stats in minimal queries
$stats_defaults = ['total_titles'=>0,'total_copies'=>0,'available'=>0,'borrowed'=>0,'overdue'=>0,'total_returned'=>0,'returned_today'=>0,'lost'=>0,'fines_due'=>0,'fines_paid'=>0,'pending_requests'=>0,'return_requests'=>0,'total_students'=>0,'total_teachers'=>0,'students_with_books'=>0,'teachers_with_books'=>0];
try { $stats = $pdo->query("SELECT
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
")->fetch() ?: $stats_defaults; } catch(Exception $e) { $stats = $stats_defaults; }

try { $top_books = $pdo->query("SELECT bk.title, bk.author, COUNT(lb.id) AS cnt
    FROM library_borrows lb JOIN library_books bk ON lb.book_id=bk.id
    GROUP BY lb.book_id ORDER BY cnt DESC LIMIT 8")->fetchAll(); } catch(Exception $e) { $top_books = []; }

try { $recent_activity = $pdo->query("SELECT lb.*, bk.title,
    COALESCE(CONCAT(s.first_name,' ',s.last_name), CONCAT(t.first_name,' ',t.last_name)) AS borrower_name,
    lb.borrower_type
    FROM library_borrows lb
    JOIN library_books bk ON lb.book_id=bk.id
    LEFT JOIN students s ON lb.student_id=s.id
    LEFT JOIN teachers t ON lb.teacher_id=t.id
    ORDER BY lb.borrowed_at DESC LIMIT 8")->fetchAll(); } catch(Exception $e) { $recent_activity = []; }

require_once '../../includes/header.php';

// Borrow trend last 6 months
try { $borrow_trend = $pdo->query("SELECT DATE_FORMAT(borrowed_at,'%b %Y') AS mo, COUNT(*) AS cnt FROM library_borrows WHERE borrowed_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH) GROUP BY DATE_FORMAT(borrowed_at,'%Y-%m') ORDER BY DATE_FORMAT(borrowed_at,'%Y-%m')")->fetchAll(); } catch(Exception $e) { $borrow_trend = []; }
// Category distribution
try { $cat_dist = $pdo->query("SELECT COALESCE(category,'Uncategorized') AS cat, COUNT(*) AS cnt FROM library_books WHERE is_active=1 GROUP BY category ORDER BY cnt DESC LIMIT 8")->fetchAll(); } catch(Exception $e) { $cat_dist = []; }
?>
<!-- Header -->
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px">
  <div>
    <h1 style="font-size:1.4rem;font-weight:800;color:#1e293b"><i class="fas fa-chart-bar" style="color:var(--primary);margin-right:8px"></i>Library Dashboard</h1>
    <p style="color:#64748b;font-size:.88rem;margin-top:2px">Overview and reports — <?= date('l, F j, Y') ?></p>
  </div>
  <a href="librarian_desk.php" class="btn btn-primary"><i class="fas fa-tasks"></i> Libr