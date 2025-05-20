<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once '../db.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../login.php");
    exit();
}

// Get teacher's profile information
$stmt = $conn->prepare("
    SELECT u.*, ts.office_hours, ts.notification_preferences, ts.availability
    FROM users u
    LEFT JOIN teacher_settings ts ON u.id = ts.user_id
    WHERE u.id = ?
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();

// Get all courses for this teacher
$stmt = $conn->prepare("
    SELECT c.*, 
           COUNT(DISTINCT ce.student_id) as enrolled_students,
           COUNT(DISTINCT a.id) as total_assignments,
           COUNT(DISTINCT m.id) as total_materials,
           (SELECT AVG(score) FROM evaluations WHERE course_id = c.id) as average_score,
           (SELECT AVG(grade) FROM grades WHERE course_id = c.id) as average_grade,
           (SELECT COUNT(*) FROM evaluations WHERE course_id = c.id) as total_evaluations,
           (SELECT COUNT(*) FROM grades WHERE course_id = c.id) as graded_count,
           (SELECT COUNT(*) FROM assignment_submissions a 
            JOIN assignments ass ON a.assignment_id = ass.id 
            WHERE ass.course_id = c.id AND a.status = 'pending') as pending_submissions
    FROM courses c
    LEFT JOIN course_enrollments ce ON c.id = ce.course_id
    LEFT JOIN assignments a ON c.id = a.course_id
    LEFT JOIN materials m ON c.id = m.course_id
    WHERE c.teacher_id = ?
    GROUP BY c.id
    ORDER BY c.created_at DESC
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get upcoming classes for the next 7 days
$stmt = $conn->prepare("
    SELECT cs.*, c.name as course_name, c.id as course_id,
           (SELECT COUNT(*) FROM course_enrollments WHERE course_id = c.id) as student_count
    FROM course_schedule cs
    JOIN courses c ON cs.course_id = c.id
    WHERE cs.day_of_week IN (
        DAYNAME(CURRENT_DATE),
        DAYNAME(DATE_ADD(CURRENT_DATE, INTERVAL 1 DAY)),
        DAYNAME(DATE_ADD(CURRENT_DATE, INTERVAL 2 DAY)),
        DAYNAME(DATE_ADD(CURRENT_DATE, INTERVAL 3 DAY)),
        DAYNAME(DATE_ADD(CURRENT_DATE, INTERVAL 4 DAY)),
        DAYNAME(DATE_ADD(CURRENT_DATE, INTERVAL 5 DAY)),
        DAYNAME(DATE_ADD(CURRENT_DATE, INTERVAL 6 DAY))
    )
    AND c.teacher_id = ?
    ORDER BY FIELD(cs.day_of_week, 
        DAYNAME(CURRENT_DATE),
        DAYNAME(DATE_ADD(CURRENT_DATE, INTERVAL 1 DAY)),
        DAYNAME(DATE_ADD(CURRENT_DATE, INTERVAL 2 DAY)),
        DAYNAME(DATE_ADD(CURRENT_DATE, INTERVAL 3 DAY)),
        DAYNAME(DATE_ADD(CURRENT_DATE, INTERVAL 4 DAY)),
        DAYNAME(DATE_ADD(CURRENT_DATE, INTERVAL 5 DAY)),
        DAYNAME(DATE_ADD(CURRENT_DATE, INTERVAL 6 DAY))
    ), cs.start_time ASC
    LIMIT 7
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$upcoming_classes_result = $stmt->get_result();
$upcoming_classes = [];
while ($row = $upcoming_classes_result->fetch_assoc()) {
    $upcoming_classes[] = $row;
}

// Get pending tasks
$stmt = $conn->prepare("
    SELECT 
        'assignment' as type,
        a.id,
        a.title,
        c.name as course_name,
        a.due_date,
        COUNT(s.id) as pending_count
    FROM assignments a
    JOIN courses c ON a.course_id = c.id
    LEFT JOIN assignment_submissions s ON s.assignment_id = a.id AND s.status = 'pending'
    WHERE c.teacher_id = ?
    AND a.due_date >= CURDATE()
    GROUP BY a.id
    HAVING pending_count > 0
    UNION ALL
    SELECT 
        'enrollment' as type,
        er.id,
        'Enrollment Request' as title,
        c.name as course_name,
        er.created_at as due_date,
        1 as pending_count
    FROM enrollment_requests er
    JOIN courses c ON er.course_id = c.id
    WHERE c.teacher_id = ? AND er.status = 'pending'
    ORDER BY due_date ASC
    LIMIT 10
");
$stmt->bind_param("ii", $_SESSION['user_id'], $_SESSION['user_id']);
$stmt->execute();
$pending_tasks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get recent announcements
$stmt = $conn->prepare("
    SELECT 
        a.id,
        a.title,
        a.content,
        a.created_at,
        u.name as author_name,
        c.name as course_name
    FROM announcements a
    JOIN users u ON a.author_id = u.id
    JOIN courses c ON a.course_id = c.id
    WHERE a.target_role = 'teacher' OR a.target_role = 'all'
    ORDER BY a.created_at DESC
    LIMIT 5
");
$stmt->execute();
$announcements = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get recent messages
$stmt = $conn->prepare("
    SELECT 
        m.id,
        m.content,
        m.created_at,
        m.is_read,
        u.name as sender_name,
        c.name as course_name
    FROM messages m
    JOIN users u ON m.sender_id = u.id
    JOIN courses c ON m.course_id = c.id
    WHERE m.receiver_id = ?
    ORDER BY m.created_at DESC
    LIMIT 5
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate statistics
$total_courses = count($courses);
$total_students = array_sum(array_column($courses, 'enrolled_students'));
$total_assignments = array_sum(array_column($courses, 'total_assignments'));
$total_pending = array_sum(array_column($courses, 'pending_submissions'));
$total_evaluations = array_sum(array_column($courses, 'total_evaluations'));

$average_score = 0;
$evaluated_courses = array_filter($courses, function($course) {
    return isset($course['average_score']) && $course['average_score'] !== null && $course['average_score'] > 0;
});
if (count($evaluated_courses) > 0) {
    $average_score = array_sum(array_column($evaluated_courses, 'average_score')) / count($evaluated_courses);
}

$average_grade = 0;
$graded_courses = array_filter($courses, function($course) {
    return isset($course['average_grade']) && $course['average_grade'] !== null && $course['average_grade'] > 0;
});
if (count($graded_courses) > 0) {
    $average_grade = array_sum(array_column($graded_courses, 'average_grade')) / count($graded_courses);
}
?>

<!DOCTYPE html>
<html lang="mn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Багшийн хяналтын самбар - Сургалтын Үнэлгээний Систем</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .stat-card {
            transition: transform 0.2s;
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            height: 100%;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .dashboard-section {
            background: #fff;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        .nav-pills .nav-link {
            color: #495057;
            border-radius: 8px;
            padding: 10px 20px;
            margin: 5px;
        }
        .nav-pills .nav-link.active {
            background-color: #0d6efd;
            color: white;
        }
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            padding: 3px 6px;
            border-radius: 50%;
            background: #dc3545;
            color: white;
            font-size: 0.7rem;
        }
        .calendar-event {
            cursor: pointer;
            padding: 5px;
            margin: 2px 0;
            border-radius: 4px;
            background: #e9ecef;
        }
        .calendar-event:hover {
            background: #dee2e6;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
                <div class="position-sticky pt-3">
                    <div class="text-center mb-4">
                        <img src="../assets/default-avatar.png" alt="Profile" class="rounded-circle" width="80">
                        <h5 class="mt-2"><?php echo htmlspecialchars($profile['name']); ?></h5>
                        <p class="text-muted"><?php echo htmlspecialchars($profile['email']); ?></p>
                    </div>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="#overview" data-bs-toggle="tab">
                                <i class="bi bi-house-door"></i> Хяналтын самбар
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#classes" data-bs-toggle="tab">
                                <i class="bi bi-book"></i> Миний хичээлүүд
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#students" data-bs-toggle="tab">
                                <i class="bi bi-people"></i> Сурагчид
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#assignments" data-bs-toggle="tab">
                                <i class="bi bi-file-text"></i> Даалгаврууд
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#attendance" data-bs-toggle="tab">
                                <i class="bi bi-calendar-check"></i> Ирцийн бүртгэл
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#messages" data-bs-toggle="tab">
                                <i class="bi bi-chat-dots"></i> Мессэж
                                <?php if (count($messages) > 0): ?>
                                    <span class="notification-badge"><?php echo count($messages); ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                    <!-- <li class="nav-item">
                            <a class="nav-link" href="courses.php">Хичээлүүд</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="materials.php">Материал</a>
                        </li> -->
                    <li class="nav-item">
                        <a class="nav-link" href="#resources" data-bs-toggle="tab">
                            <i class="bi bi-folder"></i> Нөөц материал
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#settings" data-bs-toggle="tab">
                            <i class="bi bi-gear"></i> Тохиргоо
                        </a>
                    </li>
                    </ul>
                    <hr class="my-3">
                    <ul class="nav flex-column mb-2">
                        <li class="nav-item">
                            <a class="nav-link" href="../logout.php">
                                <i class="bi bi-box-arrow-right"></i> Гарах
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Main content -->
            <div class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="tab-content">
                    <!-- Overview Tab -->
                    <div class="tab-pane fade show active" id="overview">
                        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                            <h1 class="h2">Хяналтын самбар</h1>
                            <div class="btn-toolbar mb-2 mb-md-0">
                                <div class="btn-group me-2">
                                    <button type="button" class="btn btn-sm btn-outline-secondary">Экспорт</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary">Хэвлэх</button>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Stats -->
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <div class="stat-card bg-primary text-white p-3">
                                    <h6>Нийт хичээл</h6>
                                    <h3><?php echo $total_courses; ?></h3>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stat-card bg-success text-white p-3">
                                    <h6>Нийт сурагч</h6>
                                    <h3><?php echo $total_students; ?></h3>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stat-card bg-warning text-white p-3">
                                    <h6>Хүлээгдэж буй даалгавар</h6>
                                    <h3><?php echo $total_pending; ?></h3>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stat-card bg-info text-white p-3">
                                    <h6>Дундаж дүн</h6>
                                    <h3><?php echo number_format($average_grade, 1); ?></h3>
                                </div>
                            </div>
                        </div>

                        <!-- Upcoming Classes and Calendar -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="dashboard-section">
                                    <h4>Өнөөдрийн хичээлүүд</h4>
                                    <div class="list-group">
                                        <?php foreach ($upcoming_classes as $class): ?>
                                            <a href="#" class="list-group-item list-group-item-action">
                                                <div class="d-flex w-100 justify-content-between">
                                                    <h5 class="mb-1"><?php echo htmlspecialchars($class['course_name']); ?></h5>
                                                    <small><?php echo date('H:i', strtotime($class['start_time'])); ?> - <?php echo date('H:i', strtotime($class['end_time'])); ?></small>
                                                </div>
                                                <p class="mb-1">Өрөө: <?php echo htmlspecialchars($class['room']); ?></p>
                                                <small>Сурагч: <?php echo $class['student_count']; ?></small>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="dashboard-section">
                                    <h4>Хуанли</h4>
                                    <div id="calendar"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Announcements and Messages -->
                        <div class="row mt-4">
                            <div class="col-md-6">
                                <div class="dashboard-section">
                                    <h4>Сүүлийн мэдэгдлүүд</h4>
                                    <?php foreach ($announcements as $announcement): ?>
                                        <div class="card mb-2">
                                            <div class="card-body">
                                                <h5 class="card-title"><?php echo htmlspecialchars($announcement['title']); ?></h5>
                                                <p class="card-text"><?php echo htmlspecialchars($announcement['content']); ?></p>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($announcement['author_name']); ?> - 
                                                    <?php echo date('Y-m-d H:i', strtotime($announcement['created_at'])); ?>
                                                </small>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="dashboard-section">
                                    <h4>Сүүлийн мессэжнүүд</h4>
                                    <?php foreach ($messages as $message): ?>
                                        <div class="card mb-2 <?php echo $message['is_read'] ? '' : 'border-primary'; ?>">
                                            <div class="card-body">
                                                <h5 class="card-title"><?php echo htmlspecialchars($message['sender_name']); ?></h5>
                                                <p class="card-text"><?php echo htmlspecialchars($message['content']); ?></p>
                                                <small class="text-muted">
                                                    <?php echo date('Y-m-d H:i', strtotime($message['created_at'])); ?>
                                                </small>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Other tabs will be loaded dynamically via AJAX -->
                    <div class="tab-pane fade" id="classes"></div>
                    <div class="tab-pane fade" id="students"></div>
                    <div class="tab-pane fade" id="assignments"></div>
                    <div class="tab-pane fade" id="attendance"></div>
                    <div class="tab-pane fade" id="messages"></div>
                    <div class="tab-pane fade" id="resources"></div>
                    <div class="tab-pane fade" id="settings"></div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize FullCalendar
        var calendarEl = document.getElementById('calendar');
        var calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },
            events: [
                <?php foreach ($upcoming_classes as $class): ?>
                {
                    title: '<?php echo addslashes($class['course_name']); ?>',
                    start: '<?php echo date('Y-m-d', strtotime($class['start_time'])); ?>T<?php echo date('H:i:s', strtotime($class['start_time'])); ?>',
                    end: '<?php echo date('Y-m-d', strtotime($class['end_time'])); ?>T<?php echo date('H:i:s', strtotime($class['end_time'])); ?>',
                    url: 'course.php?id=<?php echo $class['course_id']; ?>'
                },
                <?php endforeach; ?>
            ]
        });
        calendar.render();

        // Handle tab changes - Select only nav-links within the first ul
        document.querySelectorAll('.nav.flex-column:first-of-type .nav-link').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const target = this.getAttribute('href').substring(1);

                // Remove active class from all tabs and links
                document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
                document.querySelectorAll('.tab-pane').forEach(t => t.classList.remove('show', 'active'));
                
                // Add active class to clicked link
                this.classList.add('active');
                
                // Show target tab
                const targetTab = document.getElementById(target);
                // Check if the target tab element exists before accessing classList
                if (targetTab) {
                    targetTab.classList.add('show', 'active');
                } else {
                    console.error('Target tab element not found:', target);
                }
                
                // Load content if not overview tab
                if (target !== 'overview') {
                    loadTabContent(target);
                }
            });
        });
    });

    function loadTabContent(tab) {
        const targetTab = document.getElementById(tab);
        
        // Show loading indicator
        if (targetTab) { // Add check here too
            targetTab.innerHTML = `
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Ачааллаж байна...</p>
                </div>
            `;
        }
        
        // Load content
        fetch(`load_tab.php?tab=${tab}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.text();
            })
            .then(html => {
                if (targetTab) { // Add check here too
                    targetTab.innerHTML = html;
                    
                    // Initialize Bootstrap modals within the loaded content
                    $(targetTab).find('.modal').each(function() {
                        new bootstrap.Modal(this);
                    });
                    
                    // Reinitialize daterangepicker if the element exists and it's the attendance tab
                    if (tab === 'attendance') {
                        const reportDateRangeInput = targetTab.querySelector('#report_date_range');
                        if (reportDateRangeInput && typeof $(reportDateRangeInput).daterangepicker !== 'undefined') {
                             $(reportDateRangeInput).daterangepicker({
                                locale: {
                                    format: 'YYYY-MM-DD',
                                    applyLabel: 'Сонгох',
                                    cancelLabel: 'Болих',
                                    fromLabel: 'Эхлэх',
                                    toLabel: 'Дуусах',
                                    customRangeLabel: 'Өөр',
                                    daysOfWeek: ['Ня', 'Да', 'Мя', 'Лх', 'Пү', 'Ба', 'Бя'],
                                    monthNames: ['1-р сар', '2-р сар', '3-р сар', '4-р сар', '5-р сар', '6-р сар', '7-р сар', '8-р сар', '9-р сар', '10-р сар', '11-р сар', '12-р сар'],
                                    firstDay: 1
                                },
                                startDate: moment().subtract(30, 'days'),
                                endDate: moment(),
                                ranges: {
                                    'Энэ 7 хоног': [moment().subtract(6, 'days'), moment()],
                                    'Энэ сар': [moment().startOf('month'), moment().endOf('month')],
                                    'Өнгөрсөн сар': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]}
                            });
                        }
                    }
                    
                    // Reinitialize any JavaScript components (like modals) within the loaded tab
                    const loadedModals = targetTab.querySelectorAll('.modal');
                    loadedModals.forEach(modalEl => {
                        const modal = new bootstrap.Modal(modalEl);
                    });
                 } else {
                    tabContent.innerHTML = '<p>Error loading tab content.</p>';
                }
            })
            .catch(error => {
                console.error('Error loading tab content:', error);
                if (targetTab) { // Add check here too
                    targetTab.innerHTML = `
                        <div class="alert alert-danger m-3">
                            <i class="bi bi-exclamation-triangle"></i>
                            Ачаалахад алдаа гарлаа. Дараа дахин оролдоно уу.
                        </div>
                    `;
                }
            });
    }
    </script>
</body>
</html> 