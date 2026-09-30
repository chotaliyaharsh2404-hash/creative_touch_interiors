<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'creative_touch_interiors');

// Create database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to utf8mb4
$conn->set_charset("utf8mb4");

// Base URL - Dynamic detection to support localhost/project/ or custom document roots
$scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
$scriptDir = str_replace('\\', '/', $scriptDir);
if (strpos($scriptDir, '/admin') !== false) {
    $scriptDir = dirname($scriptDir);
}
$basePath = rtrim($scriptDir, '/') . '/';
define('BASE_URL', (isset($_SERVER['HTTP_HOST']) ? ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://") . $_SERVER['HTTP_HOST'] . $basePath : 'http://localhost/project/Creative%20Touch%20Interiors/'));

// Site Configuration
define('SITE_NAME', 'Creative Touch Interiors');
define('SITE_EMAIL', 'harshchotaliya@gmail.com');
define('SITE_PHONE', '+91 9316856961');

// Timezone Configuration
date_default_timezone_set('Asia/Kolkata');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Helper function to sanitize input
function sanitize($data) {
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $conn->real_escape_string($data);
}

// Helper function to check if regular user is logged in
function isUserLoggedIn() {
    return !empty($_SESSION['user_id']);
}

// Helper function to check if admin is logged in
function isAdminLoggedIn() {
    return isset($_SESSION['admin_id']) && isset($_SESSION['admin_username']);
}

// Helper function to check if current user is Super Admin
function isSuperAdmin() {
    return isAdminLoggedIn() && isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'super_admin';
}

// Helper function to prevent browser caching on protected pages
function preventPageCaching() {
    if (!headers_sent()) {
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        header("Cache-Control: post-check=0, pre-check=0", false);
        header("Pragma: no-cache");
        header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
    }
}

// Helper function to enforce user authentication on protected pages
function requireUserLogin($redirectUrl = 'login.php') {
    preventPageCaching();
    if (!isUserLoggedIn()) {
        redirect($redirectUrl);
    }
}

// Helper function to enforce admin authentication on protected pages
function requireAdminLogin($redirectUrl = 'login.php') {
    preventPageCaching();
    if (!isAdminLoggedIn()) {
        redirect($redirectUrl);
    }
}

// Helper function to enforce Super Admin role on restricted management pages
function requireSuperAdmin($redirectUrl = 'dashboard.php') {
    preventPageCaching();
    if (!isAdminLoggedIn()) {
        redirect('login.php');
    }
    if (!isSuperAdmin()) {
        redirect($redirectUrl . '?error=' . urlencode('Access restricted: Super Admin privileges are required to manage this section.'));
    }
}

// Helper function to completely destroy user session
function destroyUserSession() {
    unset($_SESSION['user_id']);
    unset($_SESSION['user_name']);
    unset($_SESSION['user_email']);
    
    $_SESSION = array();
    
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    
    if (session_status() === PHP_SESSION_ACTIVE) {
        @session_destroy();
    }
}

// Helper function to redirect
function redirect($url) {
    header("Location: " . $url);
    exit();
}

// Helper function to get site content
function getSiteContent($key) {
    global $conn;
    $sql = "SELECT content FROM website_content WHERE section_key = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return $row['content'];
    }
    return '';
}

// CSRF Protection Helpers
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

function validate_csrf() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
            return false;
        }
    }
    return true;
}

// Helper function to resolve team member photo with automatic extension matching and cache-busting
if (!function_exists('getTeamMemberPhoto')) {
    function getTeamMemberPhoto($name, $definedPhoto = '', $prefix = '') {
        $baseDir = dirname(__DIR__); // Project root directory
        
        if (!empty($definedPhoto)) {
            if (file_exists($definedPhoto)) {
                $ver = @filemtime($definedPhoto);
                return $definedPhoto . ($ver ? '?v=' . $ver : '');
            }
            $cleanDefined = ltrim(str_replace('\\', '/', $definedPhoto), '/');
            $fullPath = $baseDir . '/' . $cleanDefined;
            if (file_exists($fullPath)) {
                $ver = @filemtime($fullPath);
                return $prefix . $cleanDefined . ($ver ? '?v=' . $ver : '');
            }
        }
        
        $slug = strtolower(explode(' ', trim($name))[0]);
        $teamDir = $baseDir . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'team';
        
        foreach (['jpeg', 'jpg', 'png', 'webp', 'jpge'] as $ext) {
            $diskPath = $teamDir . DIRECTORY_SEPARATOR . "{$slug}.{$ext}";
            if (file_exists($diskPath)) {
                $ver = @filemtime($diskPath);
                return $prefix . "uploads/team/{$slug}.{$ext}" . ($ver ? '?v=' . $ver : '');
            }
        }
        return '';
    }
}

