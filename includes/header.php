<?php
$flash = get_flash();
require_once __DIR__.'/notify.php';
// Only generate system notifications once per minute per user, not on every page load
if (empty($_SESSION['notif_generated_at']) || (time() - $_SESSION['notif_generated_at']) > 60) {
    generate_system_notifications($pdo);
    $_SESSION['notif_generated_at'] = time();
}
$uid = $_SESSION['user']['id'] ?? 0;
$role = $_SESSION['user']['role'] ?? '';

// Fetch notification count and messages in one query
$notif_data = $pdo->prepare("SELECT
    SUM(is_read=0) AS unread_count,
    COUNT(*) AS total
    FROM notifications WHERE user_id=?");
$notif_data->execute([$uid]); $notif_data = $notif_data->fetch();
$notif_count = (int)($notif_data['unread_count'] ?? 0);

$notif_list = $pdo->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 6");
$notif_list->execute([$uid]); $notif_list = $notif_list->fetchAll();

$msg_unread = $pdo->prepare("SELECT COUNT(*) FROM message_recipients WHERE user_id=? AND is_read=0");
$msg_unread->execute([$uid]); $msg_unread = (int)$msg_unread->fetchColumn();

$ap = $active_page ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($page_title ?? APP_NAME) ?> â€” <?= APP_NAME ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/assets/favicon.svg">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=2.1">
<!-- Apply sidebar state BEFORE render to avoid flash -->
<script>
  if (localStorage.getItem('sidebar_collapsed') === '1') {
    document.documentElement.classList.add('sidebar-pre-collapsed');
  } else {
    // Default: expanded — clear any stale collapsed state
    localStorage.removeItem('sidebar_collapsed');
  }
</script>
<style>
  html.sidebar-pre-collapsed #sidebar { width: 68px; }
  html.sidebar-pre-collapsed .main-wrapper { margin-left: 68px; }
  html.sidebar-pre-collapsed .nav-item span,
  html.sidebar-pre-collapsed .nav-section,
  html.sidebar-pre-collapsed .sidebar-brand span { display: none; }
  html.sidebar-pre-collapsed .nav-item { justify-content: center; padding: 14px; }
</style>
</head>
<body>

<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <div class="sidebar-brand-icon"><i class="fas fa-graduation-cap"></i></div>
    <span><?= APP_NAME ?></span>
  </div>
  <nav class="sidebar-nav">

    <?php if (is_super_admin()): ?>
    <div class="nav-section">MAIN</div>
    <a href="<?= BASE_URL ?>/modules/super_admin/dashboard.php" class="nav-item <?= $ap==='super_dashboard'?'active':'' ?>"><i class="fas fa-crown ni-gold" style="color:#f4a261"></i><span>Super Dashboard</span></a>
    <a href="<?= BASE_URL ?>/modules/super_admin/branches.php" class="nav-item <?= $ap==='super_branches'?'active':'' ?>"><i class="fas fa-building ni-orange"></i><span>Manage Branches</span></a>
    <a href="<?= BASE_URL ?>/modules/super_admin/reports.php" class="nav-item <?= $ap==='super_reports'?'active':'' ?>"><i class="fas fa-chart-line ni-purple"></i><span>Global Reports</span></a>
    <a href="<?= BASE_URL ?>/modules/super_admin/admins.php" class="nav-item <?= $ap==='super_admins'?'active':'' ?>"><i class="fas fa-user-shield ni-blue"></i><span>Branch Admins</span></a>
    <a href="<?= BASE_URL ?>/modules/super_admin/branch_activity.php" class="nav-item <?= $ap==='super_branch_activity'?'active':'' ?>"><i class="fas fa-history ni-teal"></i><span>Branch Activity</span></a>

    <?php elseif ($role === 'parent'): ?>
    <div class="nav-section">MAIN</div>
    <a href="<?= BASE_URL ?>/modules/parent/dashboard.php" class="nav-item <?= $ap==='parent_dashboard'?'active':'' ?>"><i class="fas fa-home ni-blue"></i><span>My Dashboard</span></a>
    <div class="nav-section">MY CHILDREN</div>
    <a href="<?= BASE_URL ?>/modules/parent/dashboard.php" class="nav-item <?= $ap==='parent_dashboard'?'active':'' ?>"><i class="fas fa-user-graduate ni-teal"></i><span>Academic Progress</span></a>
    <div class="nav-section">COMMUNICATION</div>
    <a href="<?= BASE_URL ?>/modules/notices/index.php" class="nav-item <?= $ap==='notices'?'active':'' ?>"><i class="fas fa-bullhorn ni-yellow"></i><span>Notice Board</span></a>
    <a href="<?= BASE_URL ?>/modules/messages/index.php" class="nav-item <?= $ap==='messages'?'active':'' ?>"><i class="fas fa-envelope ni-blue"></i><span>Messages<?php if ($msg_unread > 0): ?> <span class="nav-badge"><?= $msg_unread ?></span><?php endif; ?></span></a>
    <a href="<?= BASE_URL ?>/modules/notifications/index.php" class="nav-item <?= $ap==='notifications'?'active':'' ?>"><i class="fas fa-bell ni-orange"></i><span>Notifications<?php if ($notif_count > 0): ?> <span class="nav-badge"><?= $notif_count ?></span><?php endif; ?></span></a>

    <?php elseif ($role === 'librarian'): ?>
    <div class="nav-section">LIBRARY</div>
    <a href="<?= BASE_URL ?>/modules/library/librarian.php" class="nav-item <?= $ap==='librarian_dash'?'active':'' ?>"><i class="fas fa-chart-bar ni-blue"></i><span>Library Dashboard</span></a>
    <a href="<?= BASE_URL ?>/modules/library/librarian_desk.php" class="nav-item <?= $ap==='librarian_desk'?'active':'' ?>"><i class="fas fa-tasks ni-orange"></i><span>Librarian Desk</span></a>
    <a href="<?= BASE_URL ?>/modules/library/index.php" class="nav-item <?= $ap==='library'?'active':'' ?>"><i class="fas fa-book-open ni-teal"></i><span>Book Catalog</span></a>
    <div class="nav-section">COMMUNICATION</div>
    <a href="<?= BASE_URL ?>/modules/notices/index.php" class="nav-item <?= $ap==='notices'?'active':'' ?>"><i class="fas fa-bullhorn ni-yellow"></i><span>Notice Board</span></a>
    <a href="<?= BASE_URL ?>/modules/messages/index.php" class="nav-item <?= $ap==='messages'?'active':'' ?>"><i class="fas fa-envelope ni-blue"></i><span>Messages<?php if ($msg_unread > 0): ?> <span class="nav-badge"><?= $msg_unread ?></span><?php endif; ?></span></a>
    <a href="<?= BASE_URL ?>/modules/notifications/index.php" class="nav-item <?= $ap==='notifications'?'active':'' ?>"><i class="fas fa-bell ni-orange"></i><span>Notifications<?php if ($notif_count > 0): ?> <span class="nav-badge"><?= $notif_count ?></span><?php endif; ?></span></a>

    <?php elseif ($role === 'student'): ?>
    <div class="nav-section">MAIN</div>
    <a href="<?= BASE_URL ?>/modules/students/dashboard.php" class="nav-item <?= $ap==='student_dashboard'?'active':'' ?>"><i class="fas fa-tachometer-alt ni-blue"></i><span>My Dashboard</span></a>
    <div class="nav-section">ACADEMICS</div>
    <a href="<?= BASE_URL ?>/modules/assignments/index.php" class="nav-item <?= $ap==='assignments'?'active':'' ?>"><i class="fas fa-tasks ni-indigo"></i><span>Assignments</span></a>
    <a href="<?= BASE_URL ?>/modules/online_exam/index.php" class="nav-item <?= $ap==='online_exam'?'active':'' ?>"><i class="fas fa-laptop-code ni-purple"></i><span>Online Exams</span></a>
    <a href="<?= BASE_URL ?>/modules/timetable/index.php" class="nav-item <?= $ap==='timetable'?'active':'' ?>"><i class="fas fa-calendar-week ni-teal"></i><span>Timetable</span></a>
    <a href="<?= BASE_URL ?>/modules/calendar/index.php" class="nav-item <?= $ap==='calendar'?'active':'' ?>"><i class="fas fa-calendar-alt ni-blue"></i><span>Calendar</span></a>
    <div class="nav-section">LIBRARY</div>
    <a href="<?= BASE_URL ?>/modules/library/index.php" class="nav-item <?= $ap==='library'?'active':'' ?>"><i class="fas fa-book-open ni-teal"></i><span>Library</span></a>
    <a href="<?= BASE_URL ?>/modules/library/my.php" class="nav-item <?= $ap==='my_library'?'active':'' ?>"><i class="fas fa-book-reader ni-blue"></i><span>My Books</span></a>
    <div class="nav-section">COMMUNICATION</div>
    <a href="<?= BASE_URL ?>/modules/notices/index.php" class="nav-item <?= $ap==='notices'?'active':'' ?>"><i class="fas fa-bullhorn ni-yellow"></i><span>Notice Board</span></a>
    <a href="<?= BASE_URL ?>/modules/messages/index.php" class="nav-item <?= $ap==='messages'?'active':'' ?>"><i class="fas fa-envelope ni-blue"></i><span>Messages<?php if ($msg_unread > 0): ?> <span class="nav-badge"><?= $msg_unread ?></span><?php endif; ?></span></a>
    <a href="<?= BASE_URL ?>/modules/notifications/index.php" class="nav-item <?= $ap==='notifications'?'active':'' ?>"><i class="fas fa-bell ni-orange"></i><span>Notifications<?php if ($notif_count > 0): ?> <span class="nav-badge"><?= $notif_count ?></span><?php endif; ?></span></a>

    <?php elseif ($role === 'teacher'): ?>
    <div class="nav-section">MAIN</div>
    <a href="<?= BASE_URL ?>/modules/teacher/dashboard.php" class="nav-item <?= $ap==='teacher_dashboard'?'active':'' ?>"><i class="fas fa-chalkboard ni-teal"></i><span>My Classes</span></a>
    <div class="nav-section">ACADEMICS</div>
    <a href="<?= BASE_URL ?>/modules/students/index.php" class="nav-item <?= $ap==='students'?'active':'' ?>"><i class="fas fa-user-graduate ni-blue"></i><span>Students</span></a>
    <a href="<?= BASE_URL ?>/modules/exams/index.php" class="nav-item <?= $ap==='exams'?'active':'' ?>"><i class="fas fa-file-alt ni-yellow"></i><span>Exams</span></a>
    <a href="<?= BASE_URL ?>/modules/grades/index.php" class="nav-item <?= $ap==='grades'?'active':'' ?>"><i class="fas fa-star-half-alt ni-gold"></i><span>Grades</span></a>
    <a href="<?= BASE_URL ?>/modules/attendance/index.php" class="nav-item <?= $ap==='attendance'?'active':'' ?>"><i class="fas fa-calendar-check ni-green"></i><span>Attendance</span></a>
    <a href="<?= BASE_URL ?>/modules/assignments/index.php" class="nav-item <?= $ap==='assignments'?'active':'' ?>"><i class="fas fa-tasks ni-indigo"></i><span>Assignments</span></a>
    <a href="<?= BASE_URL ?>/modules/online_exam/index.php" class="nav-item <?= $ap==='online_exam'?'active':'' ?>"><i class="fas fa-laptop-code ni-purple"></i><span>Online Exams</span></a>
    <a href="<?= BASE_URL ?>/modules/feedback/index.php" class="nav-item <?= $ap==='feedback'?'active':'' ?>"><i class="fas fa-comment-alt ni-teal"></i><span>Student Feedback</span></a>
    <a href="<?= BASE_URL ?>/modules/timetable/index.php" class="nav-item <?= $ap==='timetable'?'active':'' ?>"><i class="fas fa-calendar-week ni-teal"></i><span>Timetable</span></a>
    <a href="<?= BASE_URL ?>/modules/calendar/index.php" class="nav-item <?= $ap==='calendar'?'active':'' ?>"><i class="fas fa-calendar-alt ni-blue"></i><span>Calendar</span></a>
    <div class="nav-section">LIBRARY</div>
    <a href="<?= BASE_URL ?>/modules/library/index.php" class="nav-item <?= $ap==='library'?'active':'' ?>"><i class="fas fa-book-open ni-teal"></i><span>Library</span></a>
    <a href="<?= BASE_URL ?>/modules/library/my.php" class="nav-item <?= $ap==='my_library'?'active':'' ?>"><i class="fas fa-book-reader ni-blue"></i><span>My Books</span></a>
    <div class="nav-section">COMMUNICATION</div>
    <a href="<?= BASE_URL ?>/modules/notices/index.php" class="nav-item <?= $ap==='notices'?'active':'' ?>"><i class="fas fa-bullhorn ni-yellow"></i><span>Notice Board</span></a>
    <a href="<?= BASE_URL ?>/modules/messages/index.php" class="nav-item <?= $ap==='messages'?'active':'' ?>"><i class="fas fa-envelope ni-blue"></i><span>Messages<?php if ($msg_unread > 0): ?> <span class="nav-badge"><?= $msg_unread ?></span><?php endif; ?></span></a>
    <a href="<?= BASE_URL ?>/modules/notifications/index.php" class="nav-item <?= $ap==='notifications'?'active':'' ?>"><i class="fas fa-bell ni-orange"></i><span>Notifications<?php if ($notif_count > 0): ?> <span class="nav-badge"><?= $notif_count ?></span><?php endif; ?></span></a>
    <div class="nav-section">REPORTS</div>
    <a href="<?= BASE_URL ?>/modules/reports/index.php" class="nav-item <?= $ap==='reports'?'active':'' ?>"><i class="fas fa-chart-bar ni-purple"></i><span>Reports</span></a>

    <?php else: ?>
    <!-- ADMIN -->
    <div class="nav-section">MAIN</div>
    <a href="<?= BASE_URL ?>/dashboard.php" class="nav-item <?= $ap==='dashboard'?'active':'' ?>"><i class="fas fa-tachometer-alt ni-blue"></i><span>Dashboard</span></a>
    <div class="nav-section">ACADEMICS</div>
    <a href="<?= BASE_URL ?>/modules/students/index.php" class="nav-item <?= $ap==='students'?'active':'' ?>"><i class="fas fa-user-graduate ni-blue"></i><span>Students</span></a>
    <a href="<?= BASE_URL ?>/modules/students/lookup.php" class="nav-item <?= $ap==='student_lookup'?'active':'' ?>"><i class="fas fa-id-card ni-teal"></i><span>ID Lookup</span></a>
    <a href="<?= BASE_URL ?>/modules/students/parents.php" class="nav-item <?= $ap==='parents'?'active':'' ?>"><i class="fas fa-user-friends ni-purple"></i><span>Parents</span></a>
    <a href="<?= BASE_URL ?>/modules/teachers/index.php" class="nav-item <?= $ap==='teachers'?'active':'' ?>"><i class="fas fa-chalkboard-teacher ni-purple"></i><span>Teachers</span></a>
    <a href="<?= BASE_URL ?>/modules/courses/index.php" class="nav-item <?= $ap==='courses'?'active':'' ?>"><i class="fas fa-book-open ni-teal"></i><span>Courses</span></a>
    <a href="<?= BASE_URL ?>/modules/classes/index.php" class="nav-item <?= $ap==='classes'?'active':'' ?>"><i class="fas fa-door-open ni-orange"></i><span>Classes</span></a>
    <a href="<?= BASE_URL ?>/modules/exams/index.php" class="nav-item <?= $ap==='exams'?'active':'' ?>"><i class="fas fa-file-alt ni-yellow"></i><span>Exams</span></a>
    <a href="<?= BASE_URL ?>/modules/grades/index.php" class="nav-item <?= $ap==='grades'?'active':'' ?>"><i class="fas fa-star-half-alt ni-gold"></i><span>Grades</span></a>
    <a href="<?= BASE_URL ?>/modules/attendance/index.php" class="nav-item <?= $ap==='attendance'?'active':'' ?>"><i class="fas fa-calendar-check ni-green"></i><span>Attendance</span></a>
    <a href="<?= BASE_URL ?>/modules/assignments/index.php" class="nav-item <?= $ap==='assignments'?'active':'' ?>"><i class="fas fa-tasks ni-indigo"></i><span>Assignments</span></a>
    <a href="<?= BASE_URL ?>/modules/online_exam/index.php" class="nav-item <?= $ap==='online_exam'?'active':'' ?>"><i class="fas fa-laptop-code ni-purple"></i><span>Online Exams</span></a>
    <a href="<?= BASE_URL ?>/modules/timetable/index.php" class="nav-item <?= $ap==='timetable'?'active':'' ?>"><i class="fas fa-calendar-week ni-teal"></i><span>Timetable</span></a>
    <a href="<?= BASE_URL ?>/modules/calendar/index.php" class="nav-item <?= $ap==='calendar'?'active':'' ?>"><i class="fas fa-calendar-alt ni-blue"></i><span>Calendar</span></a>
    <a href="<?= BASE_URL ?>/modules/results/index.php" class="nav-item <?= $ap==='results'?'active':'' ?>"><i class="fas fa-graduation-cap ni-blue"></i><span>Year Results</span></a>
    <div class="nav-section">FINANCE</div>
    <a href="<?= BASE_URL ?>/modules/payments/index.php" class="nav-item <?= $ap==='payments'?'active':'' ?>"><i class="fas fa-credit-card ni-green"></i><span>Payments</span></a>
    <div class="nav-section">LIBRARY</div>
    <a href="<?= BASE_URL ?>/modules/library/index.php" class="nav-item <?= $ap==='library'?'active':'' ?>"><i class="fas fa-book-open ni-teal"></i><span>Library</span></a>
    <div class="nav-section">COMMUNICATION</div>
    <a href="<?= BASE_URL ?>/modules/notices/index.php" class="nav-item <?= $ap==='notices'?'active':'' ?>"><i class="fas fa-bullhorn ni-yellow"></i><span>Notice Board</span></a>
    <a href="<?= BASE_URL ?>/modules/messages/index.php" class="nav-item <?= $ap==='messages'?'active':'' ?>"><i class="fas fa-envelope ni-blue"></i><span>Messages<?php if ($msg_unread > 0): ?> <span class="nav-badge"><?= $msg_unread ?></span><?php endif; ?></span></a>
    <a href="<?= BASE_URL ?>/modules/notifications/index.php" class="nav-item <?= $ap==='notifications'?'active':'' ?>"><i class="fas fa-bell ni-orange"></i><span>Notifications<?php if ($notif_count > 0): ?> <span class="nav-badge"><?= $notif_count ?></span><?php endif; ?></span></a>
    <div class="nav-section">REPORTS & ADMIN</div>
    <a href="<?= BASE_URL ?>/modules/reports/index.php" class="nav-item <?= $ap==='reports'?'active':'' ?>"><i class="fas fa-chart-bar ni-purple"></i><span>Reports</span></a>
    <a href="<?= BASE_URL ?>/modules/registrar/index.php" class="nav-item <?= $ap==='registrar'?'active':'' ?>"><i class="fas fa-university ni-blue"></i><span>Registrar</span></a>
    <a href="<?= BASE_URL ?>/modules/feedback/index.php" class="nav-item <?= $ap==='feedback'?'active':'' ?>"><i class="fas fa-comment-alt ni-teal"></i><span>Student Feedback</span></a>
    <a href="<?= BASE_URL ?>/modules/clearance/index.php" class="nav-item <?= $ap==='clearance'?'active':'' ?>"><i class="fas fa-clipboard-check ni-orange"></i><span>Clearance</span></a>
    <a href="<?= BASE_URL ?>/modules/transfer/index.php" class="nav-item <?= $ap==='transfer'?'active':'' ?>"><i class="fas fa-file-export ni-purple"></i><span>Transfer Certs</span></a>
    <a href="<?= BASE_URL ?>/modules/disciplinary/index.php" class="nav-item <?= $ap==='disciplinary'?'active':'' ?>"><i class="fas fa-gavel ni-red"></i><span>Disciplinary</span></a>
    <a href="<?= BASE_URL ?>/modules/alumni/index.php" class="nav-item <?= $ap==='alumni'?'active':'' ?>"><i class="fas fa-user-graduate ni-gold"></i><span>Alumni</span></a>
    <a href="<?= BASE_URL ?>/modules/documents/index.php" class="nav-item <?= $ap==='documents'?'active':'' ?>"><i class="fas fa-folder-open ni-teal"></i><span>Documents</span></a>
    <a href="<?= BASE_URL ?>/modules/settings/index.php" class="nav-item <?= $ap==='settings'?'active':'' ?>"><i class="fas fa-cog ni-gray"></i><span>Settings</span></a>
    <a href="<?= BASE_URL ?>/modules/settings/api_keys.php" class="nav-item <?= $ap==='api_keys'?'active':'' ?>"><i class="fas fa-key ni-yellow"></i><span>API Keys</span></a>
    <a href="<?= BASE_URL ?>/modules/activity/index.php" class="nav-item <?= $ap==='activity'?'active':'' ?>"><i class="fas fa-history ni-teal"></i><span>Activity Log</span></a>
    <?php endif; ?>
  </nav>
  <div class="sidebar-footer"><?= APP_NAME ?> v<?= APP_VERSION ?><br><span style="font-size:.6rem;opacity:.6">Developed by EthioOps Software QA & DevOps Consulting Services Â© 2026</span></div>
</aside>

<div class="main-wrapper" id="mainWrapper">
  <header class="topbar">
    <!-- NO inline onclick â€” handled entirely by app.js -->
    <button class="sidebar-toggle" id="sidebarToggle" title="Toggle sidebar">
      <i class="fas fa-bars"></i>
    </button>
    <div class="topbar-title"><?= e($page_title ?? 'Dashboard') ?></div>
    <div class="topbar-right">

      <?php if ($role === 'admin'):
        $all_branches = $pdo->query("SELECT * FROM branches WHERE is_active=1 ORDER BY is_main DESC, name")->fetchAll();
        $cur_branch = get_current_branch();
        if (count($all_branches) > 0):
      ?>
      <div style="display:flex;align-items:center;gap:6px;margin-right:8px">
        <i class="fas fa-code-branch" style="color:#aaa;font-size:.85rem"></i>
        <form method="GET" style="margin:0" id="branchSwitchForm">
          <select onchange="document.getElementById('branchSwitchForm').submit()" name="switch_branch"
            style="border:1.5px solid #e0e0e0;border-radius:8px;padding:5px 10px;font-size:.8rem;background:#f8f9ff;color:#444;cursor:pointer">
            <option value="0" <?= !$cur_branch?'selected':'' ?>>All Branches</option>
            <?php foreach ($all_branches as $b): ?>
            <option value="<?= $b['id'] ?>" <?= $cur_branch==$b['id']?'selected':'' ?>><?= e($b['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </form>
        <?php if ($cur_branch):
          $bn = $pdo->prepare("SELECT name FROM branches WHERE id=?"); $bn->execute([$cur_branch]); $bn = $bn->fetchColumn();
          // reuse already-fetched branch name from the list above
          $bn = '';
          foreach ($all_branches as $b) { if ($b['id'] == $cur_branch) { $bn = $b['name']; break; } }
        ?>
        <span style="background:#4361ee22;color:#4361ee;font-size:.72rem;font-weight:700;padding:3px 8px;border-radius:10px"><?= e($bn) ?></span>
        <?php endif; ?>
      </div>
      <?php endif; endif; ?>

      <!-- Notification bell -->
      <div class="notif-bell" id="notifBell" style="position:relative;cursor:pointer">
        <button class="notif-bell-btn" title="Notifications">
          <i class="fas fa-bell"></i>
        </button>
        <?php if ($notif_count > 0): ?>
        <span class="badge" id="notifBadge"><?= $notif_count ?></span>
        <?php endif; ?>
        <div id="notifDropdown" class="notif-dropdown" style="display:none">
          <div class="notif-dropdown-header">
            <strong>Notifications</strong>
            <a href="<?= BASE_URL ?>/modules/notifications/index.php?mark_all=1">Mark all read</a>
          </div>
          <?php if ($notif_list): ?>
          <?php foreach ($notif_list as $n): ?>
          <a href="<?= BASE_URL ?>/modules/notifications/index.php?read=<?= $n['id'] ?>" class="notif-item <?= $n['is_read']?'':'notif-unread' ?>">
            <div class="notif-icon <?= $n['is_read']?'':'notif-icon-active' ?>">
              <i class="fas fa-bell"></i>
            </div>
            <div class="notif-body">
              <div class="notif-title"><?= e($n['title']) ?></div>
              <div class="notif-msg"><?= e(mb_substr($n['message'],0,60)) ?></div>
              <div class="notif-time"><?= date('M j, g:i A', strtotime($n['created_at'])) ?></div>
            </div>
          </a>
          <?php endforeach; ?>
          <?php else: ?>
          <div class="notif-empty"><i class="fas fa-bell-slash"></i><p>No notifications</p></div>
          <?php endif; ?>
          <a href="<?= BASE_URL ?>/modules/notifications/index.php" class="notif-footer">View all notifications</a>
        </div>
      </div>

      <!-- User menu -->
      <div class="user-menu" id="userMenu" onclick="toggleUserMenu(event)">
        <div class="user-avatar"><?= strtoupper(substr($_SESSION['user']['name']??'A',0,2)) ?></div>
        <div class="user-info">
          <span class="user-name"><?= e($_SESSION['user']['name'] ?? 'Admin') ?></span>
          <span class="user-role"><?= ucfirst($role) ?></span>
        </div>
        <i class="fas fa-chevron-down" style="font-size:.65rem;color:#94a3b8;margin-left:2px"></i>
        <div class="dropdown" id="userDropdown">
          <a href="<?= BASE_URL ?>/profile.php"><i class="fas fa-user" style="color:var(--primary)"></i> Profile</a>
          <a href="<?= BASE_URL ?>/logout.php"><i class="fas fa-sign-out-alt" style="color:var(--danger)"></i> Logout</a>
        </div>
      </div>
      <script>
      function toggleUserMenu(e) {
        e.stopPropagation();
        document.getElementById('userDropdown').classList.toggle('open');
      }
      document.addEventListener('click', function() {
        document.getElementById('userDropdown').classList.remove('open');
      });
      </script>

    </div>
  </header>

  <main class="content">
    <?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] ?>">
      <i class="fas fa-<?= $flash['type']==='success'?'check-circle':'exclamation-circle' ?>"></i>
      <?= e($flash['msg']) ?>
      <button onclick="this.parentElement.remove()" style="margin-left:auto;background:none;border:none;cursor:pointer;font-size:1.1rem">Ã—</button>
    </div>
    <?php endif; ?>
