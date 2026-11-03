<?php
require_once __DIR__ . '/../includes/header.php';

$pdo = getDBConnection();

// Dynamic Search & Filtering parameters
$search = trim($_GET['search'] ?? '');
$category_id = filter_input(INPUT_GET, 'category_id', FILTER_VALIDATE_INT);
$status = trim($_GET['status'] ?? '');

// Pagination Settings
$limit = 10;
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
$offset = ($page - 1) * $limit;

// Build Query Conditions Dynamically
$where = [];
$params = [];

if ($search !== '') {
    $where[] = "(a.asset_tag LIKE :search OR a.name LIKE :search OR a.serial_number LIKE :search)";
    $params['search'] = "%{$search}%";
}
if ($category_id) {
    $where[] = "a.category_id = :category_id";
    $params['category_id'] = $category_id;
}
if ($status !== '') {
    $where[] = "a.status = :status";
    $params['status'] = $status;
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Count Total Records
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM assets a $whereSQL");
$countStmt->execute($params);
$totalAssets = $countStmt->fetchColumn();
$totalPages = ceil($totalAssets / $limit);

// Fetch Assets Page Records
$sql = "SELECT a.*, c.name AS category_name 
        FROM assets a 
        LEFT JOIN categories c ON a.category_id = c.id 
        $whereSQL 
        ORDER BY a.id DESC 
        LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);
foreach ($params as $key => $val) {
    $stmt->bindValue(":$key", $val);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$assets = $stmt->fetchAll();

// Fetch Categories for Filter Dropdown
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi-box-seam me-2"></i>Asset Directory</h2>
    <a href="/assets/create.php" class="btn btn-primary"><i class="bi-plus-circle me-1"></i> Add New Asset</a>
</div>

<!-- Search and Filter Form -->
<div class="card mb-4 shadow-sm">
    <div class="card-body">
        <form method="GET" action="/assets/index.php" class="row g-3">
            <div class="col-md-4">
                <input type="text" name="search" class="form-control" placeholder="Search Tag, Name, or Serial..." value="<?= sanitize($search); ?>">
            </div>
            <div class="col-md-3">
                <select name="category_id" class="form-select">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id']; ?>" <?= $category_id == $cat['id'] ? 'selected' : ''; ?>>
                            <?= sanitize($cat['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    <?php foreach (['Available', 'In Use', 'Under Maintenance', 'Retired'] as $st): ?>
                        <option value="<?= $st; ?>" <?= $status === $st ? 'selected' : ''; ?>><?= $st; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-secondary w-100"><i class="bi-filter me-1"></i> Filter</button>
                <a href="/assets/index.php" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Assets Data Table -->
<div class="table-responsive p-3">
    <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
            <tr>
                <th>Tag</th>
                <th>Name</th>
                <th>Category</th>
                <th>Status</th>
                <th>Purchase Date</th>
                <th>Cost</th>
                <th class="text-end">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($assets)): ?>
                <tr>
                    <td colspan="7" class="text-center text-muted py-4">No assets found matching your criteria.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($assets as $asset): ?>
                    <tr>
                        <td><strong><?= sanitize($asset['asset_tag']); ?></strong></td>
                        <td><?= sanitize($asset['name']); ?></td>
                        <td><?= sanitize($asset['category_name'] ?? 'Uncategorized'); ?></td>
                        <td>
                            <?php
                            $badgeClass = match($asset['status']) {
                                'Available' => 'bg-success',
                                'In Use' => 'bg-primary',
                                'Under Maintenance' => 'bg-warning text-dark',
                                'Retired' => 'bg-danger',
                                default => 'bg-secondary'
                            };
                            ?>
                            <span class="badge <?= $badgeClass; ?>"><?= $asset['status']; ?></span>
                        </td>
                        <td><?= $asset['purchase_date'] ? date('M d, Y', strtotime($asset['purchase_date'])) : 'N/A'; ?></td>
                        <td><?= $asset['purchase_cost'] !== null ? '$' . number_format($asset['purchase_cost'], 2) : 'N/A'; ?></td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-info text-white me-1" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#viewModal<?= $asset['id']; ?>">
                                <i class="bi-eye"></i>
                            </button>
                            <a href="/assets/edit.php?id=<?= $asset['id']; ?>" class="btn btn-sm btn-warning me-1"><i class="bi-pencil"></i></a>
                            <form method="POST" action="/assets/delete.php" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this asset?');">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="id" value="<?= $asset['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-danger"><i class="bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>

                    <!-- Detail View Modal -->
                    <div class="modal fade" id="viewModal<?= $asset['id']; ?>" tabindex="-1">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Asset Breakdown: <?= sanitize($asset['asset_tag']); ?></h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <p><strong>Name:</strong> <?= sanitize($asset['name']); ?></p>
                                    <p><strong>Category:</strong> <?= sanitize($asset['category_name'] ?? 'Uncategorized'); ?></p>
                                    <p><strong>Serial Number:</strong> <?= sanitize($asset['serial_number'] ?? 'N/A'); ?></p>
                                    <p><strong>Status:</strong> <?= $asset['status']; ?></p>
                                    <p><strong>Purchase Date:</strong> <?= $asset['purchase_date'] ?? 'N/A'; ?></p>
                                    <p><strong>Purchase Cost:</strong> <?= $asset['purchase_cost'] ? '$' . number_format($asset['purchase_cost'], 2) : 'N/A'; ?></p>
                                    <p><strong>Notes:</strong><br><?= nl2br(sanitize($asset['notes'] ?? 'No notes available.')); ?></p>
                                    <hr>
                                    <small class="text-muted d-block">Created At: <?= $asset['created_at']; ?></small>
                                    <small class="text-muted d-block">Last Updated: <?= $asset['updated_at']; ?></small>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Pagination Links -->
<?php if ($totalPages > 1): ?>
    <nav class="mt-4">
        <ul class="pagination justify-content-center">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?= $i == $page ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?= $i; ?>&search=<?= urlencode($search); ?>&category_id=<?= $category_id; ?>&status=<?= urlencode($status); ?>">
                        <?= $i; ?>
                    </a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
