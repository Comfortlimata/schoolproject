<?php
session_start();

// Check if teacher is logged in
if (!isset($_SESSION['username']) || $_SESSION['usertype'] !== 'teacher') {
    header("Location: login.php");
    exit();
}

// Database connection
$host = "localhost";
$user = "root";
$password = "";
$db = "schoolproject";

$data = mysqli_connect($host, $user, $password, $db);

if (!$data) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Get teacher information
$teacher_id = $_SESSION['teacher_id'];
$teacher_sql = "SELECT * FROM teacher WHERE id = ?";
$stmt = mysqli_prepare($data, $teacher_sql);
mysqli_stmt_bind_param($stmt, "i", $teacher_id);
mysqli_stmt_execute($stmt);
$teacher_result = mysqli_stmt_get_result($stmt);
$teacher = mysqli_fetch_assoc($teacher_result);

// Get all grades and subjects assigned to this teacher
$teacher_grades = mysqli_query($data, "SELECT DISTINCT g.id, g.name FROM teacher_grade_subjects tgs JOIN grades g ON tgs.grade_id = g.id WHERE tgs.teacher_id = $teacher_id ORDER BY g.name");
$teacher_subjects = mysqli_query($data, "SELECT DISTINCT s.id, s.name FROM teacher_grade_subjects tgs JOIN subjects s ON tgs.subject_id = s.id WHERE tgs.teacher_id = $teacher_id ORDER BY s.name");

// Get selected grade/subject from GET
$selected_grade = isset($_GET['grade']) ? (int)$_GET['grade'] : 0;
$selected_subject = isset($_GET['subject']) ? (int)$_GET['subject'] : 0;

// Build filter for queries
$filter = "WHERE tgs.teacher_id = $teacher_id";
if ($selected_grade) $filter .= " AND tgs.grade_id = $selected_grade";
if ($selected_subject) $filter .= " AND tgs.subject_id = $selected_subject";

// Filtered assignment count
$assignment_count = mysqli_fetch_array(mysqli_query($data, "SELECT COUNT(*) FROM teacher_grade_subjects tgs $filter"))[0];

// Filtered student count
$student_count = mysqli_fetch_array(mysqli_query($data, "SELECT COUNT(DISTINCT s.id) FROM students s JOIN teacher_grade_subjects tgs ON s.grade_id = tgs.grade_id $filter"))[0];

// New: Use teacher_grade_subjects mapping
$total_students = mysqli_fetch_array(mysqli_query($data, "SELECT COUNT(*) FROM students"))[0];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Teacher Dashboard - Miles e-School Academy</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #1e40af;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --info-color: #3b82f6;
            --dark-color: #1f2937;
            --light-color: #f8fafc;
            --border-color: #e5e7eb;
        }
        
        * {
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            background: var(--light-color);
            overflow-x: hidden;
        }
        
        /* Sidebar Styles */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            width: 280px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            z-index: 1000;
            transition: all 0.3s ease;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        
        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            text-align: center;
        }
        
        .sidebar-brand {
            font-size: 1.5rem;
            font-weight: 700;
            color: white;
            text-decoration: none;
        }
        
        .sidebar-brand:hover {
            color: white;
        }
        
        .sidebar-menu {
            padding: 1rem 0;
        }
        
        .sidebar-item {
            margin: 0.5rem 1rem;
        }
        
        .sidebar-link {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        
        .sidebar-link:hover {
            background: rgba(255,255,255,0.1);
            color: white;
            transform: translateX(5px);
        }
        
        .sidebar-link.active {
            background: rgba(255,255,255,0.2);
            color: white;
        }
        
        .sidebar-icon {
            width: 20px;
            margin-right: 0.75rem;
        }
        
        /* Main Content */
        .main-content {
            margin-left: 280px;
            min-height: 100vh;
            transition: all 0.3s ease;
        }
        
        /* Header */
        .top-header {
            background: white;
            height: 70px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 999;
        }
        
        .header-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark-color);
        }
        
        .header-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .teacher-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .teacher-avatar {
            width: 40px;
            height: 40px;
            background: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }
        
        .logout-btn {
            background: var(--danger-color);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .logout-btn:hover {
            background: #dc2626;
            color: white;
            transform: translateY(-2px);
        }
        
        /* Dashboard Content */
        .dashboard-content {
            padding: 2rem;
        }
        
        .welcome-section {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
        }
        
        .welcome-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .welcome-subtitle {
            opacity: 0.9;
            font-size: 1.1rem;
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .stat-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }
        
        .stat-icon.courses { background: var(--success-color); }
        .stat-icon.students { background: var(--info-color); }
        .stat-icon.total-students { background: var(--warning-color); }
        .stat-icon.specialization { background: var(--danger-color); }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 0.25rem;
        }
        
        .stat-label {
            color: #6b7280;
            font-weight: 500;
        }
        
        /* Quick Actions */
        .quick-actions {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border: 1px solid var(--border-color);
        }
        
        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 1.5rem;
        }
        
        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .action-card {
            background: var(--light-color);
            padding: 1.5rem;
            border-radius: 12px;
            text-align: center;
            text-decoration: none;
            color: var(--dark-color);
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        
        .action-card:hover {
            background: var(--primary-color);
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(37, 99, 235, 0.3);
        }
        
        .action-icon {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: var(--primary-color);
        }
        
        .action-card:hover .action-icon {
            color: white;
        }
        
        .action-title {
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .action-desc {
            font-size: 0.9rem;
            opacity: 0.8;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.open {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }
            
            .actions-grid {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }
        }
        
        /* Toggle Button */
        .sidebar-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--dark-color);
        }
        
        @media (max-width: 768px) {
            .sidebar-toggle {
                display: block;
            }
        }
    </style>
