<?php
// collections.php - Collection CRUD page
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

// Fetch executives
$executives = $pdo->query('SELECT id, name FROM executives ORDER BY name')->fetchAll();

// Fetch schemes
$schemes = $pdo->query('SELECT id, name, due_amount, total_dues FROM schemes ORDER BY name')->fetchAll();

// Fetch customers based on executive (for admin: all, for executive: only their customers)
$customers = [];
if ($_SESSION['role'] === 'admin') {
    $customers = $pdo->query('SELECT c.*, e.name AS executive_name, s.name AS scheme_name FROM customers c LEFT JOIN executives e ON c.executive_id = e.id LEFT JOIN schemes s ON c.scheme_id = s.id ORDER BY c.name')->fetchAll();
} else {
    $stmt = $pdo->prepare('SELECT c.*, e.name AS executive_name, s.name AS scheme_name FROM customers c LEFT JOIN executives e ON c.executive_id = e.id LEFT JOIN schemes s ON c.scheme_id = s.id WHERE c.executive_id = ? ORDER BY c.name');
    $stmt->execute([$_SESSION['user_id']]);
    $customers = $stmt->fetchAll();
}

// Handle CRUD actions
$action = $_GET['action'] ?? '';

// Add Collection
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $executive_id = intval($_POST['executive_id'] ?? 0);
    $scheme_id = intval($_POST['scheme_id'] ?? 0);
    $customer_id = intval($_POST['customer_id'] ?? 0);
    $due_no = intval($_POST['due_no'] ?? 0);
    $due_amount = floatval($_POST['due_amount'] ?? 0);
    $paid_amount = floatval($_POST['paid_amount'] ?? 0);
    $paid_type = trim($_POST['paid_type'] ?? '');
    // Check for duplicate due_no for this customer and scheme
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM collections WHERE customer_id=? AND scheme_id=? AND due_no=?');
    $stmt->execute([$customer_id, $scheme_id, $due_no]);
    if ($stmt->fetchColumn() > 0) {
        header('Location: collections.php?msg=Duplicate+Due+No+for+this+customer+and+scheme&type=error');
        exit;
    }
    // Get scheme info
    $stmt = $pdo->prepare('SELECT total_dues, due_amount FROM schemes WHERE id=?');
    $stmt->execute([$scheme_id]);
    $scheme = $stmt->fetch();
    $total_dues = $scheme ? intval($scheme['total_dues']) : 0;
    $scheme_due_amount = $scheme ? floatval($scheme['due_amount']) : 0;
    // Get total paid so far for this customer+scheme
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(paid_amount),0) FROM collections WHERE customer_id=? AND scheme_id=?');
    $stmt->execute([$customer_id, $scheme_id]);
    $sum_paid_before = floatval($stmt->fetchColumn());
    $new_total_paid = $sum_paid_before + $paid_amount;
    $balance_amount = ($total_dues * $scheme_due_amount) - $new_total_paid;
    if ($balance_amount < 0) $balance_amount = 0;
    if ($executive_id && $scheme_id && $customer_id && $due_no && $due_amount && $paid_amount && $paid_type) {
        $stmt = $pdo->prepare('INSERT INTO collections (executive_id, scheme_id, customer_id, due_no, due_amount, paid_amount, balance_amount, paid_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$executive_id, $scheme_id, $customer_id, $due_no, $due_amount, $paid_amount, $balance_amount, $paid_type]);
        // Recalculate balances for this customer and scheme to keep snapshots consistent
        recalcBalances($pdo, $customer_id, $scheme_id);
        header('Location: collections.php?msg=Collection+added+successfully');
        exit;
    }
}

