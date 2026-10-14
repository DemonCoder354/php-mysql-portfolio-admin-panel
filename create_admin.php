<?php
// Create an administrator or change their password from the command line:
//   php create_admin.php <username> <password>
require __DIR__ . '/includes/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Run this script from the command line only.');
}

[$script, $username, $password] = array_pad($argv, 3, null);

if (!$username || !$password) {
    fwrite(STDERR, "Usage: php create_admin.php <username> <password>\n");
    exit(1);
}
if (strlen($password) < 8) {
    fwrite(STDERR, "Password must be at least 8 characters long.\n");
    exit(1);
}

$stmt = db()->prepare(
    'INSERT INTO admins (username, password_hash) VALUES (?, ?)
     ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash)'
);
$stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);

echo "Done: administrator \"{$username}\" saved.\n";
