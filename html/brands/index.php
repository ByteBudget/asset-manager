<?php
require_once __DIR__ . '/../config/database.php';

$pdo = getDBConnection();
$errors = [];

// Handle inline brand addition
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCSRF();

    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '') ?: null;

    if (empty($name)) {
        $errors[] = 'Brand name is required.';
    } else {
        $chk = $pdo->prepare("SELECT id FROM brands WHERE name = ?");
        $chk->execute([$name]);
        if ($chk->fetch()) {
            $errors[] = 'Brand with this name already exists.';
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("INSERT INTO brands (name, description) VALUES (?, ?)");
        $stmt->execute([$name, $description]);
        $_SESSION['flash_success'] = 'Brand added successfully!';
        header('Location: /brands/index.php');
        exit;
    }
}

// Fetch brands with total assigned assets count
$sql = "SELECT b.*, COUNT(a.id) AS total_assets 
        FROM brands b 
        LEFT JOIN assets a ON b.id = a.brand_id 
        GROUP BY b.id 
        ORDER BY b.name ASC";
$brands = $pdo->query($sql)->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row">
    <!-- Brand Add Form -->
    <div class="col-md-4 mb-4">
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h5 class="card-title mb-0">Add Brand</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger mb-3">
                        <ul class="mb-0">
                            <?php foreach ($errors as $err): ?>
                                <li><?= sanitize($err); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" action="/brands/index.php">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                    <div class="mb-3">
                        <label class="form-label">Brand Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required placeholder="e.g. Apple, Dell, HP">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Optional details..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary w-100"><i class="bi-plus-circle me-1"></i> Save Brand</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Brands Table -->
    <div class="col-md-8">
        <div class="table-responsive p-3">
            <h5 class="mb-3">Brands Directory</h5>
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Brand Name</th>
                        <th>Description</th>
                        <th>Assigned Assets</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($brands)): ?>
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">No brands created yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($brands as $brand): ?>
                            <tr>
                                <td><strong><?= sanitize($brand['name']); ?></strong></td>
                                <td><?= sanitize($brand['description'] ?? 'N/A'); ?></td>
                                <td>
                                    <span class="badge bg-secondary rounded-pill">
                                        <?= $brand['total_assets']; ?> Asset(s)
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a href="/brands/edit.php?id=<?= $brand['id']; ?>" class="btn btn-sm btn-warning me-1"><i class="bi-pencil"></i></a>
                                    <form method="POST" action="/brands/delete.php" class="d-inline" onsubmit="return confirm('Deleting this brand will unassign its associated assets. Proceed?');">
                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                                        <input type="hidden" name="id" value="<?= $brand['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger"><i class="bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
