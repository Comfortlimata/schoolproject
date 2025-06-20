<?php
session_start();

// Redirect if already logged in
if (isset($_SESSION['username']) && $_SESSION['usertype'] == 'teacher') {
    header("Location: teacherhome.php");
    exit();
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $host = "localhost";
    $user = "root";
    $password = "";
    $db = "schoolproject";

    $data = mysqli_connect($host, $user, $password, $db);

    if (!$data) {
        die("Database connection failed: " . mysqli_connect_error());
    }

    $email = mysqli_real_escape_string($data, $_POST['email']);
    $password = $_POST['password'];

    // Check if teacher exists
    $sql = "SELECT * FROM teachers WHERE email = ? AND status = 'active'";
    $stmt = mysqli_prepare($data, $sql);
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) == 1) {
        $teacher = mysqli_fetch_assoc($result);
        
        // Check if password is hashed or plain text (for backward compatibility)
        if (!empty($teacher['password'])) {
            // Check hashed password
            if (password_verify($password, $teacher['password'])) {
                $_SESSION['username'] = $teacher['name'];
                $_SESSION['usertype'] = 'teacher';
                $_SESSION['teacher_id'] = $teacher['id'];
                $_SESSION['teacher_email'] = $teacher['email'];
                $_SESSION['specialization'] = $teacher['specialization'];
                
                header("Location: teacherhome.php");
                exit();
            } else {
                $message = "Invalid password!";
            }
        } else {
            // Fallback for old plain text passwords (for backward compatibility)
            if ($password === 'teacher123') {
                $_SESSION['username'] = $teacher['name'];
                $_SESSION['usertype'] = 'teacher';
                $_SESSION['teacher_id'] = $teacher['id'];
                $_SESSION['teacher_email'] = $teacher['email'];
                $_SESSION['specialization'] = $teacher['specialization'];
                
                header("Location: teacherhome.php");
                exit();
            } else {
                $message = "Invalid password!";
            }
        }
    } else {
        $message = "Teacher not found or account inactive!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Teacher Login - Miles e-School Academy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Poppins', sans-serif;
        }
        .login-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 2rem;
            width: 100%;
            max-width: 400px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #2563eb, #1e40af);
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
        }
        .form-control {
            border-radius: 10px;
            border: 2px solid #e5e7eb;
            padding: 12px 15px;
        }
        .form-control:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 0.2rem rgba(37, 99, 235, 0.25);
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="text-center mb-4">
            <i class="fas fa-chalkboard-teacher fa-3x text-primary mb-3"></i>
            <h2>Teacher Login</h2>
            <p class="text-muted">Sign in to your teacher account</p>
        </div>
        
        <?php if (!empty($message)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label for="email" class="form-label">Email Address</label>
                <input type="email" class="form-control" id="email" name="email" required>
            </div>
            
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            
            <button type="submit" class="btn btn-primary w-100">
                <i class="fas fa-sign-in-alt me-2"></i>Sign In
            </button>
        </form>
        
        <div class="text-center mt-3">
            <a href="index.php" class="text-decoration-none">Back to Home</a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