</head>

<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="#" class="sidebar-brand">
                <i class="fas fa-chalkboard-teacher me-2"></i>
                Teacher Portal
            </a>
        </div>
        
        <div class="sidebar-menu">
            <div class="sidebar-item">
                <a href="teacherhome.php" class="sidebar-link active">
                    <i class="fas fa-tachometer-alt sidebar-icon"></i>
                    Dashboard
                </a>
            </div>
            
            <div class="sidebar-item">
                <a href="teacher_courses.php" class="sidebar-link">
                    <i class="fas fa-book sidebar-icon"></i>
                    My Courses
                </a>
            </div>
            
            <div class="sidebar-item">
                <a href="teacher_student_management.php" class="sidebar-link">
                    <i class="fas fa-users sidebar-icon"></i>
                    My Students
                </a>
            </div>
            
            <div class="sidebar-item">
                <a href="teacher_content_management.php" class="sidebar-link">
                    <i class="fas fa-tasks sidebar-icon"></i>
                    Assignments & Content
                </a>
            </div>
            
            <div class="sidebar-item">
                <a href="teacher_grade_management.php" class="sidebar-link">
                    <i class="fas fa-chart-bar sidebar-icon"></i>
                    Grades
                </a>
            </div>
            
            <div class="sidebar-item">
                <a href="teacher_schedule_management.php" class="sidebar-link">
                    <i class="fas fa-calendar sidebar-icon"></i>
                    Schedule
                </a>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="top-header">
            <div class="d-flex align-items-center">
                <button class="sidebar-toggle me-3" id="sidebarToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <h1 class="header-title mb-0">Dashboard</h1>
            </div>
            
            <div class="header-actions">
                <div class="teacher-info">
                    <div class="teacher-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <div>
                        <div class="fw-bold"><?php echo htmlspecialchars($_SESSION['username']); ?></div>
                        <small class="text-muted"><?php echo htmlspecialchars($teacher['specialization'] ?? ''); ?> Teacher</small>
                    </div>
                </div>
                
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt me-2"></i>Logout
                </a>
            </div>
        </div>

        <!-- Dashboard Content -->
        <div class="dashboard-content">
            <!-- Welcome Section -->
            <div class="welcome-section">
                <h1 class="welcome-title">Welcome back, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>
                <p class="welcome-subtitle">Here's your teaching dashboard overview for today.</p>
            </div>

            <!-- Add filter dropdowns above the stats grid -->
            <form method="get" class="row g-2 mb-3">
                <div class="col-auto">
                    <select name="grade" class="form-select" onchange="this.form.submit()">
                        <option value="">All Grades</option>
                        <?php mysqli_data_seek($teacher_grades, 0); while ($g = mysqli_fetch_assoc($teacher_grades)): ?>
                            <option value="<?= $g['id'] ?>" <?= $selected_grade == $g['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($g['name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <select name="subject" class="form-select" onchange="this.form.submit()">
                        <option value="">All Subjects</option>
                        <?php mysqli_data_seek($teacher_subjects, 0); while ($s = mysqli_fetch_assoc($teacher_subjects)): ?>
                            <option value="<?= $s['id'] ?>" <?= $selected_subject == $s['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s['name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </form>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon courses">
                            <i class="fas fa-book"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?php echo $assignment_count; ?></div>
                    <div class="stat-label">My Courses</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon students">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?php echo $student_count; ?></div>
                    <div class="stat-label">My Students</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon total-students">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?php echo $total_students; ?></div>
                    <div class="stat-label">Total Students</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon specialization">
                            <i class="fas fa-star"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?php echo htmlspecialchars($teacher['experience_years']); ?>+</div>
                    <div class="stat-label">Years Experience</div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <h2 class="section-title">Quick Actions</h2>
                <div class="actions-grid">
                    <a href="teacher_courses.php" class="action-card">
                        <div class="action-icon">
                            <i class="fas fa-book"></i>
                        </div>
                        <div class="action-title">View Courses</div>
                        <div class="action-desc">Manage your course materials</div>
                    </a>
                    
                    <a href="teacher_student_management.php" class="action-card">
                        <div class="action-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="action-title">Student List</div>
                        <div class="action-desc">View your students</div>
                    </a>
                    
                    <a href="teacher_content_management.php" class="action-card">
                        <div class="action-icon">
                            <i class="fas fa-tasks"></i>
                        </div>
                        <div class="action-title">Assignments</div>
                        <div class="action-desc">Create and grade assignments</div>
                    </a>
                    
                    <a href="teacher_grade_management.php" class="action-card">
                        <div class="action-icon">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                        <div class="action-title">Grades</div>
                        <div class="action-desc">Manage student grades</div>
                    </a>
                    
                    <a href="teacher_schedule_management.php" class="action-card">
                        <div class="action-icon">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <div class="action-title">Schedule</div>
                        <div class="action-desc">View your teaching schedule</div>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Sidebar toggle for mobile
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebar = document.getElementById('sidebar');
        
        sidebarToggle.addEventListener('click', () => {
            sidebar.classList.toggle('open');
        });
        
        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', (e) => {
            if (window.innerWidth <= 768) {
                if (!sidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
                    sidebar.classList.remove('open');
                }
            }
        });
        
        // Active link highlighting
        const currentPage = window.location.pathname.split('/').pop();
        const sidebarLinks = document.querySelectorAll('.sidebar-link');
        
        sidebarLinks.forEach(link => {
            if (link.getAttribute('href') === currentPage) {
                link.classList.add('active');
            }
        });
    </script>
</body>
</html> 