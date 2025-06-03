<?php

/**
 * 统一的会话管理和CSRF保护类
 * 解决重复定义和不一致的问题
 */

require_once dirname(__DIR__) . '/config.php';
require_once __DIR__ . '/input_validator.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * 会话管理类
 */
class SessionManager
{
    /**
     * 初始化会话
     */
    public static function init()
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        // 检查会话是否过期
        if (isset($_SESSION['last_activity'])) {
            if (time() - $_SESSION['last_activity'] > SESSION_LIFETIME) {
                self::destroy();
                return false;
            }
        }

        // 更新最后活动时间
        $_SESSION['last_activity'] = time();

        return true;
    }

    /**
     * 销毁会话
     */
    public static function destroy()
    {
        // 清除会话数据
        session_unset();
        session_destroy();

        // 删除会话cookie
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
    }

    /**
     * 重新生成会话ID
     */
    public static function regenerateId()
    {
        session_regenerate_id(true);
    }

    /**
     * 检查会话是否有效
     */
    public static function isValid()
    {
        return isset($_SESSION['user_id']) && isset($_SESSION['last_activity']);
    }
}

/**
 * 统一的CSRF保护类
 */
class CSRFProtection
{
    /**
     * 生成CSRF令牌
     */
    public static function generateToken()
    {
        if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
            $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
        }
        return $_SESSION[CSRF_TOKEN_NAME];
    }

    /**
     * 验证CSRF令牌
     */
    public static function validateToken($token)
    {
        if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
            return false;
        }
        return hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
    }
}

/**
 * 安全的重定向函数
 */
function safeRedirect($url = null, $default = '/')
{
    if ($url) {
        $validatedUrl = InputValidator::validateRedirectUrl($url);
        $url = $validatedUrl ?: $default;
    } else {
        $url = $default;
    }

    header("Location: " . $url);
    exit;
}
