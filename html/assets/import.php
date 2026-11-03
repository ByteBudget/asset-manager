<?php
require_once __DIR__ . '/../config/database.php';

$pdo = getDBConnection();
$errors = [];
$successCount = 0;
$skippedCount = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCSRF();

    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Please select a valid CSV file to upload.';
    } else {
        $fileTmpPath = $_FILES['csv_file']['tmp_name'];
        $fileName    = $_FILES['csv_file']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if ($fileExtension !== 'csv') {
            $errors[] = 'Invalid file type. Only standard .csv files are supported.';
        } else {
            $handle = fopen($fileTmpPath, 'r');
            if ($handle !== false) {
                // Read and skip BOM marker if present (Excel export compatibility)
                $bom = fread($handle, 3);
                if ($bom !== "\xEF\xBB\xBF") {
                    rewind($handle);
                }

                // Parse Header Row
                $header = fgetcsv($handle);
                if ($header === false) {
                    $errors[] = 'The uploaded CSV file is empty.';
                } else {
                    // Map headers lowercased/trimmed
                    $headerMap = array_map(function($h) {
                        return strtolower(trim($h));
                    }, $header);

                    // Validate minimal required columns
                    if (!in_array('Asset Tag', $headerMap) || !in_array('Asset Name', $headerMap)) {
                        $errors[] = 'Invalid CSV layout. Header must include "Asset Tag" and "Name".';
                    } else {
                        // Prepared lookup/insert helpers for entities
                        $getCatStmt = $pdo->prepare("SELECT id FROM categories WHERE name = ?");
                        $insCatStmt = $pdo->prepare("INSERT INTO categories (name) VALUES (?)");

                        $getLocStmt = $pdo->prepare("SELECT id FROM locations WHERE name = ?");
                        $insLocStmt = $pdo->prepare("INSERT INTO locations (name) VALUES (?)");

                        $getBrandStmt = $pdo->prepare("SELECT id FROM brands WHERE name = ?");
                        $insBrandStmt = $pdo->prepare("INSERT INTO brands (name) VALUES (?)");

                        $chkAssetStmt = $pdo->prepare("SELECT id FROM assets WHERE asset_tag = ?");
                        $insAssetStmt = $pdo->prepare("INSERT INTO assets (asset_tag, name, category_id, location_id, brand_id, serial_number, purchase_date, purchase_cost, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                        $rowNum = 1; // Tracks row count
                        while (($data = fgetcsv($handle)) !== false) {
                            $rowNum++;
                            if (count($data) < count($headerMap)) {
                                continue; // Skip malformed rows
                            }

                            $row = array_combine($headerMap, array_map('trim', $data));

                            $assetTag = $row['asset tag'] ?? $row['tag'] ?? '';
                            $name     = $row['name'] ?? '';

                            if (empty($name)) {
                                $skippedCount++;
                                continue;
                            }

                            // If Asset Tag is empty, generate sequential tag
                            if (empty($assetTag)) {
                                $assetTag = generateNextAssetTag('AST-', 5);
                            } else {
                                // Check for existing Asset Tag
                                $chkAssetStmt->execute([$assetTag]);
                                if ($chkAssetStmt->fetch()) {
                                    $skippedCount++; // Duplicate Tag found
                                    continue;
                                }
                            }

                            // 1. Resolve Category ID (auto-create if missing)
                            $categoryId = null;
                            $catName = $row['category'] ?? '';
                            if ($catName !== '' && strtolower($catName) !== 'uncategorized') {
                                $getCatStmt->execute([$catName]);
                                $cat = $getCatStmt->fetch();
                                if ($cat) {
                                    $categoryId = $cat['id'];
                                } else {
                                    $insCatStmt->execute([$catName]);
                                    $categoryId = $pdo->lastInsertId();
                                }
                            }

                            // 2. Resolve Location ID (auto-create if missing)
                            $locationId = null;
                            $locName = $row['location'] ?? '';
                            if ($locName !== '' && strtolower($locName) !== 'unassigned') {
                                $getLocStmt->execute([$locName]);
                                $loc = $getLocStmt->fetch();
                                if ($loc) {
                                    $locationId = $loc['id'];
                                } else {
                                    $insLocStmt->execute([$locName]);
                                    $locationId = $pdo->lastInsertId();
                                }
                            }

                            // 3. Resolve Brand ID (auto-create if missing)
                            $brandId = null;
                            $brandName = $row['brand'] ?? '';
                            if ($brandName !== '' && strtolower($brandName) !== 'unassigned') {
                                $getBrandStmt->execute([$brandName]);
                                $b = $getBrandStmt->fetch();
                                if ($b) {
                                    $brandId = $b['id'];
                                } else {
                                    $insBrandStmt->execute([$brandName]);
                                    $brandId = $pdo->lastInsertId();
                                }
                            }

                            // 4. Parse Remaining Optional Fields
                            $serialNumber = $row['serial number'] ?? $row['serial'] ?? null;
                            $serialNumber = ($serialNumber === 'N/A' || $serialNumber === '') ? null : $serialNumber;

                            $purchaseDate = $row['purchase date'] ?? null;
                            $purchaseDate = ($purchaseDate === 'N/A' || $purchaseDate === '') ? null : date('Y-m-d', strtotime($purchaseDate));

                            $costRaw = $row['purchase cost'] ?? $row['cost'] ?? null;
                            $purchaseCost = null;
                            if ($costRaw !== null && $costRaw !== 'N/A') {
                                $costClean = preg_replace('/[^0-9.]/', '', $costRaw);
                                $purchaseCost = is_numeric($costClean) ? (float)$costClean : null;
                            }

                            $status = $row['status'] ?? 'Available';
                            if (!in_array($status, ['Available', 'In Use', 'Under Maintenance', 'Retired'])) {
                                $status = 'Available';
                            }

                            $notes = $row['notes'] ?? null;

                            // Insert New Asset
                            $insAssetStmt->execute([
                                $assetTag, $name, $categoryId, $locationId, $brandId,
                                $serialNumber, $purchaseDate, $purchaseCost, $status, $notes
                            ]);

                            $successCount++;
                        }
                        fclose($handle);

                        $_SESSION['flash_success'] = "Import completed! Successfully imported {$successCount} assets. ({$skippedCount} skipped due to empty names or duplicate tags).";
                        header('Location: /assets/index.php');
                        exit;
                    }
                }
            } else {
                $errors[] = 'Unable to read the uploaded CSV file.';
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h4 class="card-title mb-0"><i class="bi-file-earmark-arrow-up me-2"></i>Import Assets from CSV</h4>
                <a href="/assets/index.php" class="btn btn-outline-secondary btn-sm">Back to Directory</a>
            </div>
            <div class="card-body">

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0 ps-3">
                            <?php foreach ($errors as $err): ?>
                                <li><?= sanitize($err); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <div class="alert alert-info">
                    <h6 class="alert-heading"><i class="bi-info-circle me-1"></i> File Guidelines</h6>
                    <p class="mb-1 small">Your CSV header row should contain the following column names:</p>
                    <code class="d-block bg-white p-2 border rounded mb-2">Asset Tag, Name, Category, Brand, Location, Status, Serial Number, Purchase Date, Purchase Cost, Notes</code>
                    <ul class="mb-0 small text-muted">
                        <li><strong>Categories, Brands & Locations:</strong> If a Category, Brand, or Location in the CSV does not exist in the database, it will be automatically created.</li>
                        <li><strong>Duplicate Tags:</strong> Rows with existing Asset Tags will be automatically skipped to prevent collisions.</li>
                    </ul>
                </div>

                <form method="POST" action="/assets/import.php" enctype="multipart/form-data" class="mt-4">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">

                    <div class="mb-4">
                        <label class="form-label font-weight-bold">Select CSV File</label>
                        <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="/assets/index.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary"><i class="bi-upload me-1"></i> Upload & Process CSV</button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
