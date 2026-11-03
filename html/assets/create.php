<?php
require_once __DIR__ . '/../config/database.php';

$pdo = getDBConnection();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCSRF();

    $asset_tag    = trim($_POST['asset_tag'] ?? '');
    $name         = trim($_POST['name'] ?? '');
    $category_id  = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT) ?: null;
    $serial_number= trim($_POST['serial_number'] ?? '') ?: null;
    $purchase_date= trim($_POST['purchase_date'] ?? '') ?: null;
    $purchase_cost= filter_input(INPUT_POST, 'purchase_cost', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
    $status       = trim($_POST['status'] ?? 'Available');
    $notes        = trim($_POST['notes'] ?? '') ?: null;

    // Field Validations
    if (empty($asset_tag)) { $errors[] = 'Asset Tag is required.'; }
    if (empty($name)) { $errors[] = 'Asset Name is required.'; }

    // Check unique Asset Tag
    if (!empty($asset_tag)) {
        $chkStmt = $pdo->prepare("SELECT id FROM assets WHERE asset_tag = ?");
        $chkStmt->execute([$asset_tag]);
        if ($chkStmt->fetch()) {
            $errors[] = 'Asset Tag must be unique. The provided tag already exists.';
        }
    }

    if (empty($errors)) {
        $sql = "INSERT INTO assets (asset_tag, name, category_id, serial_number, purchase_date, purchase_cost, status, notes) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$asset_tag, $name, $category_id, $serial_number, $purchase_date, $purchase_cost, $status, $notes]);

        $_SESSION['flash_success'] = 'Asset created successfully!';
        header('Location: /assets/index.php');
        exit;
    }
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h4 class="card-title mb-0">Add New Asset</h4>
            </div>
            <div class="card-body">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $err): ?>
                                <li><?= sanitize($err); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" action="/assets/create.php">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                    
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Asset Tag <span class="text-danger">*</span></label>
                            <input type="text" name="asset_tag" class="form-control" required value="<?= sanitize($_POST['asset_tag'] ?? ''); ?>" placeholder="e.g. AST-001">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Asset Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required value="<?= sanitize($_POST['name'] ?? ''); ?>" placeholder="e.g. MacBook Pro">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Category</label>
                            <select name="category_id" class="form-select">
                                <option value="">-- Select Category --</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id']; ?>" <?= (($_POST['category_id'] ?? '') == $cat['id']) ? 'selected' : ''; ?>>
                                        <?= sanitize($cat['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <?php foreach (['Available', 'In Use', 'Under Maintenance', 'Retired'] as $st): ?>
                                    <option value="<?= $st; ?>" <?= (($_POST['status'] ?? 'Available') === $st) ? 'selected' : ''; ?>><?= $st; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Serial Number</label>
                            <input type="text" name="serial_number" class="form-control" value="<?= sanitize($_POST['serial_number'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Purchase Date</label>
                            <input type="date" name="purchase_date" class="form-control" value="<?= sanitize($_POST['purchase_date'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Purchase Cost ($)</label>
                            <input type="number" step="0.01" name="purchase_cost" class="form-control" value="<?= sanitize($_POST['purchase_cost'] ?? ''); ?>" placeholder="0.00">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="3"><?= sanitize($_POST['notes'] ?? ''); ?></textarea>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="/assets/index.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Save Asset</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
