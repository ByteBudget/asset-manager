<?php
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCSRF();
    
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    if ($id) {
        $pdo = getDBConnection();
        // foreign key constraint handles setting category_id to NULL on child assets automatically
        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['flash_success'] = 'Category deleted successfully. Associated assets are now uncategorized.';
    } else {
        $_SESSION['flash_error'] = 'Invalid category ID.';
    }
}

header('Location: /categories/index.php');
exit;
