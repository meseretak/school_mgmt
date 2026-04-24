<?php
require_once '../../includes/config.php';
auth_check(['admin','super_admin','librarian']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
csrf_check();

$book_id = (int)($_POST['book_id'] ?? 0);
$pdf_url = trim($_POST['pdf_url'] ?? '');

if (!$book_id) { flash('Invalid book.','error'); header('Location: index.php'); exit; }

// Verify book exists
$bk = $pdo->prepare("SELECT * FROM library_books WHERE id=?"); $bk->execute([$book_id]); $bk = $bk->fetch();
if (!$bk) { flash('Book not found.','error'); header('Location: index.php'); exit; }

$pdf_file = $bk['pdf_file'];

// Handle file upload
if (!empty($_FILES['pdf_file']['name'])) {
    $ext = strtolower(pathinfo($_FILES['pdf_file']['name'], PATHINFO_EXTENSION));
    if ($ext !== 'pdf') { flash('Only PDF files allowed.','error'); header('Location: index.php'); exit; }
    if ($_FILES['pdf_file']['size'] > 50 * 1024 * 1024) { flash('File too large (max 50MB).','error'); header('Location: index.php'); exit; }

    $upload_dir = dirname(__DIR__, 2) . '/uploads/library/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

    // Delete old file
    if ($bk['pdf_file'] && file_exists($upload_dir . $bk['pdf_file'])) {
        unlink($upload_dir . $bk['pdf_file']);
    }

    $fname = 'book_' . $book_id . '_' . time() . '.pdf';
    if (move_uploaded_file($_FILES['pdf_file']['tmp_name'], $upload_dir . $fname)) {
        $pdf_file = $fname;
        $pdf_url  = ''; // clear URL if file uploaded
    } else {
        flash('Upload failed.','error'); header('Location: index.php'); exit;
    }
}

// Save
$pdo->prepare("UPDATE library_books SET pdf_url=?, pdf_file=? WHERE id=?")
    ->execute([$pdf_url ?: null, $pdf_file ?: null, $book_id]);

log_activity($pdo, 'book_pdf_updated', "PDF updated for book ID $book_id");
flash('PDF updated for "' . $bk['title'] . '".');
header('Location: index.php'); exit;
SERVER['DOCUMENT_ROOT'].'/includes/config.php';
auth_check(['admin','super_admin','librarian']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
csrf_check();

$book_id = (int)($_POST['book_id'] ?? 0);
$pdf_url = trim($_POST['pdf_url'] ?? '');

if (!$book_id) { flash('Invalid book.','error'); header('Location: index.php'); exit; }

// Verify book exists
$bk = $pdo->prepare("SELECT * FROM library_books WHERE id=?"); $bk->execute([$book_id]); $bk = $bk->fetch();
if (!$bk) { flash('Book not found.','error'); header('Location: index.php'); exit; }

$pdf_file = $bk['pdf_file'];

// Handle file upload
if (!empty($_FILES['pdf_file']['name'])) {
    $ext = strtolower(pathinfo($_FILES['pdf_file']['name'], PATHINFO_EXTENSION));
    if ($ext !== 'pdf') { flash('Only PDF files allowed.','error'); header('Location: index.php'); exit; }
    if ($_FILES['pdf_file']['size'] > 50 * 1024 * 1024) { flash('File too large (max 50MB).','error'); header('Location: index.php'); exit; }

    $upload_dir = dirname(__DIR__, 2) . '/uploads/library/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

    // Delete old file
    if ($bk['pdf_file'] && file_exists($upload_dir . $bk['pdf_file'])) {
        unlink($upload_dir . $bk['pdf_file']);
    }

    $fname = 'book_' . $book_id . '_' . time() . '.pdf';
    if (move_uploaded_file($_FILES['pdf_file']['tmp_name'], $upload_dir . $fname)) {
        $pdf_file = $fname;
        $pdf_url  = ''; // clear URL if file uploaded
    } else {
        flash('Upload failed.','error'); header('Location: index.php'); exit;
    }
}

// Save
$pdo->prepare("UPDATE library_books SET pdf_url=?, pdf_file=? WHERE id=?")
    ->execute([$pdf_url ?: null, $pdf_file ?: null, $book_id]);

log_activity($pdo, 'book_pdf_updated', "PDF updated for book ID $book_id");
flash('PDF updated for "' . $bk['title'] . '".');
header('Location: index.php'); exit;
SERVER['DOCUMENT_ROOT'].'/includes/config.php';
auth_check(['admin','super_admin','librarian']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
csrf_check();

$book_id = (int)($_POST['book_id'] ?? 0);
$pdf_url = trim($_POST['pdf_url'] ?? '');

if (!$book_id) { flash('Invalid book.','error'); header('Location: index.php'); exit; }

// Verify book exists
$bk = $pdo->prepare("SELECT * FROM library_books WHERE id=?"); $bk->execute([$book_id]); $bk = $bk->fetch();
if (!$bk) { flash('Book not found.','error'); header('Location: index.php'); exit; }

$pdf_file = $bk['pdf_file'];

// Handle file upload
if (!empty($_FILES['pdf_file']['name'])) {
    $ext = strtolower(pathinfo($_FILES['pdf_file']['name'], PATHINFO_EXTENSION));
    if ($ext !== 'pdf') { flash('Only PDF files allowed.','error'); header('Location: index.php'); exit; }
    if ($_FILES['pdf_file']['size'] > 50 * 1024 * 1024) { flash('File too large (max 50MB).','error'); header('Location: index.php'); exit; }

    $upload_dir = dirname(__DIR__, 2) . '/uploads/library/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

    // Delete old file
    if ($bk['pdf_file'] && file_exists($upload_dir . $bk['pdf_file'])) {
        unlink($upload_dir . $bk['pdf_file']);
    }

    $fname = 'book_' . $book_id . '_' . time() . '.pdf';
    if (move_uploaded_file($_FILES['pdf_file']['tmp_name'], $upload_dir . $fname)) {
        $pdf_file = $fname;
        $pdf_url  = ''; // clear URL if file uploaded
    } else {
        flash('Upload failed.','error'); header('Location: index.php'); exit;
    }
}

// Save
$pdo->prepare("UPDATE library_books SET pdf_url=?, pdf_file=? WHERE id=?")
    ->execute([$pdf_url ?: null, $pdf_file ?: null, $book_id]);

log_activity($pdo, 'book_pdf_updated', "PDF updated for book ID $book_id");
flash('PDF updated for "' . $bk['title'] . '".');
header('Location: index.php'); exit;
SERVER['DOCUMENT_ROOT'].'/includes/config.php';
auth_check(['admin','super_admin','librarian']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
csrf_check();

$book_id = (int)($_POST['book_id'] ?? 0);
$pdf_url = trim($_POST['pdf_url'] ?? '');

if (!$book_id) { flash('Invalid book.','error'); header('Location: index.php'); exit; }

// Verify book exists
$bk = $pdo->prepare("SELECT * FROM library_books WHERE id=?"); $bk->execute([$book_id]); $bk = $bk->fetch();
if (!$bk) { flash('Book not found.','error'); header('Location: index.php'); exit; }

$pdf_file = $bk['pdf_file'];

// Handle file upload
if (!empty($_FILES['pdf_file']['name'])) {
    $ext = strtolower(pathinfo($_FILES['pdf_file']['name'], PATHINFO_EXTENSION));
    if ($ext !== 'pdf') { flash('Only PDF files allowed.','error'); header('Location: index.php'); exit; }
    if ($_FILES['pdf_file']['size'] > 50 * 1024 * 1024) { flash('File too large (max 50MB).','error'); header('Location: index.php'); exit; }

    $upload_dir = dirname(__DIR__, 2) . '/uploads/library/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

    // Delete old file
    if ($bk['pdf_file'] && file_exists($upload_dir . $bk['pdf_file'])) {
        unlink($upload_dir . $bk['pdf_file']);
    }

    $fname = 'book_' . $book_id . '_' . time() . '.pdf';
    if (move_uploaded_file($_FILES['pdf_file']['tmp_name'], $upload_dir . $fname)) {
        $pdf_file = $fname;
        $pdf_url  = ''; // clear URL if file uploaded
    } else {
        flash('Upload failed.','error'); header('Location: index.php'); exit;
    }
}

// Save
$pdo->prepare("UPDATE library_books SET pdf_url=?, pdf_file=? WHERE id=?")
    ->execute([$pdf_url ?: null, $pdf_file ?: null, $book_id]);

log_activity($pdo, 'book_pdf_updated', "PDF updated for book ID $book_id");
flash('PDF updated for "' . $bk['title'] . '".');
header('Location: index.php'); exit;
