<?php
// 引入统一会话管理和认证类
require_once 'includes/session_manager.php';
require_once 'login.php';

// 初始化会话
SessionManager::init();

// 执行登出操作
$auth = new UserAuth();
$auth->logout();

// 安全的重定向到首页或登录页
$redirect = $_GET['redirect'] ?? 'main.php';
safeRedirect($redirect);
