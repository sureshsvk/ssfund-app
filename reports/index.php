<?php
// reports/index.php - Collection Reports
session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'executive'])) {
    header('Location: ../login.html');
    exit;
}
//require_once '../collections.php'; // Use DB connection

// Database connection
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

// Helper function for date formatting
function formatDate($date) {
    return date('d-m-Y', strtotime($date));
}

// Fetch filter options
$executives = $pdo->query('SELECT id, name FROM executives ORDER BY name')->fetchAll();
// If an executive is logged in, restrict customers and default executive filter
$is_exec = isset($_SESSION['role']) && $_SESSION['role'] === 'executive';
$exec_id = $_SESSION['executive_id'] ?? $_SESSION['user_id'] ?? null;
if ($is_exec) {
    $filter_exec = $exec_id;
    $custStmt = $pdo->prepare('SELECT id, name FROM customers WHERE executive_id = ? ORDER BY name');
    $custStmt->execute([$exec_id]);
    $customers = $custStmt->fetchAll();
} else {
    $customers = $pdo->query('SELECT id, name FROM customers ORDER BY name')->fetchAll();
}

// Handle filters
$filter_from = $_GET['from_date'] ?? '';
$filter_to = $_GET['to_date'] ?? '';
$filter_exec = $_GET['executive_id'] ?? ($is_exec ? $exec_id : '');
$filter_cust = $_GET['customer_id'] ?? '';

$where = [];
$params = [];
if ($filter_from && $filter_to) {
    $where[] = 'DATE(col.created_at) BETWEEN ? AND ?';
    $params[] = $filter_from;
    $params[] = $filter_to;
} elseif ($filter_from) {
    $where[] = 'DATE(col.created_at) >= ?';
    $params[] = $filter_from;
} elseif ($filter_to) {
    $where[] = 'DATE(col.created_at) <= ?';
    $params[] = $filter_to;
}
if ($filter_exec) {
    $where[] = 'col.executive_id = ?';
    $params[] = $filter_exec;
}
if ($filter_cust) {
    $where[] = 'col.customer_id = ?';
    $params[] = $filter_cust;
}
$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// Main query
$sql = "SELECT col.*, e.name AS executive_name, s.name AS scheme_name, c.name AS customer_name FROM collections col LEFT JOIN executives e ON col.executive_id = e.id LEFT JOIN schemes s ON col.scheme_id = s.id LEFT JOIN customers c ON col.customer_id = c.id $where_sql ORDER BY col.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll();

// Totals
$total_collection = 0;
foreach ($records as $rec) {
    $total_collection += floatval($rec['paid_amount']);
}

// Compute total outstanding for the filtered executive/customer (not date-restricted)
$where_out = [];
$params_out = [];
if ($filter_exec) {
    $where_out[] = 'c.executive_id = ?';
    $params_out[] = $filter_exec;
}
if ($filter_cust) {
    $where_out[] = 'c.id = ?';
    $params_out[] = $filter_cust;
}
$where_out_sql = $where_out ? ('WHERE ' . implode(' AND ', $where_out)) : '';
$outSql = "SELECT COALESCE(SUM(GREATEST((s.total_dues - COALESCE(col_counts.cnt,0)),0) * s.due_amount),0) AS total_outstanding
FROM customers c
LEFT JOIN schemes s ON c.scheme_id = s.id
LEFT JOIN (
  SELECT customer_id, scheme_id, COUNT(*) AS cnt FROM collections GROUP BY customer_id, scheme_id
) col_counts ON col_counts.customer_id = c.id AND col_counts.scheme_id = c.scheme_id
" . $where_out_sql;
$outStmt = $pdo->prepare($outSql);
$outStmt->execute($params_out);
$total_outstanding = floatval($outStmt->fetchColumn());
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Collection Reports</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-4">
    <h2>Collection Reports</h2>
    <form method="get" class="row g-3 mb-4">
        <div class="col-md-3">
            <label>From Date</label>
            <input type="date" name="from_date" class="form-control" value="<?= htmlspecialchars($_GET['from_date'] ?? '') ?>">
        </div>
        <div class="col-md-3">
            <label>To Date</label>
            <input type="date" name="to_date" class="form-control" value="<?= htmlspecialchars($_GET['to_date'] ?? '') ?>">
        </div>
        <div class="col-md-2">
            <label>Executive</label>
            <?php if ($is_exec): ?>
                <?php // If executive is logged in, show only their executive and a hidden input so the filter is applied ?>
                <?php foreach ($executives as $exec): ?>
                    <?php if ($exec['id'] == $exec_id): ?>
                        <input type="hidden" name="executive_id" value="<?= $exec['id'] ?>">
                        <input type="text" class="form-control" value="<?= htmlspecialchars($exec['name']) ?>" disabled>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php else: ?>
                <select name="executive_id" class="form-select">
                    <option value="">All</option>
                    <?php foreach ($executives as $exec): ?>
                        <option value="<?= $exec['id'] ?>" <?= (($filter_exec ?? '') == $exec['id']) ? 'selected' : '' ?>><?= htmlspecialchars($exec['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
        </div>
        <div class="col-md-2">
            <label>Customer</label>
            <select name="customer_id" class="form-select">
                <option value="">All</option>
                <?php foreach ($customers as $cust): ?>
                    <option value="<?= $cust['id'] ?>" <?= (($filter_cust ?? '') == $cust['id']) ? 'selected' : '' ?>><?= htmlspecialchars($cust['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2 align-self-end d-flex gap-2">
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="index.php" class="btn btn-secondary">Reset</a>
            <?php $dash = (isset($_SESSION['role']) && $_SESSION['role'] === 'executive') ? '../executive_dashboard.php' : '../admin_dashboard.php'; ?>
            <a href="<?= $dash ?>" class="btn btn-outline-dark">Back to Dashboard</a>
        </div>
    </form>
    <table class="table table-bordered table-hover">
        <thead>
            <tr>
                <th>Date</th>
                <th>Executive</th>
                <th>Scheme</th>
                <th>Customer</th>
                <th>Due No</th>
                <th>Due Amount</th>
                <th>Paid Amount</th>
                <th>Balance Amount</th>
                <th>Paid Type</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($records as $rec): ?>
            <tr>
                <td><?= isset($rec['created_at']) ? formatDate($rec['created_at']) : '' ?></td>
                <td><?= htmlspecialchars($rec['executive_name']) ?></td>
                <td><?= htmlspecialchars($rec['scheme_name']) ?></td>
                <td><?= htmlspecialchars($rec['customer_name']) ?></td>
                <td><?= htmlspecialchars($rec['due_no']) ?></td>
                <td><?= htmlspecialchars($rec['due_amount']) ?></td>
                <td><?= htmlspecialchars($rec['paid_amount']) ?></td>
                <td><?= htmlspecialchars($rec['balance_amount']) ?></td>
                <td><?= htmlspecialchars($rec['paid_type']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr style="font-weight:bold;background:#f3f4f6;">
                <td colspan="6" class="text-end">Total Collection:</td>
                <td><?= number_format($total_collection, 2) ?></td>
                <td colspan="2">Outstanding: <?= number_format($total_outstanding, 2) ?></td>
            </tr>
        </tfoot>
    </table>
</div>
</body>
</html>