// Quotation & Estimation Helpers
if (!function_exists('generateQuoteNumber')) {
    function generateQuoteNumber($conn) {
        $year = date('Y');
        $prefix = "CTI-QT-{$year}-";
        $sql = "SELECT id FROM quote_requests ORDER BY id DESC LIMIT 1";
        $res = $conn->query($sql);
        $nextId = 1;
        if ($res && $row = $res->fetch_assoc()) {
            $nextId = (int)$row['id'] + 1;
        }
        return $prefix . str_pad($nextId, 4, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('getQuoteStatusInfo')) {
    function getQuoteStatusInfo($status) {
        $status = strtolower(trim($status ?? 'new'));
        $map = [
            'new' => [
                'label' => 'New Request',
                'step' => 1,
                'color' => '#2563eb',
                'bg' => '#eff6ff',
                'border' => '#bfdbfe',
                'icon' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"></path></svg>',
                'desc' => 'Quote request received and queued for review.'
            ],
            'under_review' => [
                'label' => 'Under Review',
                'step' => 2,
                'color' => '#4f46e5',
                'bg' => '#eef2ff',
                'border' => '#c7d2fe',
                'icon' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>',
                'desc' => 'Our lead architects are evaluating your project specifications.'
            ],
            'contacted' => [
                'label' => 'In Discussion',
                'step' => 3,
                'color' => '#0284c7',
                'bg' => '#f0f9ff',
                'border' => '#bae6fd',
                'icon' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>',
                'desc' => 'Our design team has reached out for initial project discussion.'
            ],
            'site_visit_scheduled' => [
                'label' => 'Site Visit Scheduled',
                'step' => 4,
                'color' => '#d97706',
                'bg' => '#fffbeb',
                'border' => '#fde68a',
                'icon' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>',
                'desc' => 'On-site measurement and survey appointment booked.'
            ],
            'estimation_prepared' => [
                'label' => 'Estimation Prepared',
                'step' => 5,
                'color' => '#7c3aed',
                'bg' => '#f5f3ff',
                'border' => '#ddd6fe',
                'icon' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.3 15.3l-7.6-7.6a1 1 0 0 0-1.4 0l-7.6 7.6a1 1 0 0 0 0 1.4l7.6 7.6a1 1 0 0 0 1.4 0l7.6-7.6a1 1 0 0 0 0-1.4z"></path></svg>',
                'desc' => 'Itemized cost estimation and scope draft have been prepared.'
            ],
            'quote_sent' => [
                'label' => 'Quote Sent',
                'step' => 6,
                'color' => '#0d9488',
                'bg' => '#f0fdfa',
                'border' => '#99f6e4',
                'icon' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>',
                'desc' => 'Detailed quotation proposal has been shared for your review.'
            ],
            'approved' => [
                'label' => 'Approved by Client',
                'step' => 7,
                'color' => '#16a34a',
                'bg' => '#f0fdf4',
                'border' => '#bbf7d0',
                'icon' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>',
                'desc' => 'Quotation accepted and approved for execution planning.'
            ],
            'in_progress' => [
                'label' => 'In Execution',
                'step' => 8,
                'color' => '#059669',
                'bg' => '#ecfdf5',
                'border' => '#a7f3d0',
                'icon' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path></svg>',
                'desc' => 'Interior craftsmanship and on-site execution are actively underway.'
            ],
            'project_started' => [
                'label' => 'In Execution',
                'step' => 8,
                'color' => '#059669',
                'bg' => '#ecfdf5',
                'border' => '#a7f3d0',
                'icon' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path></svg>',
                'desc' => 'Interior craftsmanship and on-site execution are actively underway.'
            ],
            'completed' => [
                'label' => 'Completed',
                'step' => 9,
                'color' => '#15803d',
                'bg' => '#dcfce7',
                'border' => '#86efac',
                'icon' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="7"></circle><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"></polyline></svg>',
                'desc' => 'Project has been successfully executed, inspected, and handed over.'
            ],
            'rejected' => [
                'label' => 'Closed / Rejected',
                'step' => 10,
                'color' => '#dc2626',
                'bg' => '#fef2f2',
                'border' => '#fecaca',
                'icon' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>',
                'desc' => 'Quote request was cancelled or declined.'
            ]
        ];
        return $map[$status] ?? [
            'label' => ucfirst(str_replace('_', ' ', $status)),
            'step' => 1,
            'color' => '#64748b',
            'bg' => '#f8fafc',
            'border' => '#e2e8f0',
            'icon' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>',
            'desc' => ''
        ];
    }
}

if (!function_exists('recordQuoteStatusHistory')) {
    function recordQuoteStatusHistory($conn, $quoteId, $prevStatus, $newStatus, $byType = 'admin', $byName = 'Admin', $comment = '') {
        $stmt = $conn->prepare("INSERT INTO quote_status_history (quote_id, previous_status, new_status, changed_by_type, changed_by_name, comment) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssss", $quoteId, $prevStatus, $newStatus, $byType, $byName, $comment);
        return $stmt->execute();
    }
}

if (!function_exists('getAllSiteContent')) {
    function getAllSiteContent() {
        global $conn;
        $content = [];
        $res = $conn->query("SELECT section_key, content FROM website_content");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $content[$row['section_key']] = $row['content'];
            }
        }
        return $content;
    }
}

// Require authoritative quote engine & calculation library
require_once __DIR__ . '/quote_engine.php';
?>
