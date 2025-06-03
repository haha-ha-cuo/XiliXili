<?php
session_start();

// 数据库配置
define('DB_HOST', 'localhost');
define('DB_NAME', 'xilixili');
define('DB_USER', 'root');
define('DB_PASS', '');

// 安全配置
define('CSRF_TOKEN_NAME', 'csrf_token');
define('SESSION_TIMEOUT', 3600); // 1小时

/**
 * 数据库连接类
 */
class Database
{
    private $pdo;

    public function __construct()
    {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die("数据库连接失败");
        }
    }

    public function getConnection()
    {
        return $this->pdo;
    }
}

/**
 * 用户认证类
 */
class UserAuth
{
    private $db;

    public function __construct()
    {
        $this->db = (new Database())->getConnection();
    }

    /**
     * 验证用户登录
     */
    public function login($username, $password)
    {
        // 输入验证
        if (empty($username) || empty($password)) {
            return ['success' => false, 'message' => '用户名和密码不能为空'];
        }

        // 查询用户
        $stmt = $this->db->prepare("SELECT id, username, password_hash, email, status FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();

        if (!$user) {
            return ['success' => false, 'message' => '用户名或密码错误'];
        }

        // 检查用户状态
        if ($user['status'] !== 'active') {
            return ['success' => false, 'message' => '账户已被禁用'];
        }

        // 验证密码
        if (!password_verify($password, $user['password_hash'])) {
            return ['success' => false, 'message' => '用户名或密码错误'];
        }

        // 创建会话
        $this->createSession($user);

        // 更新最后登录时间
        $this->updateLastLogin($user['id']);

        return ['success' => true, 'message' => '登录成功', 'user' => $user];
    }

    /**
     * 创建用户会话
     */
    private function createSession($user)
    {
        // 重新生成会话ID防止会话固定攻击
        session_regenerate_id(true);

        // 设置会话数据
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();

        // 在数据库中记录会话
        $session_id = session_id();
        $stmt = $this->db->prepare("INSERT INTO sessions (session_id, user_id, created_at, last_activity, ip_address, user_agent) VALUES (?, ?, NOW(), NOW(), ?, ?)");
        $stmt->execute([
            $session_id,
            $user['id'],
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    }

    /**
     * 更新最后登录时间
     */
    private function updateLastLogin($userId)
    {
        $stmt = $this->db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$userId]);
    }

    /**
     * 检查用户是否已登录
     */
    public function isLoggedIn()
    {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['last_activity'])) {
            return false;
        }

        // 检查会话是否过期
        if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
            $this->logout();
            return false;
        }

        // 更新最后活动时间
        $_SESSION['last_activity'] = time();

        return true;
    }

    /**
     * 用户登出
     */
    public function logout()
    {
        if (isset($_SESSION['user_id'])) {
            // 从数据库删除会话记录
            $stmt = $this->db->prepare("DELETE FROM sessions WHERE session_id = ?");
            $stmt->execute([session_id()]);
        }

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
}

/**
 * CSRF保护类
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

// 处理登录请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF保护
    if (!isset($_POST['csrf_token']) || !CSRFProtection::validateToken($_POST['csrf_token'])) {
        $error = 'Invalid CSRF token';
    } else {
        $auth = new UserAuth();
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        $result = $auth->login($username, $password);

        if ($result['success']) {
            // 登录成功，重定向到个人中心或原访问页面
            $redirect = $_SESSION['redirect_after_login'] ?? 'home.php';
            unset($_SESSION['redirect_after_login']);
            header('Location: ' . $redirect);
            exit;
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
    <title>登录 - XiliXili</title>
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

        .password-strength {
            margin-top: var(--spacing-xs);
            font-size: var(--font-size-small);
        }

        .strength-weak {
            color: #dc3545;
        }

        .strength-medium {
            color: #ffc107;
        }

        .strength-strong {
            color: #28a745;
        }
    </style>
</head>

<body>
    <header class="nav">
        <div class="nav-brand">XiliXili</div>
        <ul class="nav-links">
            <li class="nav-link"><a href="main.php">首页</a></li>
            <li class="nav-link"><a href="login.php" class="active">登录</a></li>
            <li class="nav-link"><a href="register.php">注册</a></li>
        </ul>
    </header>

    <main class="login-container">
        <div class="login-form card">
            <h2>登录</h2>

            <?php if (isset($error)): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="login.php" class="login-form">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

                <div class="form-group">
                    <input class="input-box" type="text" name="username" placeholder="用户名或邮箱" required
                        value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <input class="input-box" type="password" name="password" placeholder="密码" required id="password">
                    <div class="password-strength" id="passwordStrength"></div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" id="loginBtn">登录</button>
                    <button type="button" class="btn btn-secondary" onclick="window.location.href='main.html'">取消</button>
                </div>

                <div style="text-align: center; margin-top: var(--spacing-md);">
                    <a href="register.php">还没有账户？立即注册</a>
                </div>
            </form>
        </div>
    </main>

    <footer>
        <p>© 2025 XiliXili. All rights reserved.</p>
    </footer>

    <script>
        // 表单验证和用户体验增强
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('loginForm');
            const loginBtn = document.getElementById('loginBtn');
            const passwordInput = document.getElementById('password');
            const strengthIndicator = document.getElementById('passwordStrength');

            // 密码强度检查
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                const strength = checkPasswordStrength(password);

                strengthIndicator.textContent = strength.text;
                strengthIndicator.className = 'password-strength ' + strength.class;
            });

            // 表单提交处理
            form.addEventListener('submit', function(e) {
                const username = form.username.value.trim();
                const password = form.password.value;

                if (!username || !password) {
                    e.preventDefault();
                    alert('请填写用户名和密码');
                    return;
                }

                // 显示加载状态
                loginBtn.textContent = '登录中...';
                loginBtn.disabled = true;
            });

            // 密码强度检查函数
            function checkPasswordStrength(password) {
                if (password.length < 6) {
                    return {
                        text: '密码强度：弱',
                        class: 'strength-weak'
                    };
                }

                let score = 0;
                if (password.length >= 8) score++;
                if (/[a-z]/.test(password)) score++;
                if (/[A-Z]/.test(password)) score++;
                if (/[0-9]/.test(password)) score++;
                if (/[^A-Za-z0-9]/.test(password)) score++;

                if (score < 3) {
                    return {
                        text: '密码强度：弱',
                        class: 'strength-weak'
                    };
                } else if (score < 4) {
                    return {
                        text: '密码强度：中',
                        class: 'strength-medium'
                    };
                } else {
                    return {
                        text: '密码强度：强',
                        class: 'strength-strong'
                    };
                }
            }
        });
    </script>
</body>

</html>