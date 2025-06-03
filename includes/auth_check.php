<?php

/**
 * 认证检查中间件
 * 用于保护需要登录才能访问的页面
 */

// 引入统一会话管理
require_once __DIR__ . '/session_manager.php';
require_once dirname(__DIR__) . '/login.php';

// 初始化会话
SessionManager::init();

/**
 * 检查用户是否已登录
 * 如果未登录，重定向到登录页面
 */
function requireLogin($redirectAfterLogin = null)
{
    $auth = new UserAuth();

    if (!$auth->isLoggedIn()) {
        // 保存当前页面URL，登录后重定向回来
        if ($redirectAfterLogin) {
            $_SESSION['redirect_after_login'] = $redirectAfterLogin;
        } else {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        }

        header('Location: login.php');
        exit;
    }
}

/**
 * 获取当前登录用户信息
 */
function getCurrentUser()
{
    if (!isset($_SESSION['user_id'])) {
        return null;
    }

    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT id, username, email, nickname, avatar, status FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log("Error getting current user: " . $e->getMessage());
        return null;
    }
}

/**
 * 检查用户是否有特定权限
 */
function hasPermission($permission)
{
    $user = getCurrentUser();
    if (!$user) {
        return false;
    }

    // 这里可以扩展权限系统
    // 目前简单检查用户状态
    return $user['status'] === 'active';
}

/**
 * 生成用户头像URL
 */
function getUserAvatarUrl($user)
{
    if ($user && !empty($user['avatar'])) {
        return $user['avatar'];
    }

    // 默认头像
    return 'audio/login.svg';
}

/**
 * 获取用户显示名称
 */
function getUserDisplayName($user)
{
    if (!$user) {
        return '游客';
    }

    return $user['nickname'] ?: $user['username'];
}