// Edit Collection
if ($action === 'edit' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $executive_id = intval($_POST['executive_id'] ?? 0);
    $scheme_id = intval($_POST['scheme_id'] ?? 0);
    $customer_id = intval($_POST['customer_id'] ?? 0);
    $due_no = intval($_POST['due_no'] ?? 0);
    $due_amount = floatval($_POST['due_amount'] ?? 0);
    $paid_amount = floatval($_POST['paid_amount'] ?? 0);
    $paid_type = trim($_POST['paid_type'] ?? '');
    // Check for duplicate due_no for this customer and scheme (excluding this record)
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM collections WHERE customer_id=? AND scheme_id=? AND due_no=? AND id<>?');
    $stmt->execute([$customer_id, $scheme_id, $due_no, $id]);
    if ($stmt->fetchColumn() > 0) {
        header('Location: collections.php?msg=Duplicate+Due+No+for+this+customer+and+scheme&type=error');
        exit;
    }
    // Get scheme info
    $stmt = $pdo->prepare('SELECT total_dues, due_amount FROM schemes WHERE id=?');
    $stmt->execute([$scheme_id]);
    $scheme = $stmt->fetch();
    $total_dues = $scheme ? intval($scheme['total_dues']) : 0;
    $scheme_due_amount = $scheme ? floatval($scheme['due_amount']) : 0;
    // Get total paid excluding this record for this customer+scheme
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(paid_amount),0) FROM collections WHERE customer_id=? AND scheme_id=? AND id<>?');
    $stmt->execute([$customer_id, $scheme_id, $id]);
    $sum_paid_excl = floatval($stmt->fetchColumn());
    $new_total_paid = $sum_paid_excl + $paid_amount;
    $balance_amount = ($total_dues * $scheme_due_amount) - $new_total_paid;
    if ($balance_amount < 0) $balance_amount = 0;
    if ($executive_id && $scheme_id && $customer_id && $due_no && $due_amount && $paid_amount && $paid_type) {
        $stmt = $pdo->prepare('UPDATE collections SET executive_id=?, scheme_id=?, customer_id=?, due_no=?, due_amount=?, paid_amount=?, balance_amount=?, paid_type=? WHERE id=?');
        $stmt->execute([$executive_id, $scheme_id, $customer_id, $due_no, $due_amount, $paid_amount, $balance_amount, $paid_type, $id]);
        // Recalculate balances for this customer and scheme to keep snapshots consistent
        recalcBalances($pdo, $customer_id, $scheme_id);
        header('Location: collections.php?msg=Collection+updated+successfully');
        exit;
    }
}

// Delete Collection
if ($action === 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    // get customer and scheme before deletion
    $stmt = $pdo->prepare('SELECT customer_id, scheme_id FROM collections WHERE id=?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    $customer_to_recalc = $row ? intval($row['customer_id']) : null;
    $scheme_to_recalc = $row ? intval($row['scheme_id']) : null;

    $stmt = $pdo->prepare('DELETE FROM collections WHERE id=?');
    $stmt->execute([$id]);

    // Recalculate balances for affected customer+scheme
    if ($customer_to_recalc && $scheme_to_recalc) {
        recalcBalances($pdo, $customer_to_recalc, $scheme_to_recalc);
    }

    header('Location: collections.php?msg=Collection+deleted+successfully');
    exit;
}

// Fetch all collections (with executive, scheme, customer names)
if (isset($_SESSION['role']) && $_SESSION['role'] === 'executive') {
    // Executive should only see their own collections
    $execId = $_SESSION['executive_id'] ?? $_SESSION['user_id'];
    $stmt = $pdo->prepare('SELECT col.*, e.name AS executive_name, s.name AS scheme_name, c.name AS customer_name FROM collections col LEFT JOIN executives e ON col.executive_id = e.id LEFT JOIN schemes s ON col.scheme_id = s.id LEFT JOIN customers c ON col.customer_id = c.id WHERE col.executive_id = ? ORDER BY col.id DESC');
    $stmt->execute([$execId]);
    $collections = $stmt->fetchAll();
} else {
    $collections = $pdo->query('SELECT col.*, e.name AS executive_name, s.name AS scheme_name, c.name AS customer_name FROM collections col LEFT JOIN executives e ON col.executive_id = e.id LEFT JOIN schemes s ON col.scheme_id = s.id LEFT JOIN customers c ON col.customer_id = c.id ORDER BY col.id DESC')->fetchAll();
}

// Helper to recalculate balances for a customer+scheme
function recalcBalances($pdo, $customer_id, $scheme_id) {
    // get scheme info
    $stmt = $pdo->prepare('SELECT total_dues, due_amount FROM schemes WHERE id=?');
    $stmt->execute([$scheme_id]);
    $scheme = $stmt->fetch();
    $total_dues = $scheme ? intval($scheme['total_dues']) : 0;
    $due_amount = $scheme ? floatval($scheme['due_amount']) : 0;

    $stmt = $pdo->prepare('SELECT id, paid_amount FROM collections WHERE customer_id=? AND scheme_id=? ORDER BY id ASC');
    $stmt->execute([$customer_id, $scheme_id]);
    $rows = $stmt->fetchAll();
    $cumulative = 0.0;
    foreach ($rows as $r) {
        $cumulative += floatval($r['paid_amount']);
        $balance = ($total_dues * $due_amount) - $cumulative;
        if ($balance < 0) $balance = 0;
        $up = $pdo->prepare('UPDATE collections SET balance_amount=? WHERE id=?');
        $up->execute([round($balance,2), $r['id']]);
    }
}

// Fetch collection for edit
$edit_collection = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $pdo->prepare('SELECT * FROM collections WHERE id=?');
    $stmt->execute([$id]);
    $edit_collection = $stmt->fetch();
}

