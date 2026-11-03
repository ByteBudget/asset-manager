<?php
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCSRF();
    
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    if ($id) {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("DELETE FROM assets WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['flash_success'] = 'Asset deleted successfully.';
    } else {
        $_SESSION['flash_error'] = 'Invalid asset identifier.';
    }
}

header('Location: /assets/index.php');
exit;
