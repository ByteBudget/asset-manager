<?php
session_start();

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db_host = trim($_POST['db_host'] ?? '127.0.0.1');
    $db_name = trim($_POST['db_name'] ?? 'asset_management');
    $db_user = trim($_POST['db_user'] ?? 'root');
    $db_pass = $_POST['db_pass'] ?? '';

    // 1. Basic Input Validation
    if (empty($db_host)) { $errors[] = "Database Host is required."; }
    if (empty($db_name)) { $errors[] = "Database Name is required."; }
    if (empty($db_user)) { $errors[] = "Database Username is required."; }

    // 2. Check if schema.sql exists
    $schemaFile = __DIR__ . '/schema.sql';
    if (!file_exists($schemaFile)) {
        $errors[] = "The schema.sql file was not found in the root directory.";
    }

    if (empty($errors)) {
        try {
            // 3. Connect to MySQL Server (without selecting DB first)
            $dsn = "mysql:host={$db_host};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $pdo = new PDO($dsn, $db_user, $db_pass, $options);

            // 4. Create Database if missing & select it
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . str_replace("`", "``", $db_name) . "` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
            $pdo->exec("USE `" . str_replace("`", "``", $db_name) . "`;");

            // 5. Read & Execute SQL Schema Commands
            $sqlContent = file_get_contents($schemaFile);
            
            // Execute raw schema query string
            $pdo->exec($sqlContent);

            // 6. Write out config/database.php automatically
            $configDir = __DIR__ . '/config';
            if (!is_dir($configDir)) {
                mkdir($configDir, 0755, true);
            }

            $configCode = "<?php\n";
            $configCode .= "// Display errors for development mode\n";
            $configCode .= "ini_set('display_errors', 1);\n";
            $configCode .= "ini_set('display_startup_errors', 1);\n";
            $configCode .= "error_reporting(E_ALL);\n\n";
            $configCode .= "if (session_status() === PHP_SESSION_NONE) {\n";
            $configCode .= "    session_start();\n";
            $configCode .= "}\n\n";
            $configCode .= "if (empty(\$_SESSION['csrf_token'])) {\n";
            $configCode .= "    \$_SESSION['csrf_token'] = bin2hex(random_bytes(32));\n";
            $configCode .= "}\n\n";
            $configCode .= "define('DB_HOST', getenv('DB_HOST') ?: '" . addslashes($db_host) . "');\n";
            $configCode .= "define('DB_NAME', getenv('DB_NAME') ?: '" . addslashes($db_name) . "');\n";
            $configCode .= "define('DB_USER', getenv('DB_USER') ?: '" . addslashes($db_user) . "');\n";
            $configCode .= "define('DB_PASS', getenv('DB_PASS') ?: '" . addslashes($db_pass) . "');\n";
            $configCode .= "define('DB_CHARSET', 'utf8mb4');\n\n";
            $configCode .= "function getDBConnection(): PDO {\n";
            $configCode .= "    static \$pdo = null;\n";
            $configCode .= "    if (\$pdo === null) {\n";
            $configCode .= "        \$dsn = \"mysql:host=\" . DB_HOST . \";dbname=\" . DB_NAME . \";charset=\" . DB_CHARSET;\n";
            $configCode .= "        \$options = [\n";
            $configCode .= "            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,\n";
            $configCode .= "            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,\n";
            $configCode .= "            PDO::ATTR_EMULATE_PREPARES   => false,\n";
            $configCode .= "        ];\n";
            $configCode .= "        try {\n";
            $configCode .= "            \$pdo = new PDO(\$dsn, DB_USER, DB_PASS, \$options);\n";
            $configCode .= "        } catch (PDOException \$e) {\n";
            $configCode .= "            die(\"Database Connection Error: \" . htmlspecialchars(\$e->getMessage()));\n";
            $configCode .= "        }\n";
            $configCode .= "    }\n";
            $configCode .= "    return \$pdo;\n";
            $configCode .= "}\n\n";
            $configCode .= "function generateNextAssetTag(string \$prefix = 'AST-', int \$padding = 5): string {\n";
            $configCode .= "    \$pdo = getDBConnection();\n";
            $configCode .= "    \$stmt = \$pdo->query(\"SELECT asset_tag FROM assets WHERE asset_tag LIKE '{\$prefix}%' ORDER BY id DESC LIMIT 1\");\n";
            $configCode .= "    \$lastTag = \$stmt->fetchColumn();\n";
            $configCode .= "    \$number = \$lastTag ? ((int) str_replace(\$prefix, '', \$lastTag)) + 1 : 1;\n";
            $configCode .= "    return \$prefix . str_pad((string)\$number, \$padding, '0', STR_PAD_LEFT);\n";
            $configCode .= "}\n\n";
            $configCode .= "function verifyCSRF(): void {\n";
            $configCode .= "    if (\$_SERVER['REQUEST_METHOD'] === 'POST') {\n";
            $configCode .= "        if (!isset(\$_POST['csrf_token']) || !hash_equals(\$_SESSION['csrf_token'], \$_POST['csrf_token'])) {\n";
            $configCode .= "            die('Invalid CSRF token submission.');\n";
            $configCode .= "        }\n";
            $configCode .= "    }\n";
            $configCode .= "}\n\n";
            $configCode .= "function sanitize(?string \$string): string {\n";
            $configCode .= "    return htmlspecialchars(\$string ?? '', ENT_QUOTES, 'UTF-8');\n";
            $configCode .= "}\n";

            file_put_contents($configDir . '/database.php', $configCode);
            $success = true;

        } catch (PDOException $e) {
            $errors[] = "Database Error: " . $e->getMessage();
        } catch (Exception $e) {
            $errors[] = "Installation Error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asset Manager - Database Setup</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-dark text-white p-3 text-center">
                    <h4 class="mb-0"><i class="bi-database-gear me-2"></i>Database Setup Wizard</h4>
                </div>
                <div class="card-body p-4">

                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <h5 class="alert-heading"><i class="bi-check-circle-fill me-2"></i>Installation Complete!</h5>
                            <p class="mb-0">The database schema was executed successfully and <code>config/database.php</code> has been generated.</p>
                        </div>
                        <div class="d-grid mt-4">
                            <a href="/assets/index.php" class="btn btn-primary btn-lg">Launch Application &rarr;</a>
                        </div>
                    <?php else: ?>

                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0 ps-3">
                                    <?php foreach ($errors as $err): ?>
                                        <li><?= htmlspecialchars($err); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="install.php">
                            <div class="mb-3">
                                <label class="form-label">Database Host</label>
                                <input type="text" name="db_host" class="form-control" value="<?= htmlspecialchars($_POST['db_host'] ?? '127.0.0.1'); ?>" required>
                                <small class="text-muted">Typically <code>127.0.0.1</code> or <code>localhost</code>.</small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Database Name</label>
                                <input type="text" name="db_name" class="form-control" value="<?= htmlspecialchars($_POST['db_name'] ?? 'asset_management'); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Database Username</label>
                                <input type="text" name="db_user" class="form-control" value="<?= htmlspecialchars($_POST['db_user'] ?? 'root'); ?>" required>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Database Password</label>
                                <input type="password" name="db_pass" class="form-control" value="<?= htmlspecialchars($_POST['db_pass'] ?? ''); ?>" placeholder="(leave blank if none)">
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="bi-play-fill me-1"></i> Run Setup & Install Database
                                </button>
                            </div>
                        </form>

                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
