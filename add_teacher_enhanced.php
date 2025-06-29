<?php
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['username']) || $_SESSION['usertype'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$host = "localhost";
$user = "root";
$password = "";
$db = "schoolproject";

$data = mysqli_connect($host, $user, $password, $db);

if (!$data) {
    die("Database connection failed: " . mysqli_connect_error());
}

$message = '';

// Get available courses for selection
$courses_sql = "SELECT * FROM courses ORDER BY course_name";
$courses_result = mysqli_query($data, $courses_sql);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = mysqli_real_escape_string($data, $_POST['username']);
    $email = mysqli_real_escape_string($data, $_POST['email']);
    $name = mysqli_real_escape_string($data, $_POST['name']);
    $phone = mysqli_real_escape_string($data, $_POST['phone']);
    $specialization = mysqli_real_escape_string($data, $_POST['specialization']);
    $qualification = mysqli_real_escape_string($data, $_POST['qualification']);
    $experience_years = (int)$_POST['experience_years'];
    $bio = mysqli_real_escape_string($data, $_POST['bio']);
    $department = mysqli_real_escape_string($data, $_POST['department']);
    $office_location = mysqli_real_escape_string($data, $_POST['office_location']);
    $office_hours = mysqli_real_escape_string($data, $_POST['office_hours']);
    $linkedin_url = mysqli_real_escape_string($data, $_POST['linkedin_url']);
    $password = $_POST['password'];
    $status = $_POST['status'];
    $selected_courses = isset($_POST['courses']) ? $_POST['courses'] : [];

    if (empty($username) || empty($email) || empty($name) || empty($password) || empty($specialization) || empty($qualification)) {
        $message = "Please fill in all required fields!";
    } elseif (empty($selected_courses)) {
        $message = "Please select at least one course for the teacher!";
    } else {
        // Check if username already exists
        $check_username = mysqli_query($data, "SELECT id FROM teacher WHERE username = '$username'");
        if (mysqli_num_rows($check_username) > 0) {
            $message = "Username already exists!";
        } else {
            // Check if email already exists
            $check_email = mysqli_query($data, "SELECT id FROM teacher WHERE email = '$email'");
            if (mysqli_num_rows($check_email) > 0) {
                $message = "Email already exists!";
            } else {
                // Start transaction
                mysqli_begin_transaction($data);
                
                try {
                    // Hash password
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Insert teacher
                    $insert_sql = "INSERT INTO teacher (
                        username, password, name, email, phone, specialization, qualification, 
                        experience_years, bio, department, office_location, office_hours, 
                        linkedin_url, status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    
                    $stmt = mysqli_prepare($data, $insert_sql);
                    mysqli_stmt_bind_param($stmt, "sssssssissssss", 
                        $username, $hashed_password, $name, $email, $phone, $specialization, 
                        $qualification, $experience_years, $bio, $department, $office_location, 
                        $office_hours, $linkedin_url, $status
                    );
                    
                    if (mysqli_stmt_execute($stmt)) {
                        $teacher_id = mysqli_insert_id($data);
                        
                        // Assign courses to teacher
                        foreach ($selected_courses as $course_id) {
                            $assign_course_sql = "UPDATE courses SET teacher_id = ? WHERE id = ?";
                            $stmt2 = mysqli_prepare($data, $assign_course_sql);
                            mysqli_stmt_bind_param($stmt2, "ii", $teacher_id, $course_id);
                            mysqli_stmt_execute($stmt2);
                        }
                        
                        mysqli_commit($data);
                        $message = "Teacher added successfully with " . count($selected_courses) . " course(s) assigned!";
                        $_POST = array();
                    } else {
                        throw new Exception("Error adding teacher: " . mysqli_error($data));
                    }
                } catch (Exception $e) {
                    mysqli_rollback($data);
                    $message = $e->getMessage();
                }
            }
        }
    }
}

$teachers_sql = "SELECT t.username, t.email, t.name, t.specialization, t.department, t.status, 
                        COUNT(c.id) as course_count 
                 FROM teacher t 
                 LEFT JOIN courses c ON t.id = c.teacher_id 
                 GROUP BY t.id 
                 ORDER BY t.name";
