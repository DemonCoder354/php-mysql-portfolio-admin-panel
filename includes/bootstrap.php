<?php
declare(strict_types=1);

// config.php holds local credentials and is not in Git; fall back to the example defaults.
$config = require (is_file(__DIR__ . '/../config.php') ? __DIR__ . '/../config.php' : __DIR__ . '/../config.example.php');

ini_set('display_errors', $config['debug'] ? '1' : '0');
error_reporting(E_ALL);

if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']),
    ]);
    session_start();
}

/** Shared PDO connection to MySQL. */
function db(): PDO
{
    static $pdo = null;
    global $config;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $config['db_host'], $config['db_port'], $config['db_name']
        );
        try {
            $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $ex) {
            http_response_code(500);
            $msg = $config['debug'] ? $ex->getMessage() : 'Could not connect to the database.';
            exit('<h1>Database unavailable</h1><p>' . htmlspecialchars($msg) . '</p>');
        }
    }
    return $pdo;
}

/** Escape a value for HTML output. */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** Allow only http(s) links so javascript: and similar schemes never get through. */
function safe_url(?string $url): string
{
    $url = trim((string) $url);
    return preg_match('~^https?://~i', $url) ? $url : '';
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

function csrf_check(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(419);
        exit('Your session has expired. Go back and refresh the page.');
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $items;
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function require_admin(): void
{
    if (empty($_SESSION['admin_id'])) {
        redirect('login.php');
    }
}

function get_profile(): array
{
    return db()->query('SELECT * FROM profile WHERE id = 1')->fetch() ?: [
        'full_name' => 'Your Name', 'headline' => '', 'about' => '', 'location' => '',
        'email' => '', 'github_url' => '', 'telegram_url' => '', 'linkedin_url' => '', 'avatar_url' => '',
    ];
}

/** Initials for the placeholder avatar. */
function initials(string $name): string
{
    $parts = preg_split('/\s+/u', trim($name)) ?: [];
    $out = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        $out .= mb_strtoupper(mb_substr($p, 0, 1));
    }
    return $out ?: '?';
}
