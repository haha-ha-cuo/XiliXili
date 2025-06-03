<?php
session_start();

// 引入数据库和认证类
require_once 'login.php';

/**
 * 用户注册类
 */
class UserRegistration
{
    private $db;

    public function __construct()
    {
        $this->db = (new Database())->getConnection();
    }

    /**
     * 注册新用户
     */
    public function register($username, $email, $password, $confirmPassword, $nickname = '')
    {
        // 输入验证
        $validation = $this->validateInput($username, $email, $password, $confirmPassword);
        if (!$validation['valid']) {
            return ['success' => false, 'message' => $validation['message']];
        }

        // 检查用户名和邮箱是否已存在
        if ($this->userExists($username, $email)) {
            return ['success' => false, 'message' => '用户名或邮箱已存在'];
        }

        // 创建用户
        try {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $emailVerificationToken = bin2hex(random_bytes(32));

            $stmt = $this->db->prepare("
                INSERT INTO users (username, email, password_hash, nickname, email_verification_token, status) 
                VALUES (?, ?, ?, ?, ?, 'inactive')
            ");

            $stmt->execute([
                $username,
                $email,
                $passwordHash,
                $nickname ?: $username,
                $emailVerificationToken
            ]);

            $userId = $this->db->lastInsertId();

            // 发送验证邮件（这里只是模拟，实际需要配置邮件服务）
            $this->sendVerificationEmail($email, $emailVerificationToken);

            return [
                'success' => true,
                'message' => '注册成功！请检查您的邮箱并点击验证链接激活账户。',
                'user_id' => $userId
            ];
        } catch (PDOException $e) {
            error_log("Registration error: " . $e->getMessage());
            return ['success' => false, 'message' => '注册失败，请稍后重试'];
        }
    }

    /**
     * 验证输入数据
     */
    private function validateInput($username, $email, $password, $confirmPassword)
    {
        // 用户名验证
        if (empty($username)) {
            return ['valid' => false, 'message' => '用户名不能为空'];
        }
        if (strlen($username) < 3 || strlen($username) > 20) {
            return ['valid' => false, 'message' => '用户名长度必须在3-20个字符之间'];
        }
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            return ['valid' => false, 'message' => '用户名只能包含字母、数字和下划线'];
        }

        // 邮箱验证
        if (empty($email)) {
            return ['valid' => false, 'message' => '邮箱不能为空'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['valid' => false, 'message' => '邮箱格式不正确'];
        }

        // 密码验证
        if (empty($password)) {
            return ['valid' => false, 'message' => '密码不能为空'];
        }
        if (strlen($password) < 6) {
            return ['valid' => false, 'message' => '密码长度至少6个字符'];
        }
        if ($password !== $confirmPassword) {
            return ['valid' => false, 'message' => '两次输入的密码不一致'];
        }

        return ['valid' => true];
    }

    /**
     * 检查用户是否已存在
     */
    private function userExists($username, $email)
    {
        $stmt = $this->db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        return $stmt->fetch() !== false;
    }

    /**
     * 发送验证邮件（模拟）
     */
    private function sendVerificationEmail($email, $token)
    {
        // 这里应该实现真实的邮件发送功能
        // 为了演示，我们只是记录到日志
        $verificationUrl = "http://" . $_SERVER['HTTP_HOST'] . "/verify.php?token=" . $token;
        error_log("Verification email would be sent to {$email} with URL: {$verificationUrl}");
    }

    /**
     * 验证邮箱
     */
    public function verifyEmail($token)
    {
        $stmt = $this->db->prepare("SELECT id FROM users WHERE email_verification_token = ? AND status = 'inactive'");
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if (!$user) {
            return ['success' => false, 'message' => '验证链接无效或已过期'];
        }

        // 激活用户账户
        $stmt = $this->db->prepare("UPDATE users SET status = 'active', email_verified = TRUE, email_verification_token = NULL WHERE id = ?");
        $stmt->execute([$user['id']]);

        return ['success' => true, 'message' => '邮箱验证成功！您现在可以登录了。'];
    }
}

// 处理注册请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF保护
    if (!isset($_POST['csrf_token']) || !CSRFProtection::validateToken($_POST['csrf_token'])) {
        $error = 'Invalid CSRF token';
    } else {
        $registration = new UserRegistration();

        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $nickname = trim($_POST['nickname'] ?? '');

        $result = $registration->register($username, $email, $password, $confirmPassword, $nickname);

        if ($result['success']) {
            $success = $result['message'];
        } else {
            $error = $result['message'];
        }
    }
}