$teachers_result = mysqli_query($data, $teachers_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Add Teacher - Admin Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin.css">
    
    <style>
        .course-selection {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .course-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .course-item {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 1rem;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .course-item:hover {
            border-color: #007bff;
            transform: translateY(-2px);
        }
        
        .course-item.selected {
            border-color: #007bff;
            background: #f0f8ff;
        }
        
        .course-checkbox {
            display: none;
        }
        
        .course-title {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #333;
        }
        
        .course-code {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 0.5rem;
        }
        
        .course-description {
            font-size: 0.85rem;
            color: #777;
            line-height: 1.4;
        }
        
        .course-program {
            display: inline-block;
            background: #007bff;
            color: white;
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            margin-top: 0.5rem;
        }
        
        .selected-count {
            background: #007bff;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <a href="adminhome.php">
            <i class="fas fa-graduation-cap me-2"></i>
            Admin Dashboard
        </a>
        <div class="logout">
            <a href="logout.php">
                <i class="fas fa-sign-out-alt me-2"></i>
                Logout
            </a>
        </div>
    </header>

    <!-- Sidebar -->
    <aside>
        <ul>
            <li>
                <a href="admission.php">
                    <i class="fas fa-user-plus me-2"></i>
                    Admission
                </a>
            </li>
            <li>
                <a href="add_student.php">
                    <i class="fas fa-user-graduate me-2"></i>
                    Add Student
                </a>
            </li>
            <li>
                <a href="view_student.php">
                    <i class="fas fa-users me-2"></i>
                    View Students
                </a>
            </li>
            <li>
                <a href="add_courses.php">
                    <i class="fas fa-book me-2"></i>
                    Add Course
                </a>
            </li>
            <li>
                <a href="content_management.php">
                    <i class="fas fa-file-alt me-2"></i>
                    Content Management
                </a>
            </li>
            <li>
                <a href="add_teacher_enhanced.php" class="active">
                    <i class="fas fa-chalkboard-teacher me-2"></i>
                    Add Teacher
                </a>
            </li>
        </ul>
    </aside>

    <!-- Main Content -->
    <div class="content fade-in">
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-chalkboard-teacher me-2"></i>Add New Teacher</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($message)): ?>
                            <div class="alert alert-<?php echo strpos($message, 'successfully') !== false ? 'success' : 'danger'; ?>">
                                <i class="fas fa-<?php echo strpos($message, 'successfully') !== false ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                                <?php echo htmlspecialchars($message); ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="username" class="form-label">
                                            <i class="fas fa-user me-2"></i>Username *
                                        </label>
                                        <input type="text" class="form-control" id="username" name="username" 
                                               value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="email" class="form-label">
                                            <i class="fas fa-envelope me-2"></i>Email *
                                        </label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="name" class="form-label">
                                            <i class="fas fa-id-card me-2"></i>Full Name *
                                        </label>
                                        <input type="text" class="form-control" id="name" name="name" 
                                               value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" required>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="phone" class="form-label">
                                            <i class="fas fa-phone me-2"></i>Phone Number
                                        </label>
                                        <input type="tel" class="form-control" id="phone" name="phone" 
                                               value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="specialization" class="form-label">
                                            <i class="fas fa-graduation-cap me-2"></i>Specialization *
                                        </label>
                                        <select class="form-control" id="specialization" name="specialization" required>
                                            <option value="">Select Specialization</option>
                                            <option value="Mathematics" <?php echo (isset($_POST['specialization']) && $_POST['specialization'] === 'Mathematics') ? 'selected' : ''; ?>>Mathematics</option>
                                            <option value="Computer Science" <?php echo (isset($_POST['specialization']) && $_POST['specialization'] === 'Computer Science') ? 'selected' : ''; ?>>Computer Science</option>
                                            <option value="Physics" <?php echo (isset($_POST['specialization']) && $_POST['specialization'] === 'Physics') ? 'selected' : ''; ?>>Physics</option>
                                            <option value="Chemistry" <?php echo (isset($_POST['specialization']) && $_POST['specialization'] === 'Chemistry') ? 'selected' : ''; ?>>Chemistry</option>
                                            <option value="Biology" <?php echo (isset($_POST['specialization']) && $_POST['specialization'] === 'Biology') ? 'selected' : ''; ?>>Biology</option>
                                            <option value="English" <?php echo (isset($_POST['specialization']) && $_POST['specialization'] === 'English') ? 'selected' : ''; ?>>English</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="qualification" class="form-label">
                                            <i class="fas fa-certificate me-2"></i>Qualification *
                                        </label>
                                        <input type="text" class="form-control" id="qualification" name="qualification" 
                                               value="<?php echo isset($_POST['qualification']) ? htmlspecialchars($_POST['qualification']) : ''; ?>" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="experience_years" class="form-label">
                                            <i class="fas fa-clock me-2"></i>Years of Experience
                                        </label>
                                        <input type="number" class="form-control" id="experience_years" name="experience_years" 
                                               value="<?php echo isset($_POST['experience_years']) ? htmlspecialchars($_POST['experience_years']) : ''; ?>" min="0" max="50">
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="department" class="form-label">
                                            <i class="fas fa-building me-2"></i>Department
                                        </label>
                                        <input type="text" class="form-control" id="department" name="department" 
                                               value="<?php echo isset($_POST['department']) ? htmlspecialchars($_POST['department']) : ''; ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="bio" class="form-label">
                                    <i class="fas fa-user-edit me-2"></i>Bio
                                </label>
                                <textarea class="form-control" id="bio" name="bio" rows="3" 
                                          placeholder="Brief description about the teacher..."><?php echo isset($_POST['bio']) ? htmlspecialchars($_POST['bio']) : ''; ?></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="office_location" class="form-label">
                                            <i class="fas fa-map-marker-alt me-2"></i>Office Location
                                        </label>
                                        <input type="text" class="form-control" id="office_location" name="office_location" 
                                               value="<?php echo isset($_POST['office_location']) ? htmlspecialchars($_POST['office_location']) : ''; ?>">
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="office_hours" class="form-label">
                                            <i class="fas fa-calendar-alt me-2"></i>Office Hours
                                        </label>
                                        <input type="text" class="form-control" id="office_hours" name="office_hours" 
                                               value="<?php echo isset($_POST['office_hours']) ? htmlspecialchars($_POST['office_hours']) : ''; ?>" 
                                               placeholder="e.g., Mon-Fri 9:00 AM - 5:00 PM">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="linkedin_url" class="form-label">
                                    <i class="fab fa-linkedin me-2"></i>LinkedIn URL
                                </label>
                                <input type="url" class="form-control" id="linkedin_url" name="linkedin_url" 
                                       value="<?php echo isset($_POST['linkedin_url']) ? htmlspecialchars($_POST['linkedin_url']) : ''; ?>">
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="password" class="form-label">
                                            <i class="fas fa-lock me-2"></i>Password *
                                        </label>
                                        <input type="password" class="form-control" id="password" name="password" required>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="status" class="form-label">
                                            <i class="fas fa-toggle-on me-2"></i>Status
                                        </label>
                                        <select class="form-control" id="status" name="status">
                                            <option value="active" <?php echo (isset($_POST['status']) && $_POST['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                                            <option value="inactive" <?php echo (isset($_POST['status']) && $_POST['status'] === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Course Selection Section -->
                            <div class="course-selection">
                                <h6><i class="fas fa-book me-2"></i>Assign Courses to Teacher *</h6>
                                <p class="text-muted">Select the courses this teacher will be responsible for:</p>
                                
                                <div id="selectedCount" class="selected-count" style="display: none;">
                                    <i class="fas fa-check-circle me-2"></i>
                                    <span id="countText">0 courses selected</span>
                                </div>
                                
                                <div class="course-grid">
                                    <?php if (mysqli_num_rows($courses_result) > 0): ?>
                                        <?php while ($course = mysqli_fetch_assoc($courses_result)): ?>
                                            <div class="course-item" onclick="toggleCourse(<?php echo $course['id']; ?>)">
                                                <input type="checkbox" class="course-checkbox" name="courses[]" 
                                                       value="<?php echo $course['id']; ?>" id="course_<?php echo $course['id']; ?>">
                                                <div class="course-title"><?php echo htmlspecialchars($course['course_name']); ?></div>
                                                <div class="course-code"><?php echo htmlspecialchars($course['course_code']); ?></div>
                                                <div class="course-description"><?php echo htmlspecialchars($course['course_description']); ?></div>
                                                <div class="course-program"><?php echo htmlspecialchars($course['program']); ?></div>
                                            </div>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <div class="col-12">
                                            <p class="text-muted">No courses available. Please add courses first.</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-plus me-2"></i>Add Teacher
                                </button>
                                <a href="adminhome.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-list me-2"></i>Current Teachers</h5>
                    </div>
                    <div class="card-body">
                        <?php if (mysqli_num_rows($teachers_result) > 0): ?>
                            <?php while ($teacher = mysqli_fetch_assoc($teachers_result)): ?>
                                <div class="teacher-item">
                                    <div class="teacher-info">
                                        <h6><?php echo htmlspecialchars($teacher['name']); ?></h6>
                                        <p class="text-muted mb-1"><?php echo htmlspecialchars($teacher['specialization']); ?></p>
                                        <p class="text-muted mb-1"><?php echo htmlspecialchars($teacher['email']); ?></p>
                                        <small class="text-info">
                                            <i class="fas fa-book me-1"></i>
                                            <?php echo $teacher['course_count']; ?> course(s) assigned
                                        </small>
                                    </div>
                                    <div class="teacher-status">
                                        <span class="badge badge-<?php echo $teacher['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                            <?php echo ucfirst($teacher['status']); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p class="text-muted">No teachers found.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleCourse(courseId) {
            const checkbox = document.getElementById('course_' + courseId);
            const courseItem = checkbox.parentElement;
            
            checkbox.checked = !checkbox.checked;
            
            if (checkbox.checked) {
                courseItem.classList.add('selected');
            } else {
                courseItem.classList.remove('selected');
            }
            
            updateSelectedCount();
        }
        
        function updateSelectedCount() {
            const checkboxes = document.querySelectorAll('.course-checkbox:checked');
            const countElement = document.getElementById('selectedCount');
            const countText = document.getElementById('countText');
            
            if (checkboxes.length > 0) {
                countElement.style.display = 'block';
                countText.textContent = checkboxes.length + ' course(s) selected';
            } else {
                countElement.style.display = 'none';
            }
        }
        
        // Initialize count on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateSelectedCount();
        });
    </script>
</body>
</html> 