<?php

declare(strict_types=1);

/**
 * Email Marketing Platform Installer
 * 
 * Interactive web-based installer with environment setup,
 * database configuration, and initial user creation.
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Define paths
define('BASE_PATH', dirname(__DIR__));
define('INSTALL_LOCK_FILE', BASE_PATH . '/storage/.installed');

// Check if already installed
if (file_exists(INSTALL_LOCK_FILE)) {
    die('Installation already completed. Delete /storage/.installed to reinstall.');
}

// Ensure required directories exist
$requiredDirs = [
    'storage/logs',
    'storage/cache',
    'storage/uploads',
    'public/uploads'
];

foreach ($requiredDirs as $dir) {
    $fullPath = BASE_PATH . '/' . $dir;
    if (!is_dir($fullPath)) {
        mkdir($fullPath, 0755, true);
    }
}

// Handle form submission
$step = $_GET['step'] ?? 1;
$errors = [];
$success = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($step) {
        case 1:
            // Requirements check passed, go to step 2
            header('Location: ?step=2');
            exit;
            
        case 2:
            // Database configuration
            $errors = validateDatabaseConfig($_POST);
            if (empty($errors)) {
                saveDatabaseConfig($_POST);
                header('Location: ?step=3');
                exit;
            }
            break;
            
        case 3:
            // Email configuration
            $errors = validateEmailConfig($_POST);
            if (empty($errors)) {
                saveEmailConfig($_POST);
                header('Location: ?step=4');
                exit;
            }
            break;
            
        case 4:
            // Admin user creation
            $errors = validateAdminUser($_POST);
            if (empty($errors)) {
                $result = createAdminUser($_POST);
                if ($result['success']) {
                    header('Location: ?step=5');
                    exit;
                } else {
                    $errors[] = $result['error'];
                }
            }
            break;
            
        case 5:
            // Finalize installation
            finalizeInstallation();
            header('Location: ?step=6');
            exit;
    }
}

/**
 * Validate database configuration
 */