// 如果已经登录，重定向到个人中心
$auth = new UserAuth();
if ($auth->isLoggedIn()) {
    header('Location: home.html');
    exit;
}

// 生成CSRF令牌
$csrf_token = CSRFProtection::generateToken();
?>
<!DOCTYPE html>
<html lang="zh-CN">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>注册 - XiliXili</title>
    <link rel="stylesheet" type="text/css" href="css/common.css">
    <link rel="stylesheet" type="text/css" href="css/login.css">
    <style>
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: var(--spacing-sm) var(--spacing-md);
            border: 1px solid #f5c6cb;
            border-radius: var(--border-radius-sm);
            margin-bottom: var(--spacing-md);
        }

        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: var(--spacing-sm) var(--spacing-md);
            border: 1px solid #c3e6cb;
            border-radius: var(--border-radius-sm);
            margin-bottom: var(--spacing-md);
        }

        .password-requirements {
            font-size: var(--font-size-small);
            color: var(--secondary-color);
            margin-top: var(--spacing-xs);
        }

        .requirement {
            display: flex;
            align-items: center;
            margin-bottom: var(--spacing-xs);
        }

        .requirement.valid {
            color: #28a745;
        }

        .requirement.invalid {
            color: #dc3545;
        }

        .requirement::before {
            content: '✗';
            margin-right: var(--spacing-xs);
        }

        .requirement.valid::before {
            content: '✓';
        }
    </style>
</head>

