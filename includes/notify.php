<?php
/**
 * Smart Notification Engine
 * notify_user()              — send to one user (dedup 24h)
 * notify_users()             — send to array of user IDs
 * notify_by_role()           — send to all users of a role
 * generate_system_notifications() — called once per session, checks all smart alerts
 */

function notify_user($pdo, $user_id, $title, $message, $dedup_hours = 24) {
    if (!$user_id) return;
    if ($dedup_hours > 0) {
        $exists = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND title=? AND created_at > DATE_SUB(NOW(), INTERVAL ? HOUR)");
        $exists->execute([$user_id, $title, $dedup_hours]);
        if ($exists->fetchColumn() > 0) return;
    }
    $pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?,?,?)")
        ->execute([$user_id, $title, $message]);
}

function notify_users($pdo, array $user_ids, $title, $message, $dedup_hours = 24) {
    foreach (array_unique($user_ids) as $uid) {
        notify_user($pdo, $uid, $title, $message, $dedup_hours);
    }
}

function notify_by_role($pdo, $role, $title, $message, $dedup_hours = 24) {
    $ids = $pdo->prepare("SELECT id FROM users WHERE role=? AND is_active=1");
    $ids->execute([$role]);
    foreach ($ids->fetchAll(\PDO::FETCH_COLUMN) as $uid) {
        notify_user($pdo, $uid, $title, $message, $dedup_hours);
    }
}

// ── Trigger: Notice posted ───────────────────────────────────
function notify_notice_posted($pdo, $notice_id) {
    $n = $pdo->prepare("SELECT * FROM notices WHERE id=?");
    $n->execute([$notice_id]); $n = $n->fetch();
    if (!$n) return;

    $title = "📢 New Notice: " . mb_substr($n['title'], 0, 60);
    $msg   = mb_substr($n['body'], 0, 120) . (strlen($n['body']) > 120 ? '...' : '');

    if ($n['audience'] === 'all') {
        $ids = $pdo->query("SELECT id FROM users WHERE is_active=1")->fetchAll(\PDO::FETCH_COLUMN);
    } elseif ($n['audience'] === 'teachers') {
        $ids = $pdo->query("SELECT id FROM users WHERE role='teacher' AND is_active=1")->fetchAll(\PDO::FETCH_COLUMN);
    } elseif ($n['audience'] === 'students') {
        $ids = $pdo->query("SELECT id FROM users WHERE role='student' AND is_active=1")->fetchAll(\PDO::FETCH_COLUMN);
    } else {
        $ids = $pdo->query("SELECT id FROM users WHERE role='admin' AND is_active=1")->fetchAll(\PDO::FETCH_COLUMN);
    }
    notify_users($pdo, $ids, $title, $msg, 0); // no dedup for notices
}

// ── Trigger: Message received ────────────────────────────────
function notify_message_received($pdo, $message_id) {
    $m = $pdo->prepare("SELECT m.*, u.name AS sender_name FROM messages m JOIN users u ON m.sender_id=u.id WHERE m.id=?");
    $m->execute([$message_id]); $m = $m->fetch();
    if (!$m) return;

    $title = "✉️ Message from " . $m['sender_name'];
    $preview = mb_substr($m['body'], 0, 80) . (strlen($m['body']) > 80 ? '...' : '');

    $recipients = $pdo->prepare("SELECT user_id FROM message_recipients WHERE message_id=?");
    $recipients->execute([$message_id]);
    foreach ($recipients->fetchAll(\PDO::FETCH_COLUMN) as $uid) {
        notify_user($pdo, $uid, $title, $preview, 0);
    }
}

// ── Trigger: Class assigned to teacher ──────────────────────
function notify_class_assigned($pdo, $class_id, $teacher_id) {
    $cl = $pdo->prepare("SELECT cl.*, co.name AS course_name, co.code, ay.label AS year FROM classes cl JOIN courses co ON cl.course_id=co.id JOIN academic_years ay ON cl.academic_year_id=ay.id WHERE cl.id=?");
    $cl->execute([$class_id]); $cl = $cl->fetch();
    if (!$cl) return;

    $teacher_user = $pdo->prepare("SELECT user_id FROM teachers WHERE id=?");
    $teacher_user->execute([$teacher_id]); $teacher_user = $teacher_user->fetchColumn();

    if ($teacher_user) {
        notify_user($pdo, $teacher_user,
            "🏫 New Class Assigned",
            "You have been assigned to teach {$cl['code']} — {$cl['course_name']} (Section {$cl['section']}, {$cl['year']}). Schedule: " . ($cl['schedule'] ?? 'TBD'),
            0
        );
    }

    // Notify admins too
    $admin_ids = $pdo->query("SELECT id FROM users WHERE role='admin' AND is_active=1")->fetchAll(\PDO::FETCH_COLUMN);
    $t = $pdo->prepare("SELECT CONCAT(first_name,' ',last_name) AS name FROM teachers WHERE id=?");
    $t->execute([$teacher_id]); $t = $t->fetchColumn();
    foreach ($admin_ids as $aid) {
        notify_user($pdo, $aid, "📋 Class Assignment", "Teacher $t assigned to {$cl['code']} — {$cl['course_name']}.", 1);
    }
}

