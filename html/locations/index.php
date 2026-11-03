<?php
require_once __DIR__ . '/../config/database.php';

$pdo = getDBConnection();
$errors = [];

// Handle inline location creation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCSRF();

    $name = trim($_POST['name'] ?? '');
    $address = trim($_POST['address'] ?? '') ?: null;
    $description = trim($_POST['description'] ?? '') ?: null;

    if (empty($name)) {
        $errors[] = 'Location name is required.';
    } else {
        $chk = $pdo->prepare("SELECT id FROM locations WHERE name = ?");
        $chk->execute([$name]);
        if ($chk->fetch()) {
            $errors[] = 'Location with this name already exists.';
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("INSERT INTO locations (name, address, description) VALUES (?, ?, ?)");
        $stmt->execute([$name, $address, $description]);
        $_SESSION['flash_success'] = 'Location added successfully!';
        header('Location: /locations/index.php');
        exit;
    }
}

// Fetch locations with total assigned assets count
$sql = "SELECT l.*, COUNT(a.id) AS total_assets 
        FROM locations l 
        LEFT JOIN assets a ON l.id = a.location_id 
        GROUP BY l.id 
        ORDER BY l.name ASC";
$locations = $pdo->query($sql)->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row">
    <!-- Location Form -->
    <div class="col-md-4 mb-4">
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h5 class="card-title mb-0">Add Location</h5>
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

                <form method="POST" action="/locations/index.php">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                    <div class="mb-3">
                        <label class="form-label">Location Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required placeholder="e.g. Server Room 1">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address / Room</label>
                        <input type="text" name="address" class="form-control" placeholder="e.g. Building A, Floor 2">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Optional details..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary w-100"><i class="bi-plus-circle me-1"></i> Save Location</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Location Listing -->
    <div class="col-md-8">
        <div class="table-responsive p-3">
            <h5 class="mb-3">Locations Listing</h5>
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Location Name</th>
                        <th>Address</th>
                        <th>Assigned Assets</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($locations)): ?>
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">No locations created yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($locations as $loc): ?>
                            <tr>
                                <td><strong><?= sanitize($loc['name']); ?></strong></td>
                                <td><?= sanitize($loc['address'] ?? 'N/A'); ?></td>
                                <td>
                                    <span class="badge bg-secondary rounded-pill">
                                        <?= $loc['total_assets']; ?> Asset(s)
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a href="/locations/edit.php?id=<?= $loc['id']; ?>" class="btn btn-sm btn-warning me-1"><i class="bi-pencil"></i></a>
                                    <form method="POST" action="/locations/delete.php" class="d-inline" onsubmit="return confirm('Deleting this location will unassign its associated assets. Proceed?');">
                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                                        <input type="hidden" name="id" value="<?= $loc['id']; ?>">
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
