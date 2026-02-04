<?php
// schemes.php - Scheme CRUD page
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin')) {
    header('Location: login.html');
    exit;
}

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

// Handle CRUD actions
$action = $_GET['action'] ?? '';

// Add Scheme
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $collection_type = $_POST['collection_type'] ?? '';
    $total_dues = floatval($_POST['total_dues'] ?? 0);
    $due_amount = floatval($_POST['due_amount'] ?? 0);
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    if ($name && $collection_type && $total_dues && $due_amount && $start_date && $end_date) {
        $stmt = $pdo->prepare('INSERT INTO schemes (name, collection_type, total_dues, due_amount, start_date, end_date) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$name, $collection_type, $total_dues, $due_amount, $start_date, $end_date]);
        header('Location: schemes.php?msg=Scheme+added+successfully');
        exit;
    }
}

// Edit Scheme
if ($action === 'edit' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $name = trim($_POST['name'] ?? '');
    $collection_type = $_POST['collection_type'] ?? '';
    $total_dues = floatval($_POST['total_dues'] ?? 0);
    $due_amount = floatval($_POST['due_amount'] ?? 0);
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    if ($name && $collection_type && $total_dues && $due_amount && $start_date && $end_date) {
        $stmt = $pdo->prepare('UPDATE schemes SET name=?, collection_type=?, total_dues=?, due_amount=?, start_date=?, end_date=? WHERE id=?');
        $stmt->execute([$name, $collection_type, $total_dues, $due_amount, $start_date, $end_date, $id]);
        header('Location: schemes.php?msg=Scheme+updated+successfully');
        exit;
    }
}

// Delete Scheme
if ($action === 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $pdo->prepare('DELETE FROM schemes WHERE id=?');
    $stmt->execute([$id]);
    header('Location: schemes.php?msg=Scheme+deleted+successfully');
    exit;
}

// Fetch all schemes
$schemes = $pdo->query('SELECT * FROM schemes ORDER BY id DESC')->fetchAll();

// Fetch scheme for edit
$edit_scheme = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $pdo->prepare('SELECT * FROM schemes WHERE id=?');
    $stmt->execute([$id]);
    $edit_scheme = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schemes - Chit Collection</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Roboto', sans-serif; background: #f8fafc; }
        .container { max-width: 900px; margin: 40px auto; }
        .table thead { background: #6366f1; color: #fff; }
        .form-section { background: #fff; border-radius: 12px; box-shadow: 0 2px 12px rgba(99,102,241,0.08); padding: 2rem; margin-bottom: 2rem; }
        .form-title { font-size: 1.3rem; font-weight: 600; margin-bottom: 1rem; }
    </style>
</head>
<body>
    <div class="container">
        <h2 class="my-4">Scheme Entries</h2>
        <div id="popupMsg" class="toast align-items-center text-bg-success border-0 position-fixed top-0 end-0 m-4" role="alert" aria-live="assertive" aria-atomic="true" style="z-index:9999;display:none;">
            <div class="d-flex">
                <div class="toast-body" id="popupMsgText"></div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close" onclick="hidePopupMsg()"></button>
            </div>
        </div>
        <script>
        function showPopupMsg(msg, type) {
            var popup = document.getElementById('popupMsg');
            var popupText = document.getElementById('popupMsgText');
            popupText.textContent = msg;
            popup.classList.remove('text-bg-success', 'text-bg-danger');
            popup.classList.add(type === 'error' ? 'text-bg-danger' : 'text-bg-success');
            popup.style.display = 'block';
            setTimeout(hidePopupMsg, 3500);
        }
        function hidePopupMsg() {
            var popup = document.getElementById('popupMsg');
            popup.style.display = 'none';
        }
        <?php if (isset($_GET['msg'])): ?>
        window.addEventListener('DOMContentLoaded', function() {
            showPopupMsg("<?= htmlspecialchars($_GET['msg']) ?>", "<?= (isset($_GET['type']) && $_GET['type'] === 'error') ? 'error' : 'success' ?>");
            if (window.history.replaceState) {
                var url = new URL(window.location.href);
                url.searchParams.delete('msg');
                url.searchParams.delete('type');
                window.history.replaceState({}, document.title, url.pathname + url.search);
            }
        });
        <?php endif; ?>
        </script>
        <div class="form-section">
            <div class="form-title"><?= $edit_scheme ? 'Edit Scheme' : 'Add New Scheme' ?></div>
            <form method="post" action="schemes.php?action=<?= $edit_scheme ? 'edit&id=' . $edit_scheme['id'] : 'add' ?>">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($edit_scheme['name'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Collection Type</label>
                        <select name="collection_type" class="form-select" required>
                            <option value="">Select</option>
                            <option value="weekly" <?= (isset($edit_scheme['collection_type']) && $edit_scheme['collection_type'] === 'weekly') ? 'selected' : '' ?>>Weekly</option>
                            <option value="monthly" <?= (isset($edit_scheme['collection_type']) && $edit_scheme['collection_type'] === 'monthly') ? 'selected' : '' ?>>Monthly</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Total Dues</label>
                        <input type="number" name="total_dues" class="form-control" required min="1" step="1" value="<?= htmlspecialchars($edit_scheme['total_dues'] ?? '') ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Due Amount</label>
                        <input type="number" name="due_amount" class="form-control" required min="1" step="0.01" value="<?= htmlspecialchars($edit_scheme['due_amount'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start_date" class="form-control" required value="<?= htmlspecialchars($edit_scheme['start_date'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">End Date</label>
                        <input type="date" name="end_date" class="form-control" required value="<?= htmlspecialchars($edit_scheme['end_date'] ?? '') ?>">
                    </div>
                    <div class="col-md-2 align-self-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <?= $edit_scheme ? 'Update' : 'Add' ?>
                        </button>
                    </div>
                    <?php if ($edit_scheme): ?>
                        <div class="col-md-2 align-self-end">
                            <a href="schemes.php" class="btn btn-secondary w-100">Cancel</a>
                        </div>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        <div class="card">
            <div class="card-header bg-primary text-white">All Schemes</div>
            <div class="card-body p-0">
                <table class="table table-bordered table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Total Dues</th>
                            <th>Due Amount</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($schemes as $scheme): ?>
                        <tr>
                            <td><?= htmlspecialchars($scheme['name']) ?></td>
                            <td><?= htmlspecialchars(ucfirst($scheme['collection_type'])) ?></td>
                            <td><?= htmlspecialchars($scheme['total_dues']) ?></td>
                            <td><?= htmlspecialchars($scheme['due_amount']) ?></td>
                            <td><?= htmlspecialchars($scheme['start_date']) ?></td>
                            <td><?= htmlspecialchars($scheme['end_date']) ?></td>
                            <td>
                                <a href="schemes.php?action=edit&id=<?= $scheme['id'] ?>" class="btn btn-sm btn-warning"><i class="bi bi-pencil"></i></a>
                                <a href="schemes.php?action=delete&id=<?= $scheme['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this scheme?');"><i class="bi bi-trash"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-4 text-end">
            <?php $dash = (isset($_SESSION['role']) && $_SESSION['role'] === 'executive') ? 'executive_dashboard.php' : 'admin_dashboard.php'; ?>
            <a href="<?= $dash ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back to Dashboard</a>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
