<?php
require_once __DIR__ . '/../config/database.php';

$pdo = getDBConnection();

// Extract active filter parameters from GET request
$search      = trim($_GET['search'] ?? '');
$category_id = filter_input(INPUT_GET, 'category_id', FILTER_VALIDATE_INT);
$location_id = filter_input(INPUT_GET, 'location_id', FILTER_VALIDATE_INT);
$brand_id    = filter_input(INPUT_GET, 'brand_id', FILTER_VALIDATE_INT);
$status      = trim($_GET['status'] ?? '');

// Build dynamic WHERE clause
$where  = [];
$params = [];

if ($search !== '') {
    $where[] = "(a.asset_tag LIKE :search OR a.name LIKE :search OR a.serial_number LIKE :search)";
    $params['search'] = "%{$search}%";
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

// Fetch all records matching the filter criteria
$sql = "SELECT a.asset_tag, a.name AS asset_name, 
               c.name AS category_name, b.name AS brand_name, l.name AS location_name, 
               a.serial_number, a.status, a.purchase_date, a.purchase_cost, a.notes, 
               a.created_at, a.updated_at
        FROM assets a 
        LEFT JOIN categories c ON a.category_id = c.id 
        LEFT JOIN locations l ON a.location_id = l.id 
        LEFT JOIN brands b ON a.brand_id = b.id 
        $whereSQL 
        ORDER BY a.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$assets = $stmt->fetchAll();

// Set HTTP response headers for CSV file download
$filename = 'asset_export_' . date('Y-m-d_H-i-s') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Open PHP output stream
$output = fopen('php://output', 'w');

// Output UTF-8 BOM for proper Excel character encoding compatibility
fputs($output, "\xEF\xBB\xBF");

// Write CSV Header Row
fputcsv($output, [
    'Asset Tag',
    'Asset Name',
    'Category',
    'Brand',
    'Location',
    'Serial Number',
    'Status',
    'Purchase Date',
    'Purchase Cost ($)',
    'Notes',
    'Created At',
    'Updated At'
]);

// Write Data Rows
foreach ($assets as $row) {
    fputcsv($output, [
        $row['asset_tag'],
        $row['asset_name'],
        $row['category_name'] ?? 'Uncategorized',
        $row['brand_name'] ?? 'Unassigned',
        $row['location_name'] ?? 'Unassigned',
        $row['serial_number'] ?? '',
        $row['status'],
        $row['purchase_date'] ?? '',
        $row['purchase_cost'] !== null ? number_format((float)$row['purchase_cost'], 2, '.', '') : '',
        $row['notes'] ?? '',
        $row['created_at'],
        $row['updated_at']
    ]);
}

fclose($output);
exit;