function validateDatabaseConfig(array $data): array
{
    $errors = [];
    
    if (empty($data['db_type'])) {
        $errors[] = 'Database type is required';
    }
    
    if ($data['db_type'] === 'mysql') {
        if (empty($data['db_host'])) $errors[] = 'Database host is required';
        if (empty($data['db_name'])) $errors[] = 'Database name is required';
        if (empty($data['db_user'])) $errors[] = 'Database username is required';
    }
    
    // Test database connection
    if (empty($errors)) {
        try {
            $dsn = buildDsn($data);
            $pdo = new PDO(
                $dsn,
                $data['db_user'] ?? '',
                $data['db_pass'] ?? '',
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } catch (PDOException $e) {
            $errors[] = 'Database connection failed: ' . $e->getMessage();
        }
    }
    
    return $errors;
}

/**
 * Validate email configuration
 */
function validateEmailConfig(array $data): array
{
    $errors = [];
    
    if (empty($data['mail_from_address']) || !filter_var($data['mail_from_address'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid from email address is required';
    }
    
    if (empty($data['mail_from_name'])) {
        $errors[] = 'From name is required';
    }
    
    if ($data['mail_driver'] === 'smtp') {
        if (empty($data['mail_host'])) $errors[] = 'SMTP host is required';
        if (empty($data['mail_port'])) $errors[] = 'SMTP port is required';
        if (empty($data['mail_username'])) $errors[] = 'SMTP username is required';
        if (empty($data['mail_password'])) $errors[] = 'SMTP password is required';
    }
    
    return $errors;
}

/**
 * Validate admin user data
 */
function validateAdminUser(array $data): array
{
    $errors = [];
    
    if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email address is required';
    }
    
    if (empty($data['password']) || strlen($data['password']) < 8) {
        $errors[] = 'Password must be at least 8 characters';
    }
    
    if ($data['password'] !== $data['password_confirm']) {
        $errors[] = 'Password confirmation does not match';
    }
    
    if (empty($data['first_name'])) {
        $errors[] = 'First name is required';
    }
    
    return $errors;
}

/**
 * Save database configuration to .env file
 */
function saveDatabaseConfig(array $data): void
{
    $envPath = BASE_PATH . '/.env';
    $envContent = file_exists($envPath) ? file_get_contents($envPath) : '';
    
    $dbConfig = [
        'DB_TYPE' => $data['db_type'],
        'DB_HOST' => $data['db_host'] ?? 'localhost',
        'DB_PORT' => $data['db_port'] ?? '3306',
        'DB_NAME' => $data['db_name'] ?? 'email_platform',
        'DB_USER' => $data['db_user'] ?? '',
        'DB_PASS' => $data['db_pass'] ?? '',
        'DB_PATH' => $data['db_path'] ?? BASE_PATH . '/storage/database.sqlite',
    ];
    
    foreach ($dbConfig as $key => $value) {
        $envContent = updateEnvValue($envContent, $key, $value);
    }
    
    file_put_contents($envPath, $envContent);
}

/**
 * Save email configuration to .env file
 */
function saveEmailConfig(array $data): void
{
    $envPath = BASE_PATH . '/.env';
    $envContent = file_get_contents($envPath);
    
    $emailConfig = [
        'MAIL_DRIVER' => $data['mail_driver'],
        'MAIL_HOST' => $data['mail_host'] ?? '',
        'MAIL_PORT' => $data['mail_port'] ?? '587',
        'MAIL_USERNAME' => $data['mail_username'] ?? '',
        'MAIL_PASSWORD' => $data['mail_password'] ?? '',
        'MAIL_ENCRYPTION' => $data['mail_encryption'] ?? 'tls',
        'MAIL_FROM_ADDRESS' => $data['mail_from_address'],
        'MAIL_FROM_NAME' => $data['mail_from_name'],
    ];
    
    foreach ($emailConfig as $key => $value) {
        $envContent = updateEnvValue($envContent, $key, $value);
    }
    
    file_put_contents($envPath, $envContent);
}

/**
 * Create admin user
 */
function createAdminUser(array $data): array
{
    try {
        // Load environment
        $envPath = BASE_PATH . '/.env';
        if (file_exists($envPath)) {
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
                    [$key, $value] = explode('=', $line, 2);
                    $_ENV[trim($key)] = trim($value);
                }
            }
        }
        
        // Create database connection
        $dsn = $_ENV['DB_TYPE'] === 'sqlite' ? 
            'sqlite:' . $_ENV['DB_PATH'] : 
            "{$_ENV['DB_TYPE']}:host={$_ENV['DB_HOST']};port={$_ENV['DB_PORT']};dbname={$_ENV['DB_NAME']}";
            
        $pdo = new PDO($dsn, $_ENV['DB_USER'] ?? '', $_ENV['DB_PASS'] ?? '');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Run migrations
        runMigrations($pdo);
        
        // Create admin user
        $uuid = generateUuid();
        $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("
            INSERT INTO users (uuid, email, password_hash, first_name, last_name, role, status, email_verified)
            VALUES (?, ?, ?, ?, ?, 'admin', 'active', 1)
        ");
        
        $stmt->execute([
            $uuid,
            $data['email'],
            $passwordHash,
            $data['first_name'],
            $data['last_name'] ?? ''
        ]);
        
        return ['success' => true];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Run database migrations
 */
function runMigrations(PDO $pdo): void
{
    $migrationsPath = BASE_PATH . '/migrations';
    $files = glob($migrationsPath . '/*.sql');
    sort($files);
    
    foreach ($files as $file) {
        $sql = file_get_contents($file);
        $pdo->exec($sql);
    }
}

/**
 * Finalize installation
 */
function finalizeInstallation(): void
{
    // Create installation lock file
    file_put_contents(INSTALL_LOCK_FILE, date('Y-m-d H:i:s'));
    
    // Set final environment variables
    $envPath = BASE_PATH . '/.env';
    $envContent = file_get_contents($envPath);
    
    $finalConfig = [
        'APP_KEY' => generateAppKey(),
        'JWT_SECRET' => generateAppKey(),
        'APP_DEBUG' => 'false',
    ];
    
    foreach ($finalConfig as $key => $value) {
        $envContent = updateEnvValue($envContent, $key, $value);
    }
    
    file_put_contents($envPath, $envContent);
}

/**
 * Helper functions
 */
function buildDsn(array $config): string
{
    return match ($config['db_type']) {
        'mysql' => "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']}",
        'sqlite' => 'sqlite:' . ($config['db_path'] ?? BASE_PATH . '/storage/database.sqlite'),
        default => throw new Exception('Unsupported database type')
    };
}

function updateEnvValue(string $content, string $key, string $value): string
{
    $pattern = "/^{$key}=.*$/m";
    $replacement = "{$key}={$value}";
    
    if (preg_match($pattern, $content)) {
        return preg_replace($pattern, $replacement, $content);
    } else {
        return $content . "\n{$replacement}";
    }
}

function generateUuid(): string
{
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

function generateAppKey(): string
{
    return base64_encode(random_bytes(32));
}

function checkRequirements(): array
{
    $requirements = [
        'PHP Version >= 8.0' => version_compare(PHP_VERSION, '8.0.0', '>='),
        'PDO Extension' => extension_loaded('pdo'),
        'JSON Extension' => extension_loaded('json'),
        'MBString Extension' => extension_loaded('mbstring'),
        'OpenSSL Extension' => extension_loaded('openssl'),
        'Curl Extension' => extension_loaded('curl'),
        'Storage Directory Writable' => is_writable(BASE_PATH . '/storage'),
        'Public Directory Writable' => is_writable(BASE_PATH . '/public'),
    ];
    
    return $requirements;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Marketing Platform - Installation</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .installer { 
            background: white; 
            border-radius: 1rem; 
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            max-width: 600px; 
            width: 100%;
            overflow: hidden;
        }
        .header { 
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white; 
            padding: 2rem; 
            text-align: center; 
        }
        .header h1 { font-size: 1.75rem; margin-bottom: 0.5rem; }
        .header p { opacity: 0.9; }
        .content { padding: 2rem; }
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 2rem;
        }
        .step {
            width: 2rem;
            height: 2rem;
            border-radius: 50%;
            background: #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 0.5rem;
            font-weight: 600;
            font-size: 0.875rem;
        }
        .step.active { background: #3b82f6; color: white; }
        .step.completed { background: #10b981; color: white; }
        .form-group { margin-bottom: 1.5rem; }
        .form-label { 
            display: block; 
            margin-bottom: 0.5rem; 
            font-weight: 500; 
            color: #374151; 
        }
        .form-input, .form-select { 
            width: 100%; 
            padding: 0.75rem; 
            border: 1px solid #d1d5db; 
            border-radius: 0.5rem; 
            font-size: 0.875rem;
            transition: border-color 0.15s;
        }
        .form-input:focus, .form-select:focus { 
            outline: none; 
            border-color: #3b82f6; 
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        .btn { 
            padding: 0.75rem 1.5rem; 
            border: none; 
            border-radius: 0.5rem; 
            font-weight: 500; 
            cursor: pointer; 
            text-decoration: none;
            display: inline-block;
            transition: all 0.15s;
        }
        .btn-primary { 
            background: #3b82f6; 
            color: white; 
        }
        .btn-primary:hover { 
            background: #2563eb; 
            transform: translateY(-1px);
        }
        .btn-secondary { 
            background: #6b7280; 
            color: white; 
        }
        .alert { 
            padding: 1rem; 
            border-radius: 0.5rem; 
            margin-bottom: 1rem; 
        }
        .alert-error { 
            background: #fef2f2; 
            border: 1px solid #fecaca; 
            color: #dc2626; 
        }
        .alert-success { 
            background: #ecfdf5; 
            border: 1px solid #a7f3d0; 
            color: #065f46; 
        }
        .requirements-list {
            list-style: none;
        }
        .requirements-list li {
            padding: 0.5rem 0;
            display: flex;
            justify-content: space-between;
            border-bottom: 1px solid #f3f4f6;
        }
        .status {
            font-weight: 600;
        }
        .status.pass { color: #10b981; }
        .status.fail { color: #ef4444; }
        .text-center { text-align: center; }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
    </style>
</head>
<body>
    <div class="installer">
        <div class="header">
            <h1>📧 Email Marketing Platform</h1>
            <p>Professional email marketing solution installation</p>
        </div>
        
        <div class="content">
            <!-- Step Indicator -->
            <div class="step-indicator">
                <?php for ($i = 1; $i <= 6; $i++): ?>
                    <div class="step <?php echo $i < $step ? 'completed' : ($i == $step ? 'active' : ''); ?>">
                        <?php echo $i; ?>
                    </div>
                <?php endfor; ?>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <?php foreach ($errors as $error): ?>
                        <div><?php echo htmlspecialchars($error); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php switch ($step): case 1: ?>
                <!-- Step 1: Requirements Check -->
                <h2>System Requirements</h2>
                <p>Please ensure your system meets the following requirements:</p>
                
                <ul class="requirements-list">
                    <?php foreach (checkRequirements() as $requirement => $status): ?>
                        <li>
                            <span><?php echo $requirement; ?></span>
                            <span class="status <?php echo $status ? 'pass' : 'fail'; ?>">
                                <?php echo $status ? '✓ Pass' : '✗ Fail'; ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                
                <div class="text-center" style="margin-top: 2rem;">
                    <?php if (array_product(checkRequirements())): ?>
                        <form method="post">
                            <button type="submit" class="btn btn-primary">Continue to Database Setup</button>
                        </form>
                    <?php else: ?>
                        <p style="color: #dc2626;">Please fix the failed requirements before continuing.</p>
                    <?php endif; ?>
                </div>
                
            <?php break; case 2: ?>
                <!-- Step 2: Database Configuration -->
                <h2>Database Configuration</h2>
                <p>Configure your database connection settings:</p>
                
                <form method="post">
                    <div class="form-group">
                        <label class="form-label">Database Type</label>
                        <select name="db_type" class="form-select" required>
                            <option value="">Select database type</option>
                            <option value="sqlite" <?php echo ($_POST['db_type'] ?? '') === 'sqlite' ? 'selected' : ''; ?>>SQLite (Recommended for testing)</option>
                            <option value="mysql" <?php echo ($_POST['db_type'] ?? '') === 'mysql' ? 'selected' : ''; ?>>MySQL/MariaDB</option>
                        </select>
                    </div>
                    
                    <div id="mysql-config" style="display: none;">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Host</label>
                                <input type="text" name="db_host" class="form-input" value="<?php echo htmlspecialchars($_POST['db_host'] ?? 'localhost'); ?>" placeholder="localhost">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Port</label>
                                <input type="number" name="db_port" class="form-input" value="<?php echo htmlspecialchars($_POST['db_port'] ?? '3306'); ?>" placeholder="3306">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Database Name</label>
                            <input type="text" name="db_name" class="form-input" value="<?php echo htmlspecialchars($_POST['db_name'] ?? 'email_platform'); ?>" placeholder="email_platform">
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Username</label>
                                <input type="text" name="db_user" class="form-input" value="<?php echo htmlspecialchars($_POST['db_user'] ?? ''); ?>" placeholder="database username">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Password</label>
                                <input type="password" name="db_pass" class="form-input" placeholder="database password">
                            </div>
                        </div>
                    </div>
                    
                    <div id="sqlite-config" style="display: none;">
                        <div class="form-group">
                            <label class="form-label">Database File Path</label>
                            <input type="text" name="db_path" class="form-input" value="<?php echo htmlspecialchars($_POST['db_path'] ?? BASE_PATH . '/storage/database.sqlite'); ?>" placeholder="<?php echo BASE_PATH; ?>/storage/database.sqlite">
                        </div>
                    </div>
                    
                    <div class="text-center">
                        <button type="submit" class="btn btn-primary">Test Connection & Continue</button>
                    </div>
                </form>
                
            <?php break; case 3: ?>
                <!-- Step 3: Email Configuration -->
                <h2>Email Configuration</h2>
                <p>Configure your email sending settings:</p>
                
                <form method="post">
                    <div class="form-group">
                        <label class="form-label">Mail Driver</label>
                        <select name="mail_driver" class="form-select" required>
                            <option value="smtp" <?php echo ($_POST['mail_driver'] ?? 'smtp') === 'smtp' ? 'selected' : ''; ?>>SMTP</option>
                            <option value="sendmail" <?php echo ($_POST['mail_driver'] ?? '') === 'sendmail' ? 'selected' : ''; ?>>Sendmail</option>
                        </select>
                    </div>
                    
                    <div id="smtp-config">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">SMTP Host</label>
                                <input type="text" name="mail_host" class="form-input" value="<?php echo htmlspecialchars($_POST['mail_host'] ?? 'smtp.mailtrap.io'); ?>" placeholder="smtp.mailtrap.io">
                            </div>
                            <div class="form-group">
                                <label class="form-label">SMTP Port</label>
                                <input type="number" name="mail_port" class="form-input" value="<?php echo htmlspecialchars($_POST['mail_port'] ?? '2525'); ?>" placeholder="587">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">SMTP Username</label>
                                <input type="text" name="mail_username" class="form-input" value="<?php echo htmlspecialchars($_POST['mail_username'] ?? ''); ?>" placeholder="your-smtp-username">
                            </div>
                            <div class="form-group">
                                <label class="form-label">SMTP Password</label>
                                <input type="password" name="mail_password" class="form-input" placeholder="your-smtp-password">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Encryption</label>
                            <select name="mail_encryption" class="form-select">
                                <option value="tls" <?php echo ($_POST['mail_encryption'] ?? 'tls') === 'tls' ? 'selected' : ''; ?>>TLS</option>
                                <option value="ssl" <?php echo ($_POST['mail_encryption'] ?? '') === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                <option value="" <?php echo ($_POST['mail_encryption'] ?? '') === '' ? 'selected' : ''; ?>>None</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">From Email Address</label>
                            <input type="email" name="mail_from_address" class="form-input" value="<?php echo htmlspecialchars($_POST['mail_from_address'] ?? 'noreply@example.com'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">From Name</label>
                            <input type="text" name="mail_from_name" class="form-input" value="<?php echo htmlspecialchars($_POST['mail_from_name'] ?? 'Email Platform'); ?>" required>
                        </div>
                    </div>
                    
                    <div class="text-center">
                        <button type="submit" class="btn btn-primary">Continue to Admin Setup</button>
                    </div>
                </form>
                
            <?php break; case 4: ?>
                <!-- Step 4: Admin User -->
                <h2>Create Admin User</h2>
                <p>Create your administrator account:</p>
                
                <form method="post">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">First Name</label>
                            <input type="text" name="first_name" class="form-input" value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Last Name</label>
                            <input type="text" name="last_name" class="form-input" value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-input" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-input" required minlength="8">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Confirm Password</label>
                            <input type="password" name="password_confirm" class="form-input" required>
                        </div>
                    </div>
                    
                    <div class="text-center">
                        <button type="submit" class="btn btn-primary">Create Admin & Finalize</button>
                    </div>
                </form>
                
            <?php break; case 5: ?>
                <!-- Step 5: Finalizing -->
                <div class="text-center">
                    <h2>Finalizing Installation...</h2>
                    <div style="margin: 2rem 0;">
                        <div class="loading" style="margin: 0 auto;"></div>
                    </div>
                    <p>Setting up final configuration and securing the installation.</p>
                </div>
                
                <script>
                    setTimeout(() => {
                        window.location.href = '?step=6';
                    }, 2000);
                </script>
                
            <?php break; case 6: ?>
                <!-- Step 6: Complete -->
                <div class="alert alert-success">
                    <h2>🎉 Installation Complete!</h2>
                    <p>Your Email Marketing Platform has been successfully installed and configured.</p>
                </div>
                
                <div style="margin: 2rem 0;">
                    <h3>Next Steps:</h3>
                    <ol style="margin-left: 2rem; line-height: 1.8;">
                        <li>Access your dashboard at <a href="../" target="_blank">../dashboard</a></li>
                        <li>Login with the admin credentials you created</li>
                        <li>Configure your SMTP servers for better deliverability</li>
                        <li>Import your subscriber lists</li>
                        <li>Create your first email campaign</li>
                    </ol>
                </div>
                
                <div class="text-center">
                    <a href="../" class="btn btn-primary">Access Dashboard</a>
                </div>
                
            <?php endswitch; ?>
        </div>
    </div>

    <script>
        // Show/hide database configuration based on type
        const dbTypeSelect = document.querySelector('select[name="db_type"]');
        if (dbTypeSelect) {
            dbTypeSelect.addEventListener('change', function() {
                const mysqlConfig = document.getElementById('mysql-config');
                const sqliteConfig = document.getElementById('sqlite-config');
                
                if (this.value === 'mysql') {
                    mysqlConfig.style.display = 'block';
                    sqliteConfig.style.display = 'none';
                } else if (this.value === 'sqlite') {
                    mysqlConfig.style.display = 'none';
                    sqliteConfig.style.display = 'block';
                } else {
                    mysqlConfig.style.display = 'none';
                    sqliteConfig.style.display = 'none';
                }
            });
            
            // Trigger change event on page load
            dbTypeSelect.dispatchEvent(new Event('change'));
        }

        // Show/hide SMTP configuration
        const mailDriverSelect = document.querySelector('select[name="mail_driver"]');
        if (mailDriverSelect) {
            mailDriverSelect.addEventListener('change', function() {
                const smtpConfig = document.getElementById('smtp-config');
                smtpConfig.style.display = this.value === 'smtp' ? 'block' : 'none';
            });
        }
    </script>
</body>
</html>