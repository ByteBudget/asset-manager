<?php
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCSRF();
    
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    if ($id) {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("DELETE FROM brands WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['flash_success'] = 'Brand deleted successfully. Associated assets are now unassigned.';
    } else {
        $_SESSION['flash_error'] = 'Invalid brand ID.';
    }
}

header('Location: /brands/index.php');
exit;
