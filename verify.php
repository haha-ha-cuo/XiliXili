<?php
// 引入统一会话管理和注册类
require_once 'includes/session_manager.php';
require_once 'register.php';

// 初始化会话
SessionManager::init();

$message = '';
$messageType = 'error';

// 处理邮箱验证
if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = $_GET['token'];
    $registration = new UserRegistration();
    $result = $registration->verifyEmail($token);

    if ($result['success']) {
        $message = $result['message'];
        $messageType = 'success';
    } else {
        $message = $result['message'];
        $messageType = 'error';
    }
} else {
    $message = '验证链接无效';
    $messageType = 'error';
}
?>
<!DOCTYPE html>
<html lang="zh-CN">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>邮箱验证 - XiliXili</title>
    <link rel="stylesheet" type="text/css" href="css/common.css">
    <link rel="stylesheet" type="text/css" href="css/login.css">
    <style>
        .message {
            padding: var(--spacing-md);
            border-radius: var(--border-radius-sm);
            margin-bottom: var(--spacing-md);
            text-align: center;
        }

        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .verification-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: calc(100vh - 140px);
            padding: var(--spacing-lg);
        }

        .verification-card {
            width: 400px;
            max-width: 100%;
            padding: var(--spacing-xl);
            text-align: center;
        }

        .verification-icon {
            font-size: 4rem;
            margin-bottom: var(--spacing-md);
        }

        .success-icon {
            color: #28a745;
        }

        .error-icon {
            color: #dc3545;
        }

        .action-buttons {
            margin-top: var(--spacing-lg);
            display: flex;
            gap: var(--spacing-md);
            justify-content: center;
        }
    </style>
</head>

<body>
    <header class="nav">
        <div class="nav-brand">XiliXili</div>
        <ul class="nav-links">
            <li class="nav-link"><a href="main.php">首页</a></li>
            <li class="nav-link"><a href="login.php">登录</a></li>
            <li class="nav-link"><a href="register.php">注册</a></li>
        </ul>
    </header>

    <main class="verification-container">
        <div class="verification-card card">
            <div class="verification-icon <?php echo $messageType === 'success' ? 'success-icon' : 'error-icon'; ?>">
                <?php echo $messageType === 'success' ? '✓' : '✗'; ?>
            </div>

            <h2>邮箱验证</h2>

            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>

            <div class="action-buttons">
                <?php if ($messageType === 'success'): ?>
                    <a href="login.php" class="btn btn-primary">立即登录</a>
                    <a href="main.php" class="btn btn-secondary">返回首页</a>
                <?php else: ?>
                    <a href="register.php" class="btn btn-primary">重新注册</a>
                    <a href="main.php" class="btn btn-secondary">返回首页</a>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <footer>
        <p>© 2025 XiliXili. All rights reserved.</p>
    </footer>
</body>

</html>