<body>
    <header class="nav">
        <div class="nav-brand">XiliXili</div>
        <ul class="nav-links">
            <li class="nav-link"><a href="main.php">首页</a></li>
            <li class="nav-link"><a href="login.php">登录</a></li>
            <li class="nav-link"><a href="register.php" class="active">注册</a></li>
        </ul>
    </header>

    <main class="login-container">
        <div class="login-form card">
            <h2>注册账户</h2>

            <?php if (isset($error)): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if (isset($success)): ?>
                <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <form class="main-form" method="post" id="registerForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

                <div class="form-group">
                    <input class="input-box" type="text" name="username" placeholder="用户名" required
                        value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" id="username">
                    <div class="password-requirements" id="usernameRequirements" style="display: none;">
                        <div class="requirement" id="req-username-length">3-20个字符</div>
                        <div class="requirement" id="req-username-chars">只能包含字母、数字和下划线</div>
                    </div>
                </div>

                <div class="form-group">
                    <input class="input-box" type="email" name="email" placeholder="邮箱地址" required
                        value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <input class="input-box" type="text" name="nickname" placeholder="昵称（可选）"
                        value="<?php echo htmlspecialchars($_POST['nickname'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <input class="input-box" type="password" name="password" placeholder="密码" required id="password">
                    <div class="password-requirements" id="passwordRequirements" style="display: none;">
                        <div class="requirement" id="req-length">至少6个字符</div>
                        <div class="requirement" id="req-uppercase">包含大写字母</div>
                        <div class="requirement" id="req-lowercase">包含小写字母</div>
                        <div class="requirement" id="req-number">包含数字</div>
                        <div class="requirement" id="req-special">包含特殊字符</div>
                    </div>
                </div>

                <div class="form-group">
                    <input class="input-box" type="password" name="confirm_password" placeholder="确认密码" required id="confirmPassword">
                    <div id="passwordMatch" style="font-size: var(--font-size-small); margin-top: var(--spacing-xs);"></div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" id="registerBtn">注册</button>
                    <button type="button" class="btn btn-secondary" onclick="window.location.href='login.php'">返回登录</button>
                </div>

                <div style="text-align: center; margin-top: var(--spacing-md);">
                    <a href="login.php">已有账户？立即登录</a>
                </div>
            </form>
        </div>
    </main>

    <footer>
        <p>© 2025 XiliXili. All rights reserved.</p>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('registerForm');
            const registerBtn = document.getElementById('registerBtn');
            const usernameInput = document.getElementById('username');
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirmPassword');
            const usernameRequirements = document.getElementById('usernameRequirements');
            const passwordRequirements = document.getElementById('passwordRequirements');
            const passwordMatch = document.getElementById('passwordMatch');

            // 用户名验证
            usernameInput.addEventListener('focus', function() {
                usernameRequirements.style.display = 'block';
            });

            usernameInput.addEventListener('input', function() {
                const username = this.value;

                // 长度检查
                const lengthReq = document.getElementById('req-username-length');
                if (username.length >= 3 && username.length <= 20) {
                    lengthReq.classList.add('valid');
                    lengthReq.classList.remove('invalid');
                } else {
                    lengthReq.classList.add('invalid');
                    lengthReq.classList.remove('valid');
                }

                // 字符检查
                const charsReq = document.getElementById('req-username-chars');
                if (/^[a-zA-Z0-9_]*$/.test(username)) {
                    charsReq.classList.add('valid');
                    charsReq.classList.remove('invalid');
                } else {
                    charsReq.classList.add('invalid');
                    charsReq.classList.remove('valid');
                }
            });

            // 密码验证
            passwordInput.addEventListener('focus', function() {
                passwordRequirements.style.display = 'block';
            });

            passwordInput.addEventListener('input', function() {
                const password = this.value;

                // 长度检查
                const lengthReq = document.getElementById('req-length');
                if (password.length >= 6) {
                    lengthReq.classList.add('valid');
                    lengthReq.classList.remove('invalid');
                } else {
                    lengthReq.classList.add('invalid');
                    lengthReq.classList.remove('valid');
                }

                // 大写字母检查
                const uppercaseReq = document.getElementById('req-uppercase');
                if (/[A-Z]/.test(password)) {
                    uppercaseReq.classList.add('valid');
                    uppercaseReq.classList.remove('invalid');
                } else {
                    uppercaseReq.classList.add('invalid');
                    uppercaseReq.classList.remove('valid');
                }

                // 小写字母检查
                const lowercaseReq = document.getElementById('req-lowercase');
                if (/[a-z]/.test(password)) {
                    lowercaseReq.classList.add('valid');
                    lowercaseReq.classList.remove('invalid');
                } else {
                    lowercaseReq.classList.add('invalid');
                    lowercaseReq.classList.remove('valid');
                }

                // 数字检查
                const numberReq = document.getElementById('req-number');
                if (/[0-9]/.test(password)) {
                    numberReq.classList.add('valid');
                    numberReq.classList.remove('invalid');
                } else {
                    numberReq.classList.add('invalid');
                    numberReq.classList.remove('valid');
                }

                // 特殊字符检查
                const specialReq = document.getElementById('req-special');
                if (/[^A-Za-z0-9]/.test(password)) {
                    specialReq.classList.add('valid');
                    specialReq.classList.remove('invalid');
                } else {
                    specialReq.classList.add('invalid');
                    specialReq.classList.remove('valid');
                }

                checkPasswordMatch();
            });

            // 确认密码验证
            confirmPasswordInput.addEventListener('input', checkPasswordMatch);

            function checkPasswordMatch() {
                const password = passwordInput.value;
                const confirmPassword = confirmPasswordInput.value;

                if (confirmPassword === '') {
                    passwordMatch.textContent = '';
                    return;
                }

                if (password === confirmPassword) {
                    passwordMatch.textContent = '✓ 密码匹配';
                    passwordMatch.style.color = '#28a745';
                } else {
                    passwordMatch.textContent = '✗ 密码不匹配';
                    passwordMatch.style.color = '#dc3545';
                }
            }

            // 表单提交处理
            form.addEventListener('submit', function(e) {
                const username = form.username.value.trim();
                const email = form.email.value.trim();
                const password = form.password.value;
                const confirmPassword = form.confirm_password.value;

                if (!username || !email || !password || !confirmPassword) {
                    e.preventDefault();
                    alert('请填写所有必填字段');
                    return;
                }

                if (password !== confirmPassword) {
                    e.preventDefault();
                    alert('两次输入的密码不一致');
                    return;
                }

                // 显示加载状态
                registerBtn.textContent = '注册中...';
                registerBtn.disabled = true;
            });
        });
    </script>
</body>

</html>