// Handle AJAX for next_due_no before any HTML output
if (isset($_GET['action']) && $_GET['action'] === 'next_due_no' && isset($_GET['customer_id'], $_GET['scheme_id'])) {
    $customer_id = intval($_GET['customer_id']);
    $scheme_id = intval($_GET['scheme_id']);
    $stmt = $pdo->prepare('SELECT MAX(due_no) FROM collections WHERE customer_id=? AND scheme_id=?');
    $stmt->execute([$customer_id, $scheme_id]);
    $max_due = intval($stmt->fetchColumn());
    echo $max_due + 1;
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Collections - Chit Collection</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Roboto', sans-serif; background: #f8fafc; }
        .container { max-width: 1100px; margin: 40px auto; }
        .table thead { background: #6366f1; color: #fff; }
        .form-section { background: #fff; border-radius: 12px; box-shadow: 0 2px 12px rgba(99,102,241,0.08); padding: 2rem; margin-bottom: 2rem; }
        .form-title { font-size: 1.3rem; font-weight: 600; margin-bottom: 1rem; }
    </style>
    <script>
    // Dynamic due amount loading, customer filtering, due no auto-fill, and balance calculation
    function updateDueAmount() {
        var schemeSelect = document.getElementById('scheme_id');
        var dueAmountInput = document.getElementById('due_amount');
        var selectedOption = schemeSelect.options[schemeSelect.selectedIndex];
        var dueAmount = selectedOption.getAttribute('data-due-amount');
        if (dueAmountInput && dueAmount) {
            dueAmountInput.value = dueAmount;
        }
        updateBalanceAmount();
    }

    function updateDueNoAndBalance() {
        updateDueNo();
        updateBalanceAmount();
    }
    function filterCustomersByExecutive() {
        var execSelect = document.getElementById('executive_id');
        var execId = execSelect.value;
        var customerRows = document.querySelectorAll('.customer-option');
        customerRows.forEach(function(opt) {
            if (execId === '' || opt.getAttribute('data-exec-id') === execId) {
                opt.style.display = '';
            } else {
                opt.style.display = 'none';
            }
        });
    }
    function updateBalanceAmount() {
        var schemeSelect = document.getElementById('scheme_id');
        var selectedOption = schemeSelect.options[schemeSelect.selectedIndex];
        var totalDues = parseInt(selectedOption.getAttribute('data-total-dues')) || 0;
        var dueAmount = parseFloat(selectedOption.getAttribute('data-due-amount')) || 0;
        var dueNo = parseInt(document.querySelector('input[name="due_no"]').value) || 0;
        var balance = (totalDues * dueAmount) - (dueNo * dueAmount);
        var balanceInput = document.querySelector('input[name="balance_amount"]');
        if (balanceInput) balanceInput.value = balance.toFixed(2);
    }
    // AJAX to get next due no for selected customer and scheme
    function updateDueNo() {
        var customerId = document.getElementById('customer_id').value;
        var schemeId = document.getElementById('scheme_id').value;
        if (customerId && schemeId) {
            var xhr = new XMLHttpRequest();
            xhr.open('GET', 'collections.php?action=next_due_no&customer_id=' + customerId + '&scheme_id=' + schemeId, true);
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    var dueNoInput = document.querySelector('input[name="due_no"]');
                    if (dueNoInput) {
                        dueNoInput.value = xhr.responseText;
                        updateBalanceAmount();
                    }
                }
            };
            xhr.send();
        }
    }
    document.addEventListener('DOMContentLoaded', function() {
        var isEdit = <?php echo (isset($edit_collection) && $edit_collection ? 'true' : 'false'); ?>;
        console.log("Script loaded, isEdit:", isEdit);
        document.getElementById('scheme_id').addEventListener('change', function() {
            updateDueAmount();
            if (!isEdit) {
                updateDueNoAndBalance();
            } else {
                updateBalanceAmount();
            }
        });
        document.getElementById('customer_id').addEventListener('change', function() {
            if (!isEdit) {
                updateDueNoAndBalance();
            } else {
                updateBalanceAmount();
            }
        });
        var dueNoInput = document.querySelector('input[name="due_no"]');
        if (dueNoInput) dueNoInput.addEventListener('input', updateBalanceAmount);
        // On page load, always set due amount and balance, and due no for add
        updateDueAmount();
        if (!isEdit) {
            updateDueNoAndBalance();
        } else {
            updateBalanceAmount();
        }
    });
    </script>
</head>
<body>
    <div class="container">
        <h2 class="my-4">Collection Entries</h2>
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
            <div class="form-title"><?= $edit_collection ? 'Edit Collection' : 'Add New Collection' ?></div>
            <form method="post" action="collections.php?action=<?= $edit_collection ? 'edit&id=' . $edit_collection['id'] : 'add' ?>">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Executive</label>
                        <select name="executive_id" id="executive_id" class="form-select" required onchange="filterCustomersByExecutive()">
                            <option value="">Select Executive</option>
                            <?php foreach ($executives as $executive): ?>
                                <option value="<?= $executive['id'] ?>" <?= (isset($edit_collection['executive_id']) && $edit_collection['executive_id'] == $executive['id']) ? 'selected' : '' ?>><?= htmlspecialchars($executive['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Scheme</label>
                        <select name="scheme_id" id="scheme_id" class="form-select" required>
                            <option value="">Select Scheme</option>
                            <?php foreach ($schemes as $scheme): ?>
                                <? echo $scheme['due_amount']; ?>
                                <option value="<?= $scheme['id'] ?>" data-due-amount="<?= $scheme['due_amount'] ?>" data-total-dues="<?= $scheme['total_dues'] ?>" <?= (isset($edit_collection['scheme_id']) && $edit_collection['scheme_id'] == $scheme['id']) ? 'selected' : '' ?>><?= htmlspecialchars($scheme['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Customer</label>
                        <select name="customer_id" id="customer_id" class="form-select" required>
                            <option value="">Select Customer</option>
                            <?php foreach ($customers as $customer): ?>
                                <option value="<?= $customer['id'] ?>" class="customer-option" data-exec-id="<?= $customer['executive_id'] ?>" <?= (isset($edit_collection['customer_id']) && $edit_collection['customer_id'] == $customer['id']) ? 'selected' : '' ?>><?= htmlspecialchars($customer['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Due No</label>
                        <input type="number" name="due_no" class="form-control" required min="1" value="<?= htmlspecialchars($edit_collection['due_no'] ?? '') ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Due Amount</label>
                        <input type="number" name="due_amount" id="due_amount" class="form-control" required min="1" step="0.01" value="<?= htmlspecialchars($edit_collection['due_amount'] ?? '') ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Paid Amount</label>
                        <input type="number" name="paid_amount" class="form-control" required min="1" step="0.01" value="<?= htmlspecialchars($edit_collection['paid_amount'] ?? '') ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Balance Amount</label>
                        <input type="number" name="balance_amount" class="form-control" readonly value="<?= isset($edit_collection['balance_amount']) ? htmlspecialchars($edit_collection['balance_amount']) : '' ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Paid Type</label>
                        <select name="paid_type" class="form-select" required>
                            <option value="">Select Type</option>
                            <?php $paid_types = ["Cash", "GPay", "PhonePe", "Net Banking", "Cheque", "DD"]; ?>
                            <?php foreach ($paid_types as $type): ?>
                                <option value="<?= $type ?>" <?= (isset($edit_collection['paid_type']) && $edit_collection['paid_type'] === $type) ? 'selected' : '' ?>><?= $type ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 align-self-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <?= $edit_collection ? 'Update' : 'Add' ?>
                        </button>
                    </div>
                    <?php if ($edit_collection): ?>
                        <div class="col-md-2 align-self-end">
                            <a href="collections.php" class="btn btn-secondary w-100">Cancel</a>
                        </div>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        <div class="card">
            <div class="card-header bg-primary text-white">All Collections</div>
            <div class="card-body p-0">
                <form method="get" class="p-3 bg-light">
                    <div class="row g-2 align-items-center">
                        <input type="hidden" name="action" value="search">
                        <div class="col-md-3">
                            <input type="text" name="search_name" class="form-control" placeholder="Search Name" value="<?= htmlspecialchars($_GET['search_name'] ?? '') ?>">
                        </div>
                        <div class="col-md-2">
                            <input type="number" name="search_amount" class="form-control" placeholder="Amount" value="<?= htmlspecialchars($_GET['search_amount'] ?? '') ?>">
                        </div>
                        <div class="col-md-2">
                            <input type="number" name="search_due_no" class="form-control" placeholder="Due No" value="<?= htmlspecialchars($_GET['search_due_no'] ?? '') ?>">
                        </div>
                        <div class="col-md-3">
                            <select name="search_paid_type" class="form-select">
                                <option value="">All Modes</option>
                                <option value="Cash" <?= (($_GET['search_paid_type'] ?? '') === 'Cash') ? 'selected' : '' ?>>Cash</option>
                                <option value="GPay" <?= (($_GET['search_paid_type'] ?? '') === 'GPay') ? 'selected' : '' ?>>GPay</option>
                                <option value="PhonePe" <?= (($_GET['search_paid_type'] ?? '') === 'PhonePe') ? 'selected' : '' ?>>PhonePe</option>
                                <option value="Net Banking" <?= (($_GET['search_paid_type'] ?? '') === 'Net Banking') ? 'selected' : '' ?>>Net Banking</option>
                                <option value="Cheque" <?= (($_GET['search_paid_type'] ?? '') === 'Cheque') ? 'selected' : '' ?>>Cheque</option>
                                <option value="DD" <?= (($_GET['search_paid_type'] ?? '') === 'DD') ? 'selected' : '' ?>>DD</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i> Search</button>
                        </div>
                    </div>
                </form>
                <table class="table table-bordered table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Executive</th>
                            <th>Scheme</th>
                            <th>Customer</th>
                            <th>Due No</th>
                            <th>Due Amount</th>
                            <th>Paid Amount</th>
                            <th>Balance Amount</th>
                            <th>Paid Type</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Search filter logic
                        $filtered_collections = $collections;
                        if (($action === 'search') && ($_SERVER['REQUEST_METHOD'] === 'GET')) {
                            $search_name = strtolower(trim($_GET['search_name'] ?? ''));
                            $search_amount = trim($_GET['search_amount'] ?? '');
                            $search_due_no = trim($_GET['search_due_no'] ?? '');
                            $search_paid_type = trim($_GET['search_paid_type'] ?? '');
                            $filtered_collections = array_filter($collections, function($col) use ($search_name, $search_amount, $search_due_no, $search_paid_type) {
                                $match = true;
                                if ($search_name && strpos(strtolower($col['customer_name']), $search_name) === false && strpos(strtolower($col['executive_name']), $search_name) === false && strpos(strtolower($col['scheme_name']), $search_name) === false) $match = false;
                                if ($search_amount !== '' && floatval($col['paid_amount']) != floatval($search_amount)) $match = false;
                                if ($search_due_no !== '' && intval($col['due_no']) != intval($search_due_no)) $match = false;
                                if ($search_paid_type && $col['paid_type'] !== $search_paid_type) $match = false;
                                return $match;
                            });
                        }
                        ?>
                        <?php
                    // Pagination for filtered collections (array-based)
                    $per_page = max(1, intval($_GET['per_page'] ?? 10));
                    $page = max(1, intval($_GET['page'] ?? 1));
                    $total_filtered = count($filtered_collections);
                    $total_pages = max(1, ceil($total_filtered / $per_page));
                    $offset = ($page - 1) * $per_page;
                    $paginated = array_slice($filtered_collections, $offset, $per_page);
                    foreach ($paginated as $col): ?>
                        <tr>
                            <td><?= htmlspecialchars($col['executive_name']) ?></td>
                            <td><?= htmlspecialchars($col['scheme_name']) ?></td>
                            <td><?= htmlspecialchars($col['customer_name']) ?></td>
                            <td><?= htmlspecialchars($col['due_no']) ?></td>
                            <td><?= htmlspecialchars($col['due_amount']) ?></td>
                            <td><?= htmlspecialchars($col['paid_amount']) ?></td>
                            <td><?= htmlspecialchars($col['balance_amount']) ?></td>
                            <td><?= htmlspecialchars($col['paid_type']) ?></td>
                            <td>
                                <a href="collections.php?action=edit&id=<?= $col['id'] ?>" class="btn btn-sm btn-warning"><i class="bi bi-pencil"></i></a>
                                <a href="collections.php?action=delete&id=<?= $col['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this collection?');"><i class="bi bi-trash"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <?php
                    // Calculate totals
                    $total_paid = 0;
                    $total_balance = 0;
                    foreach ($filtered_collections as $col) {
                        $total_paid += floatval($col['paid_amount']);
                        $total_balance += floatval($col['balance_amount']);
                    }
                    ?>
                    <tfoot>
                        <tr style="font-weight:bold;background:#f3f4f6;">
                            <td colspan="5" class="text-end">Total:</td>
                            <td><?= number_format($total_paid, 2) ?></td>
                            <td><?= number_format($total_balance, 2) ?></td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
                <div class="row mt-3">
                    <div class="col-md-6">
                        <p class="mb-0">Showing <?= $total_filtered ? ($offset + 1) : 0 ?> to <?= min($offset + count($paginated), $total_filtered) ?> of <?= $total_filtered ?> entries</p>
                    </div>
                    <div class="col-md-6 text-end">
                        <nav>
                            <ul class="pagination justify-content-end mb-0">
                                <?php
                                $qs = $_GET;
                                unset($qs['page']);
                                $base = htmlspecialchars($_SERVER['PHP_SELF']) . (count($qs) ? ('?' . http_build_query($qs) . '&') : '?');
                                ?>
                                <?php if ($page > 1): ?>
                                    <li class="page-item"><a class="page-link" href="<?= $base ?>page=<?= $page - 1 ?>">Previous</a></li>
                                <?php else: ?>
                                    <li class="page-item disabled"><span class="page-link">Previous</span></li>
                                <?php endif; ?>

                                <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                                    <li class="page-item <?= ($p === $page) ? 'active' : '' ?>"><a class="page-link" href="<?= $base ?>page=<?= $p ?>"><?= $p ?></a></li>
                                <?php endfor; ?>

                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item"><a class="page-link" href="<?= $base ?>page=<?= $page + 1 ?>">Next</a></li>
                                <?php else: ?>
                                    <li class="page-item disabled"><span class="page-link">Next</span></li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                </div>
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
