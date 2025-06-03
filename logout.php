<?php
session_start();

// 引入认证类
require_once 'login.php';

// 执行登出操作
$auth = new UserAuth();
$auth->logout();

// 重定向到首页或登录页
$redirect = $_GET['redirect'] ?? 'main.php';
header('Location: ' . $redirect);
exit;
