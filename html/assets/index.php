<?php
require_once __DIR__ . '/../includes/header.php';

$pdo = getDBConnection();

// 1. Search & Filtering Parameters
$search      = trim($_GET['search'] ?? '');
$category_id = filter_input(INPUT_GET, 'category_id', FILTER_VALIDATE_INT);
$location_id = filter_input(INPUT_GET, 'location_id', FILTER_VALIDATE_INT);
$brand_id    = filter_input(INPUT_GET, 'brand_id', FILTER_VALIDATE_INT);
$status      = trim($_GET['status'] ?? '');

// 2. Pagination Settings
$limit  = 10;
$page   = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
if ($page < 1) { $page = 1; }
$offset = ($page - 1) * $limit;

// 3. Dynamic Query Conditions (Using unique search parameters)
$where  = [];
$params = [];

if ($search !== '') {
    $where[] = "(a.asset_tag LIKE :search_tag OR a.name LIKE :search_name OR a.serial_number LIKE :search_serial)";
    $params['search_tag']    = "%{$search}%";
    $params['search_name']   = "%{$search}%";
    $params['search_serial'] = "%{$search}%";
}
if ($category_id) {
    $where[] = "a.category_id = :category_id";
    $params['category_id'] = $category_id;
}
if ($location_id) {
    $where[] = "a.location_id = :location_id";
    $params['location_id'] = $location_id;
}
if ($brand_id) {
    $where[] = "a.brand_id = :brand_id";
    $params['brand_id'] = $brand_id;
}
if ($status !== '') {
    $where[] = "a.status = :status";
    $params['status'] = $status;
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// 4. Count Filtered Records
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM assets a $whereSQL");
$countStmt->execute($params);
$totalAssets = (int)$countStmt->fetchColumn();
$totalPages  = $totalAssets > 0 ? (int)ceil($totalAssets / $limit) : 1;

// 5. Fetch Assets
$sql = "SELECT a.*, 
               c.name AS category_name, 
               l.name AS location_name, 
               b.name AS brand_name 
        FROM assets a 
        LEFT JOIN categories c ON a.category_id = c.id 
        LEFT JOIN locations l ON a.location_id = l.id 
        LEFT JOIN brands b ON a.brand_id = b.id 
        $whereSQL 
        ORDER BY a.id DESC 
        LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);

foreach ($params as $key => $val) {
    $stmt->bindValue(":$key", $val);
}
$stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);

$stmt->execute();
$assets = $stmt->fetchAll();

// 6. Fetch Dropdowns Safely
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
$locations  = $pdo->query("SELECT * FROM locations ORDER BY name ASC")->fetchAll();

