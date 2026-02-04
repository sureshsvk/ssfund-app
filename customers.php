<?php
// customers.php - Customer CRUD page
session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'executive'])) {
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

// Fetch schemes and executives for select options
$schemes = $pdo->query('SELECT id, name FROM schemes ORDER BY name')->fetchAll();
$executives = $pdo->query('SELECT id, name FROM executives ORDER BY name')->fetchAll();

// Handle CRUD actions
$action = $_GET['action'] ?? '';

// Add Customer
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $place = trim($_POST['place'] ?? '');
    $pincode = trim($_POST['pincode'] ?? '');
    $cellno = trim($_POST['cellno'] ?? '');
    $scheme_id = intval($_POST['scheme_id'] ?? 0);
    $refname = trim($_POST['refname'] ?? '');
    $refcellno = trim($_POST['refcellno'] ?? '');
    $executive_id = intval($_POST['executive_id'] ?? 0);
    if ($name && $address && $place && $pincode && $cellno && $scheme_id && $executive_id) {
        $stmt = $pdo->prepare('INSERT INTO customers (name, address, place, pincode, cellno, scheme_id, refname, refcellno, executive_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$name, $address, $place, $pincode, $cellno, $scheme_id, $refname, $refcellno, $executive_id]);
        header('Location: customers.php?msg=Customer+added+successfully');
        exit;
    }
}

// Edit Customer
if ($action === 'edit' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $name = trim($_POST['name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $place = trim($_POST['place'] ?? '');
    $pincode = trim($_POST['pincode'] ?? '');
    $cellno = trim($_POST['cellno'] ?? '');
    $scheme_id = intval($_POST['scheme_id'] ?? 0);
    $refname = trim($_POST['refname'] ?? '');
    $refcellno = trim($_POST['refcellno'] ?? '');
    $executive_id = intval($_POST['executive_id'] ?? 0);
    if ($name && $address && $place && $pincode && $cellno && $scheme_id && $executive_id) {
        $stmt = $pdo->prepare('UPDATE customers SET name=?, address=?, place=?, pincode=?, cellno=?, scheme_id=?, refname=?, refcellno=?, executive_id=? WHERE id=?');
        $stmt->execute([$name, $address, $place, $pincode, $cellno, $scheme_id, $refname, $refcellno, $executive_id, $id]);
        header('Location: customers.php?msg=Customer+updated+successfully');
        exit;
    }
}

// Delete Customer
if ($action === 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $pdo->prepare('DELETE FROM customers WHERE id=?');
    $stmt->execute([$id]);
    header('Location: customers.php?msg=Customer+deleted+successfully');
    exit;
}

// Pagination setup
$per_page = max(1, intval($_GET['per_page'] ?? 10));
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;

// Build base where clause depending on role
$where = '';
$params = [];
if ($_SESSION['role'] !== 'admin') {
    $where = ' WHERE c.executive_id = ?';
    $params[] = $_SESSION['executive_id'] ?? $_SESSION['user_id'];
}

// Get total count
$countStmt = $pdo->prepare('SELECT COUNT(*) FROM customers c' . $where);
$countStmt->execute($params);
$total_customers = intval($countStmt->fetchColumn());
$total_pages = max(1, ceil($total_customers / $per_page));

// Fetch paginated customers
$sql = 'SELECT c.*, s.name AS scheme_name, e.name AS executive_name FROM customers c LEFT JOIN schemes s ON c.scheme_id = s.id LEFT JOIN executives e ON c.executive_id = e.id' . $where . ' ORDER BY c.id DESC LIMIT ? OFFSET ?';
$params[] = $per_page;
$params[] = $offset;
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll();

// Fetch customer for edit
$edit_customer = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $pdo->prepare('SELECT * FROM customers WHERE id=?');
    $stmt->execute([$id]);
    $edit_customer = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customers - Chit Collection</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Roboto', sans-serif; background: #f8fafc; }
        .container { max-width: 1000px; margin: 40px auto; }
        .table thead { background: #6366f1; color: #fff; }
        .form-section { background: #fff; border-radius: 12px; box-shadow: 0 2px 12px rgba(99,102,241,0.08); padding: 2rem; margin-bottom: 2rem; }
        .form-title { font-size: 1.3rem; font-weight: 600; margin-bottom: 1rem; }
    </style>
</head>
<body>
    <div class="container">
        <h2 class="my-4">Customer Entries</h2>
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
            <div class="form-title"><?= $edit_customer ? 'Edit Customer' : 'Add New Customer' ?></div>
            <form method="post" action="customers.php?action=<?= $edit_customer ? 'edit&id=' . $edit_customer['id'] : 'add' ?>">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($edit_customer['name'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Address</label>
                        <input type="text" name="address" class="form-control" required value="<?= htmlspecialchars($edit_customer['address'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Place</label>
                        <input type="text" name="place" class="form-control" required value="<?= htmlspecialchars($edit_customer['place'] ?? '') ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Pincode</label>
                        <input type="text" name="pincode" class="form-control" required pattern="\d{6}" maxlength="6" value="<?= htmlspecialchars($edit_customer['pincode'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Cell No</label>
                        <input type="text" name="cellno" class="form-control" required pattern="\d{10}" maxlength="10" value="<?= htmlspecialchars($edit_customer['cellno'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Scheme</label>
                        <select name="scheme_id" class="form-select" required>
                            <option value="">Select Scheme</option>
                            <?php foreach ($schemes as $scheme): ?>
                                <option value="<?= $scheme['id'] ?>" <?= (isset($edit_customer['scheme_id']) && $edit_customer['scheme_id'] == $scheme['id']) ? 'selected' : '' ?>><?= htmlspecialchars($scheme['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Executive</label>
                        <select name="executive_id" class="form-select" required>
                            <option value="">Select Executive</option>
                            <?php foreach ($executives as $executive): ?>
                                <option value="<?= $executive['id'] ?>" <?= (isset($edit_customer['executive_id']) && $edit_customer['executive_id'] == $executive['id']) ? 'selected' : '' ?>><?= htmlspecialchars($executive['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Reference Name</label>
                        <input type="text" name="refname" class="form-control" value="<?= htmlspecialchars($edit_customer['refname'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Reference Cell No</label>
                        <input type="text" name="refcellno" class="form-control" pattern="\d{10}" maxlength="10" value="<?= htmlspecialchars($edit_customer['refcellno'] ?? '') ?>">
                    </div>
                    <div class="col-md-2 align-self-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <?= $edit_customer ? 'Update' : 'Add' ?>
                        </button>
                    </div>
                    <?php if ($edit_customer): ?>
                        <div class="col-md-2 align-self-end">
                            <a href="customers.php" class="btn btn-secondary w-100">Cancel</a>
                        </div>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        <div class="card">
            <div class="card-header bg-primary text-white">All Customers</div>
            <div class="card-body p-0">
                <table class="table table-bordered table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Address</th>
                            <th>Place</th>
                            <th>Pincode</th>
                            <th>Cell No</th>
                            <th>Scheme</th>
                            <th>Executive</th>
                            <th>Ref Name</th>
                            <th>Ref Cell No</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customers as $customer): ?>
                        <tr>
                            <td><?= htmlspecialchars($customer['name']) ?></td>
                            <td><?= htmlspecialchars($customer['address']) ?></td>
                            <td><?= htmlspecialchars($customer['place']) ?></td>
                            <td><?= htmlspecialchars($customer['pincode']) ?></td>
                            <td><?= htmlspecialchars($customer['cellno']) ?></td>
                            <td><?= htmlspecialchars($customer['scheme_name']) ?></td>
                            <td><?= htmlspecialchars($customer['executive_name']) ?></td>
                            <td><?= htmlspecialchars($customer['refname']) ?></td>
                            <td><?= htmlspecialchars($customer['refcellno']) ?></td>
                            <td>
                                <a href="customers.php?action=edit&id=<?= $customer['id'] ?>" class="btn btn-sm btn-warning"><i class="bi bi-pencil"></i></a>
                                <a href="customers.php?action=delete&id=<?= $customer['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this customer?');"><i class="bi bi-trash"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="row mt-3">
            <div class="col-md-6">
                <p class="mb-0">Showing <?= $total_customers ? ($offset + 1) : 0 ?> to <?= min($offset + count($customers), $total_customers) ?> of <?= $total_customers ?> entries</p>
            </div>
            <div class="col-md-6 text-end">
                <nav>
                    <ul class="pagination justify-content-end mb-0">
                        <?php if ($page > 1): ?>
                            <li class="page-item"><a class="page-link" href="?page=<?= $page - 1 ?>&per_page=<?= $per_page ?>">Previous</a></li>
                        <?php else: ?>
                            <li class="page-item disabled"><span class="page-link">Previous</span></li>
                        <?php endif; ?>

                        <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                            <li class="page-item <?= ($p === $page) ? 'active' : '' ?>"><a class="page-link" href="?page=<?= $p ?>&per_page=<?= $per_page ?>"><?= $p ?></a></li>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <li class="page-item"><a class="page-link" href="?page=<?= $page + 1 ?>&per_page=<?= $per_page ?>">Next</a></li>
                        <?php else: ?>
                            <li class="page-item disabled"><span class="page-link">Next</span></li>
                        <?php endif; ?>
                    </ul>
                </nav>
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