// ── Trigger: Exam added ──────────────────────────────────────
function notify_exam_added($pdo, $exam_id) {
    $ex = $pdo->prepare("SELECT e.*, co.name AS course_name, co.code, cl.section FROM exams e JOIN classes cl ON e.class_id=cl.id JOIN courses co ON cl.course_id=co.id WHERE e.id=?");
    $ex->execute([$exam_id]); $ex = $ex->fetch();
    if (!$ex) return;

    $date_str = $ex['exam_date'] ? date('D, M j, Y', strtotime($ex['exam_date'])) : 'TBD';
    $title = "📝 New Exam: {$ex['title']}";
    $msg   = "{$ex['code']} — {$ex['course_name']} (Section {$ex['section']}) | {$ex['type']} | Date: $date_str | Marks: {$ex['total_marks']}";

    // Notify all enrolled students
    $students = $pdo->prepare("SELECT u.id FROM enrollments en JOIN students s ON en.student_id=s.id JOIN users u ON s.user_id=u.id WHERE en.class_id=? AND en.status='Enrolled'");
    $students->execute([$ex['class_id']]);
    foreach ($students->fetchAll(\PDO::FETCH_COLUMN) as $uid) {
        notify_user($pdo, $uid, $title, $msg, 0);
    }
}

// ── Trigger: Grade entered for student ──────────────────────
function notify_grade_entered($pdo, $enrollment_id, $exam_id) {
    $data = $pdo->prepare("SELECT g.marks_obtained, g.grade_letter, ex.title AS exam_title, ex.total_marks, co.name AS course_name, co.code, s.user_id FROM grades g JOIN enrollments en ON g.enrollment_id=en.id JOIN students s ON en.student_id=s.id JOIN exams ex ON g.exam_id=ex.id JOIN classes cl ON en.class_id=cl.id JOIN courses co ON cl.course_id=co.id WHERE g.enrollment_id=? AND g.exam_id=?");
    $data->execute([$enrollment_id, $exam_id]); $data = $data->fetch();
    if (!$data) return;

    $pct = $data['total_marks'] > 0 ? round($data['marks_obtained'] / $data['total_marks'] * 100, 1) : 0;
    notify_user($pdo, $data['user_id'],
        "🎓 Grade Posted: {$data['exam_title']}",
        "{$data['code']} — {$data['course_name']}: You scored {$data['marks_obtained']}/{$data['total_marks']} ({$pct}%) — Grade: {$data['grade_letter']}",
        0
    );
}

// ── Trigger: Assignment posted ───────────────────────────────
function notify_assignment_posted($pdo, $assignment_id) {
    $a = $pdo->prepare("SELECT a.*, co.name AS course_name, co.code, cl.section, CONCAT(t.first_name,' ',t.last_name) AS teacher_name FROM assignments a JOIN classes cl ON a.class_id=cl.id JOIN courses co ON cl.course_id=co.id JOIN teachers t ON a.teacher_id=t.id WHERE a.id=?");
    $a->execute([$assignment_id]); $a = $a->fetch();
    if (!$a) return;

    $due = $a['due_date'] ? date('M j, Y', strtotime($a['due_date'])) : 'No deadline';
    $title = "📋 New Assignment: {$a['title']}";
    $msg   = "{$a['code']} — {$a['course_name']} by {$a['teacher_name']} | Due: $due";

    $students = $pdo->prepare("SELECT u.id FROM enrollments en JOIN students s ON en.student_id=s.id JOIN users u ON s.user_id=u.id WHERE en.class_id=? AND en.status='Enrolled'");
    $students->execute([$a['class_id']]);
    foreach ($students->fetchAll(\PDO::FETCH_COLUMN) as $uid) {
        notify_user($pdo, $uid, $title, $msg, 0);
    }
}