try {
    $brands = $pdo->query("SELECT * FROM brands ORDER BY name ASC")->fetchAll();
} catch (PDOException $e) {
    $brands = [];
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi-box-seam me-2"></i>Asset Directory</h2>
    <div class="d-flex gap-2">
        <a href="/assets/export.php?<?= http_build_query($_GET); ?>" class="btn btn-outline-success">
            <i class="bi-file-earmark-spreadsheet me-1"></i> Export CSV
        </a>
      <!-- Import Button -->
        <a href="/assets/import.php" class="btn btn-outline-primary">
            <i class="bi-file-earmark-arrow-up me-1"></i> Import CSV
        </a>
        <a href="/assets/create.php" class="btn btn-primary">
            <i class="bi-plus-circle me-1"></i> Add New Asset
        </a>
    </div>
</div>

<!-- Search and Filter Bar -->
<div class="card mb-4 shadow-sm">
    <div class="card-body">
        <form method="GET" action="/assets/index.php" class="row g-3">
            <div class="col-md-3">
                <input type="text" name="search" class="form-control" placeholder="Search Tag, Name, Serial..." value="<?= sanitize($search); ?>">
            </div>
            <div class="col-md-2">
                <select name="category_id" class="form-select">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id']; ?>" <?= $category_id == $cat['id'] ? 'selected' : ''; ?>>
                            <?= sanitize($cat['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="brand_id" class="form-select">
                    <option value="">All Brands</option>
                    <?php foreach ($brands as $b): ?>
                        <option value="<?= $b['id']; ?>" <?= $brand_id == $b['id'] ? 'selected' : ''; ?>>
                            <?= sanitize($b['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="location_id" class="form-select">
                    <option value="">All Locations</option>
                    <?php foreach ($locations as $loc): ?>
                        <option value="<?= $loc['id']; ?>" <?= $location_id == $loc['id'] ? 'selected' : ''; ?>>
                            <?= sanitize($loc['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    <?php foreach (['Available', 'In Use', 'Under Maintenance', 'Retired'] as $st): ?>
                        <option value="<?= $st; ?>" <?= $status === $st ? 'selected' : ''; ?>><?= $st; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-1 d-flex gap-1">
                <button type="submit" class="btn btn-secondary w-100" title="Filter"><i class="bi-filter"></i></button>
                <a href="/assets/index.php" class="btn btn-outline-secondary" title="Reset"><i class="bi-arrow-counterclockwise"></i></a>
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
                <th>Brand</th>
                <th>Location</th>
                <th>Status</th>
                <th>Cost</th>
                <th class="text-end">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($assets)): ?>
                <tr>
                    <td colspan="8" class="text-center text-muted py-4">No assets found matching your criteria.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($assets as $asset): ?>
                    <tr>
                        <td><strong><?= sanitize($asset['asset_tag']); ?></strong></td>
                        <td><?= sanitize($asset['name']); ?></td>
                        <td><?= sanitize($asset['category_name'] ?? 'Uncategorized'); ?></td>
                        <td><?= sanitize($asset['brand_name'] ?? 'Unassigned'); ?></td>
                        <td><?= sanitize($asset['location_name'] ?? 'Unassigned'); ?></td>
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
                        <td><?= $asset['purchase_cost'] !== null ? '$' . number_format((float)$asset['purchase_cost'], 2) : 'N/A'; ?></td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-info text-white me-1" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#viewModal<?= $asset['id']; ?>" title="View Details">
                                <i class="bi-eye"></i>
                            </button>
                            <a href="/assets/create.php?clone_id=<?= $asset['id']; ?>" class="btn btn-sm btn-secondary me-1" title="Clone Asset">
                                <i class="bi-copy"></i>
                            </a>
                            <a href="/assets/edit.php?id=<?= $asset['id']; ?>" class="btn btn-sm btn-warning me-1" title="Edit Asset">
                                <i class="bi-pencil"></i>
                            </a>
                            <form method="POST" action="/assets/delete.php" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this asset?');">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="id" value="<?= $asset['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-danger" title="Delete Asset"><i class="bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Data Summary & Pagination Controls -->
<div class="d-flex justify-content-between align-items-center mt-3 px-2">
    <div class="text-muted small">
        Showing <?= min($offset + 1, $totalAssets); ?> to <?= min($offset + $limit, $totalAssets); ?> of <?= $totalAssets; ?> total assets
    </div>
    
    <?php if ($totalPages > 1): ?>
        <nav>
            <ul class="pagination mb-0">
                <!-- Previous Page Button -->
                <li class="page-item <?= ($page <= 1) ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?= $page - 1; ?>&search=<?= urlencode($search); ?>&category_id=<?= $category_id; ?>&brand_id=<?= $brand_id; ?>&location_id=<?= $location_id; ?>&status=<?= urlencode($status); ?>">
                        &laquo; Previous
                    </a>
                </li>

                <!-- Numerical Page Buttons -->
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?= ($i == $page) ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?= $i; ?>&search=<?= urlencode($search); ?>&category_id=<?= $category_id; ?>&brand_id=<?= $brand_id; ?>&location_id=<?= $location_id; ?>&status=<?= urlencode($status); ?>">
                            <?= $i; ?>
                        </a>
                    </li>
                <?php endfor; ?>

                <!-- Next Page Button -->
                <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?= $page + 1; ?>&search=<?= urlencode($search); ?>&category_id=<?= $category_id; ?>&brand_id=<?= $brand_id; ?>&location_id=<?= $location_id; ?>&status=<?= urlencode($status); ?>">
                        Next &raquo;
                    </a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

// <?php require_once __DIR__ . '/../includes/footer.php'; ?>

