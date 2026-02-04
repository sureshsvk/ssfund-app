<?php
// executives.php - Executive CRUD page
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

// Add Executive
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $place = trim($_POST['place'] ?? '');
    $pincode = trim($_POST['pincode'] ?? '');
    $cellno = trim($_POST['cellno'] ?? '');
    if ($name && $address && $place && $pincode && $cellno) {
        $stmt = $pdo->prepare('INSERT INTO executives (name, address, place, pincode, cellno) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$name, $address, $place, $pincode, $cellno]);
        header('Location: executives.php?msg=Executive+added+successfully');
        exit;
    }
}

// Edit Executive
if ($action === 'edit' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $name = trim($_POST['name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $place = trim($_POST['place'] ?? '');
    $pincode = trim($_POST['pincode'] ?? '');
    $cellno = trim($_POST['cellno'] ?? '');
    if ($name && $address && $place && $pincode && $cellno) {
        $stmt = $pdo->prepare('UPDATE executives SET name=?, address=?, place=?, pincode=?, cellno=? WHERE id=?');
        $stmt->execute([$name, $address, $place, $pincode, $cellno, $id]);
        header('Location: executives.php?msg=Executive+updated+successfully');
        exit;
    }
}

// Delete Executive
if ($action === 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $pdo->prepare('DELETE FROM executives WHERE id=?');
    $stmt->execute([$id]);
    header('Location: executives.php?msg=Executive+deleted+successfully');
    exit;
}

// Fetch all executives
$executives = $pdo->query('SELECT * FROM executives ORDER BY id DESC')->fetchAll();

// Fetch executive for edit
$edit_executive = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $pdo->prepare('SELECT * FROM executives WHERE id=?');
    $stmt->execute([$id]);
    $edit_executive = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Executives - Chit Collection</title>
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
        <h2 class="my-4">Executive Entries</h2>
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
            <div class="form-title"><?= $edit_executive ? 'Edit Executive' : 'Add New Executive' ?></div>
            <form method="post" action="executives.php?action=<?= $edit_executive ? 'edit&id=' . $edit_executive['id'] : 'add' ?>">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($edit_executive['name'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Address</label>
                        <input type="text" name="address" class="form-control" required value="<?= htmlspecialchars($edit_executive['address'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Place</label>
                        <input type="text" name="place" class="form-control" required value="<?= htmlspecialchars($edit_executive['place'] ?? '') ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Pincode</label>
                        <input type="text" name="pincode" class="form-control" required pattern="\d{6}" maxlength="6" value="<?= htmlspecialchars($edit_executive['pincode'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Cell No</label>
                        <input type="text" name="cellno" class="form-control" required pattern="\d{10}" maxlength="10" value="<?= htmlspecialchars($edit_executive['cellno'] ?? '') ?>">
                    </div>
                    <div class="col-md-2 align-self-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <?= $edit_executive ? 'Update' : 'Add' ?>
                        </button>
                    </div>
                    <?php if ($edit_executive): ?>
                        <div class="col-md-2 align-self-end">
                            <a href="executives.php" class="btn btn-secondary w-100">Cancel</a>
                        </div>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        <div class="card">
            <div class="card-header bg-primary text-white">All Executives</div>
            <div class="card-body p-0">
                <table class="table table-bordered table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Address</th>
                            <th>Place</th>
                            <th>Pincode</th>
                            <th>Cell No</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($executives as $executive): ?>
                        <tr>
                            <td><?= htmlspecialchars($executive['name']) ?></td>
                            <td><?= htmlspecialchars($executive['address']) ?></td>
                            <td><?= htmlspecialchars($executive['place']) ?></td>
                            <td><?= htmlspecialchars($executive['pincode']) ?></td>
                            <td><?= htmlspecialchars($executive['cellno']) ?></td>
                            <td>
                                <a href="executives.php?action=edit&id=<?= $executive['id'] ?>" class="btn btn-sm btn-warning"><i class="bi bi-pencil"></i></a>
                                <a href="executives.php?action=delete&id=<?= $executive['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this executive?');"><i class="bi bi-trash"></i></a>
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
