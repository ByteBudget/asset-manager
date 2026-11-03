<?php
require_once __DIR__ . '/../config/database.php';

$pdo = getDBConnection();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    header('Location: /brands/index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM brands WHERE id = ?");
$stmt->execute([$id]);
$brand = $stmt->fetch();

if (!$brand) {
    $_SESSION['flash_error'] = 'Brand not found.';
    header('Location: /brands/index.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCSRF();

    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '') ?: null;

    if (empty($name)) {
        $errors[] = 'Brand Name is required.';
    } else {
        $chk = $pdo->prepare("SELECT id FROM brands WHERE name = ? AND id != ?");
        $chk->execute([$name, $id]);
        if ($chk->fetch()) {
            $errors[] = 'Brand name already exists.';
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("UPDATE brands SET name = ?, description = ? WHERE id = ?");
        $stmt->execute([$name, $description, $id]);

        $_SESSION['flash_success'] = 'Brand updated successfully!';
        header('Location: /brands/index.php');
        exit;
    }
} else {
    $_POST = $brand;
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h4 class="card-title mb-0">Edit Brand</h4>
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

                <form method="POST" action="/brands/edit.php">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="id" value="<?= $id; ?>">

                    <div class="mb-3">
                        <label class="form-label">Brand Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required value="<?= sanitize($_POST['name'] ?? ''); ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3"><?= sanitize($_POST['description'] ?? ''); ?></textarea>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="/brands/index.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Update Brand</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
