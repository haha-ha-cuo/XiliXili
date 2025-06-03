<?php

/**
 * XiliXili 网站配置文件
 */

// 数据库配置
define('DB_HOST', 'localhost');
define('DB_NAME', 'xilixili');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// 网站配置
define('SITE_NAME', 'XiliXili');
define('SITE_URL', 'http://localhost:8000');
define('SITE_DESCRIPTION', '发现精彩内容，分享你的创作');

// 安全配置
define('CSRF_TOKEN_NAME', 'csrf_token');
define('SESSION_LIFETIME', 3600 * 24 * 7); // 7天
define('SESSION_TIMEOUT', 3600 * 24 * 7); // 统一会话超时时间

// 文件上传配置
define('UPLOAD_DIR', 'uploads/');
define('MAX_FILE_SIZE', 50 * 1024 * 1024); // 50MB
define('ALLOWED_FILE_TYPES', [
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp',
    'video/mp4',
    'video/webm',
    'video/ogg',
    'application/pdf',
    'text/plain'
]);

// 邮件配置（用于注册验证）
define('MAIL_FROM', 'noreply@xilixili.com');
define('MAIL_FROM_NAME', 'XiliXili');

// 错误报告设置
define('ENVIRONMENT', 'development'); // 定义环境常量，默认为开发环境
if (ENVIRONMENT === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// 时区设置
date_default_timezone_set('Asia/Shanghai');

// 会话配置
ini_set('session.cookie_lifetime', SESSION_LIFETIME);
ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0); // 在HTTPS环境下设置为1
ini_set('session.use_strict_mode', 1);

/**
 * 获取网站基础URL
 */
function getSiteUrl($path = '')
{
    return SITE_URL . ($path ? '/' . ltrim($path, '/') : '');
}

// CSRF保护功能已移至 includes/session_manager.php

/**
 * 安全的重定向函数
 */
function safeRedirect($url, $fallback = 'main.php')
{
    // 只允许相对URL或同域名URL
    if (filter_var($url, FILTER_VALIDATE_URL)) {
        $parsedUrl = parse_url($url);
        $siteUrl = parse_url(SITE_URL);

        if ($parsedUrl['host'] !== $siteUrl['host']) {
            $url = $fallback;
        }
    } elseif (!preg_match('/^[a-zA-Z0-9\/\-_\.\?=&]+$/', $url)) {
        $url = $fallback;
    }

    header('Location: ' . $url);
    exit;
}

/**
 * 格式化文件大小
 */
function formatFileSize($bytes)
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);

    $bytes /= (1 << (10 * $pow));

    return round($bytes, 2) . ' ' . $units[$pow];
}

/**
 * 生成随机字符串
 */
function generateRandomString($length = 32)
{
    return bin2hex(random_bytes($length / 2));
}