// ── Trigger: Payment recorded ────────────────────────────────
function notify_payment_recorded($pdo, $payment_id) {
    $p = $pdo->prepare("SELECT p.*, ft.name AS fee_name, s.user_id FROM payments p JOIN fee_types ft ON p.fee_type_id=ft.id JOIN students s ON p.student_id=s.id WHERE p.id=?");
    $p->execute([$payment_id]); $p = $p->fetch();
    if (!$p) return;

    $status_emoji = ['Paid'=>'✅','Partial'=>'🔶','Pending'=>'⏳','Overdue'=>'🚨','Waived'=>'🎁'][$p['status']] ?? '💳';
    notify_user($pdo, $p['user_id'],
        "$status_emoji Payment Update: {$p['fee_name']}",
        "Amount: \${$p['amount_paid']} of \${$p['amount_due']} — Status: {$p['status']}",
        0
    );
}

// ── System: auto-generated smart alerts ─────────────────────
function generate_system_notifications($pdo) {
    if (!empty($_SESSION['notif_generated'])) return;
    $_SESSION['notif_generated'] = true;

    $admin_ids = $pdo->query("SELECT id FROM users WHERE role='admin' AND is_active=1")->fetchAll(\PDO::FETCH_COLUMN);

    // 1. Overdue payments
    $overdue = $pdo->query("SELECT COUNT(*) FROM payments WHERE status='Overdue'")->fetchColumn();
    if ($overdue > 0) {
        foreach ($admin_ids as $aid) {
            notify_user($pdo, $aid, "⚠️ Overdue Payments", "$overdue payment(s) are overdue and need attention.");
        }
    }

    // 2. Exams in next 3 days — notify enrolled students + teacher
    $soon_exams = $pdo->query("
        SELECT e.id, e.title, e.exam_date, co.name AS course, cl.id AS class_id,
               t.user_id AS teacher_uid
        FROM exams e
        JOIN classes cl ON e.class_id=cl.id
        JOIN courses co ON cl.course_id=co.id
        JOIN teachers t ON cl.teacher_id=t.id
        WHERE e.exam_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)")->fetchAll();

    foreach ($soon_exams as $ex) {
        $date_str = date('D, M j', strtotime($ex['exam_date']));
        // Notify students
        $studs = $pdo->prepare("SELECT u.id FROM enrollments en JOIN students s ON en.student_id=s.id JOIN users u ON s.user_id=u.id WHERE en.class_id=? AND en.status='Enrolled'");
        $studs->execute([$ex['class_id']]);
        foreach ($studs->fetchAll(\PDO::FETCH_COLUMN) as $uid) {
            notify_user($pdo, $uid, "⏰ Exam Reminder: {$ex['title']}", "Your exam for {$ex['course']} is on $date_str. Prepare well!");
        }
        // Notify teacher
        notify_user($pdo, $ex['teacher_uid'], "📝 Upcoming Exam", "Exam '{$ex['title']}' for {$ex['course']} is on $date_str.");
        // Notify admins
        foreach ($admin_ids as $aid) {
            notify_user($pdo, $aid, "📝 Upcoming Exam", "'{$ex['title']}' ({$ex['course']}) is on $date_str.");
        }
    }

    // 3. Teachers who haven't entered grades for past exams (>3 days ago)
    $ungraded = $pdo->query("
        SELECT e.title, co.name AS course, cl.section,
               t.user_id AS teacher_uid,
               CONCAT(t.first_name,' ',t.last_name) AS teacher_name,
               COUNT(DISTINCT en.id) AS students,
               COUNT(DISTINCT g.id) AS graded
        FROM exams e
        JOIN classes cl ON e.class_id=cl.id
        JOIN courses co ON cl.course_id=co.id
        JOIN teachers t ON cl.teacher_id=t.id
        LEFT JOIN enrollments en ON en.class_id=cl.id AND en.status='Enrolled'
        LEFT JOIN grades g ON g.exam_id=e.id AND g.enrollment_id=en.id
        WHERE e.exam_date < DATE_SUB(CURDATE(), INTERVAL 3 DAY)
        GROUP BY e.id
        HAVING graded < students AND students > 0")->fetchAll();

    foreach ($ungraded as $u) {
        $pending = $u['students'] - $u['graded'];
        // Remind teacher
        notify_user($pdo, $u['teacher_uid'],
            "⚠️ Grades Pending: {$u['title']}",
            "You have $pending ungraded student(s) for '{$u['title']}' ({$u['course']}). Please enter grades."
        );
        // Alert admins
        foreach ($admin_ids as $aid) {
            notify_user($pdo, $aid,
                "🔴 Grades Not Entered",
                "{$u['teacher_name']} has $pending ungraded student(s) for '{$u['title']}' ({$u['course']}, §{$u['section']})."
            );
        }
    }

    // 4. Teachers not following schedule (no attendance marked today for scheduled classes)
    $today_day = date('D'); // Mon, Tue, etc.
    $no_att = $pdo->query("
        SELECT CONCAT(t.first_name,' ',t.last_name) AS teacher_name,
               t.user_id AS teacher_uid,
               co.name AS course, co.code, cl.section, cl.schedule
        FROM classes cl
        JOIN courses co ON cl.course_id=co.id
        JOIN teachers t ON cl.teacher_id=t.id
        WHERE cl.status='Open'
          AND cl.schedule IS NOT NULL
          AND cl.schedule LIKE '%{$today_day}%'
          AND cl.id NOT IN (
              SELECT DISTINCT en.class_id
              FROM attendance a
              JOIN enrollments en ON a.enrollment_id=en.id
              WHERE a.date=CURDATE()
          )")->fetchAll();

    foreach ($no_att as $na) {
        // Remind teacher
        notify_user($pdo, $na['teacher_uid'],
            "📋 Attendance Not Marked",
            "You haven't marked attendance today for {$na['code']} — {$na['course']} (§{$na['section']})."
        );
        // Alert admins
        foreach ($admin_ids as $aid) {
            notify_user($pdo, $aid,
                "📋 Attendance Missing",
                "{$na['teacher_name']} hasn't marked attendance for {$na['code']} — {$na['course']} (§{$na['section']}) today."
            );
        }
    }

    // 5. Visa expiring within 30 days
    $expiring = $pdo->query("SELECT CONCAT(first_name,' ',last_name) AS name, visa_expiry FROM students WHERE visa_expiry BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)")->fetchAll();
    foreach ($expiring as $st) {
        foreach ($admin_ids as $aid) {
            notify_user($pdo, $aid, "🛂 Visa Expiring Soon", "{$st['name']}'s visa expires on {$st['visa_expiry']}.");
        }
    }

    // 6. Students with attendance below 75%
    $low_att = $pdo->query("
        SELECT CONCAT(s.first_name,' ',s.last_name) AS name, u.id AS uid,
               COUNT(a.id) AS total, SUM(a.status='Present') AS present
        FROM students s
        JOIN users u ON s.user_id=u.id
        JOIN enrollments en ON en.student_id=s.id
        LEFT JOIN attendance a ON a.enrollment_id=en.id
        GROUP BY s.id
        HAVING total >= 5 AND (present/total*100) < 75")->fetchAll();

    foreach ($low_att as $la) {
        $rate = round($la['present'] / $la['total'] * 100);
        // Notify student
        notify_user($pdo, $la['uid'], "⚠️ Low Attendance Warning", "Your attendance is $rate% which is below the required 75%. Please attend classes regularly.");
        // Notify admins
        foreach ($admin_ids as $aid) {
            notify_user($pdo, $aid, "⚠️ Low Attendance", "{$la['name']} has only $rate% attendance.");
        }
    }

    // 7. Assignments due tomorrow — remind students
    $due_tomorrow = $pdo->query("
        SELECT a.title, co.code, co.name AS course, a.class_id
        FROM assignments a
        JOIN classes cl ON a.class_id=cl.id
        JOIN courses co ON cl.course_id=co.id
        WHERE a.due_date = DATE_ADD(CURDATE(), INTERVAL 1 DAY)
          AND a.status='Published'")->fetchAll();

    foreach ($due_tomorrow as $a) {
        $studs = $pdo->prepare("SELECT u.id FROM enrollments en JOIN students s ON en.student_id=s.id JOIN users u ON s.user_id=u.id LEFT JOIN assignment_submissions sub ON sub.assignment_id=? AND sub.student_id=s.id WHERE en.class_id=? AND en.status='Enrolled' AND sub.id IS NULL");
        $studs->execute([$a['class_id'], $a['class_id']]);
        foreach ($studs->fetchAll(\PDO::FETCH_COLUMN) as $uid) {
            notify_user($pdo, $uid, "⏰ Assignment Due Tomorrow", "'{$a['title']}' for {$a['code']} — {$a['course']} is due tomorrow. Submit now!");
        }
    }

    // 8. New student enrolled — notify teacher
    $new_enrollments = $pdo->query("
        SELECT en.id, CONCAT(s.first_name,' ',s.last_name) AS student_name,
               co.code, co.name AS course, cl.section, t.user_id AS teacher_uid
        FROM enrollments en
        JOIN students s ON en.student_id=s.id
        JOIN classes cl ON en.class_id=cl.id
        JOIN courses co ON cl.course_id=co.id
        JOIN teachers t ON cl.teacher_id=t.id
        WHERE en.enrolled_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)")->fetchAll();

    foreach ($new_enrollments as $en) {
        notify_user($pdo, $en['teacher_uid'],
            "👤 New Student Enrolled",
            "{$en['student_name']} enrolled in your class {$en['code']} — {$en['course']} (§{$en['section']})."
        );
    }
}

