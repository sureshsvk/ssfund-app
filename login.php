<?php
// login.php
session_start();

// Database connection settings
$host = 'localhost';
$db   = 'chit_collection';
$user = 'root'; // Change if your MySQL user is different
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = $_POST['role'] ?? '';

    if ($username && $password && $role) {
        try {
            $pdo = new PDO($dsn, $user, $pass, $options);
            $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? AND role = ? LIMIT 1');
            $stmt->execute([$username, $role]);
            $user = $stmt->fetch();
            if ($user && password_verify($password, $user['password'])) {
                // Login successful
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                // If executive, find matching executive record and set executive_id in session
                if ($user['role'] === 'executive') {
                    $stmt2 = $pdo->prepare('SELECT id FROM executives WHERE login_id = ? LIMIT 1');
                    $stmt2->execute([$user['id']]);
                    $execRow = $stmt2->fetch();
                    $_SESSION['executive_id'] = $execRow ? intval($execRow['id']) : null;
                    header('Location: executive_dashboard.php');
                } else {
                    header('Location: admin_dashboard.php');
                }
                exit;
            } else {
                $error = 'Invalid username, password, or role.';
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    } else {
        $error = 'Please fill in all fields.';
    }
}

if ($error) {
    // Redirect back to login page with error message in URL
    header('Location: login.html?error=' . urlencode($error));
    exit;
} else {
    // If accessed directly, redirect to login page
    header('Location: login.html');
    exit;
}
?>
