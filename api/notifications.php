<?php
require_once '../includes/config.php';
auth_check();
header('Content-Type: application/json');

$uid = $_SESSION['user']['id'];
$action = $_GET['action'] ?? 'count';

if ($action === 'count') {
    $count = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
    $count->execute([$uid]);
    echo json_encode(['count' => (int)$count->fetchColumn()]);
}

if ($action === 'list') {
    $stmt = $pdo->prepare("SELECT id, title, message, is_read, created_at FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 8");
    $stmt->execute([$uid]);
    echo json_encode($stmt->fetchAll());
}
