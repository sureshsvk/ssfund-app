<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'executive') {
    header('Location: login.html');
    exit;
}

// DB connection and metrics for this executive
$host = 'localhost';
$db   = 'chit_collection';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
$pdo = new PDO($dsn, $user, $pass, $options);
$exec_id = $_SESSION['executive_id'] ?? $_SESSION['user_id'];
// Today's collection for executive
$stmt = $pdo->prepare("SELECT COALESCE(SUM(paid_amount),0) AS today_paid FROM collections WHERE executive_id = ? AND DATE(created_at)=CURDATE()");
$stmt->execute([$exec_id]);
$today_paid = number_format(floatval($stmt->fetchColumn()), 2);

// Total collection for executive
$stmt = $pdo->prepare("SELECT COALESCE(SUM(paid_amount),0) AS total_paid FROM collections WHERE executive_id = ?");
$stmt->execute([$exec_id]);
$total_paid = number_format(floatval($stmt->fetchColumn()), 2);

// Total outstanding for executive: per-customer using scheme & collected counts
$outStmt = $pdo->prepare("SELECT COALESCE(SUM(GREATEST((s.total_dues - COALESCE(col_counts.cnt,0)),0) * s.due_amount),0) AS total_outstanding
FROM customers c
LEFT JOIN schemes s ON c.scheme_id = s.id
LEFT JOIN (
  SELECT customer_id, scheme_id, COUNT(*) AS cnt FROM collections GROUP BY customer_id, scheme_id
) col_counts ON col_counts.customer_id = c.id AND col_counts.scheme_id = c.scheme_id
WHERE c.executive_id = ?");
$outStmt->execute([$exec_id]);
$total_balance = number_format(floatval($outStmt->fetchColumn()), 2);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Executive Dashboard - Chit Collection</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Roboto', sans-serif; background: #f8fafc; }
        .dashboard-container { max-width: 800px; margin: 40px auto; background: #fff; border-radius: 16px; box-shadow: 0 4px 24px rgba(0,0,0,0.08); padding: 2.5rem 2rem; }
        .dashboard-title { font-weight: 700; font-size: 2rem; margin-bottom: 2rem; color: #3b3b3b; }
        .dashboard-menu .card { border-radius: 12px; transition: box-shadow 0.2s; }
        .dashboard-menu .card:hover { box-shadow: 0 6px 24px rgba(99,102,241,0.12); }
        .dashboard-menu .card-body { text-align: center; }
        .dashboard-menu .bi { font-size: 2.5rem; color: #6366f1; margin-bottom: 0.5rem; }
        .dashboard-menu .card-title { font-size: 1.2rem; font-weight: 600; }
        @media (max-width: 768px) { .dashboard-container { padding: 1.5rem 0.5rem; } }
    </style>
</head>
<body>
    <div class="container dashboard-container">
        <div class="dashboard-title text-center">Executive Dashboard</div>
        <div class="row mb-4 g-3">
            <div class="col-12 col-md-4">
                <div class="card text-white bg-primary bg-gradient h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-calendar-event fs-1 me-3"></i>
                            <div>
                                <div class="small">Today's Collection</div>
                                <div class="h5">₹ <?= $today_paid ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="card text-white bg-success bg-gradient h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-cash-stack fs-1 me-3"></i>
                            <div>
                                <div class="small">Total Collection</div>
                                <div class="h5">₹ <?= $total_paid ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="card text-white bg-danger bg-gradient h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-exclamation-triangle fs-1 me-3"></i>
                            <div>
                                <div class="small">Total Outstanding</div>
                                <div class="h5">₹ <?= $total_balance ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row dashboard-menu g-4">
            <div class="col-6 col-md-4">
                <a href="customers.php" class="text-decoration-none">
                    <div class="card h-100">
                        <div class="card-body">
                            <i class="bi bi-people"></i>
                            <div class="card-title">Customers</div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-6 col-md-4">
                <a href="collections.php" class="text-decoration-none">
                    <div class="card h-100">
                        <div class="card-body">
                            <i class="bi bi-cash-coin"></i>
                            <div class="card-title">Collections</div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-6 col-md-4">
                <a href="reports/index.php" class="text-decoration-none">
                    <div class="card h-100">
                        <div class="card-body">
                            <i class="bi bi-bar-chart"></i>
                            <div class="card-title">Reports</div>
                        </div>
                    </div>
                </a>
            </div>
        </div>
        <div class="text-end mt-4">
            <a href="logout.php" class="btn btn-outline-danger"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
