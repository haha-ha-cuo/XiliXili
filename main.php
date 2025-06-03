<?php
session_start();

// 引入认证相关文件
require_once 'includes/auth_check.php';
require_once 'login.php';

// 检查登录状态
$isLoggedIn = isset($_SESSION['user_id']);
$currentUser = null;

if ($isLoggedIn) {
    $currentUser = getCurrentUser();
}

// 获取热门内容
try {
    $db = (new Database())->getConnection();

    // 获取热门内容
    $stmt = $db->prepare("
        SELECT c.*, u.username, u.nickname,
               cat.name as category_name
        FROM content c 
        JOIN users u ON c.user_id = u.id 
        JOIN categories cat ON c.category_id = cat.id
        WHERE c.status = 'published' 
        ORDER BY c.view_count DESC, c.created_at DESC 
        LIMIT 12
    ");
    $stmt->execute();
    $popularContent = $stmt->fetchAll();

    // 获取分类
    $stmt = $db->prepare("SELECT * FROM categories ORDER BY name");
    $stmt->execute();
    $categories = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error loading main page data: " . $e->getMessage());
    $popularContent = [];
    $categories = [];
}
?>
<!DOCTYPE html>
<html lang="zh-CN">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>XiliXili - 视频分享平台</title>
    <link rel="stylesheet" href="css/common.css">
    <link rel="stylesheet" href="css/main.css">
</head>

<body>
    <header class="nav">
        <div class="nav-brand">XiliXili</div>
        <ul class="nav-links">
            <li class="nav-link"><a href="main.php" class="active">首页</a></li>
            <?php if ($isLoggedIn): ?>
                <li class="nav-link"><a href="home.php">个人中心</a></li>
                <li class="nav-link"><a href="logout.php">登出</a></li>
            <?php else: ?>
                <li class="nav-link"><a href="login.php">登录</a></li>
                <li class="nav-link"><a href="register.php">注册</a></li>
            <?php endif; ?>
        </ul>
        <div class="search-container">
            <input class="search-input" type="text" placeholder="搜索..." id="searchInput">
            <button class="search-button" onclick="performSearch()"><img src="audio/search.svg" alt="search"></button>
        </div>
        <?php if ($isLoggedIn): ?>
            <div class="user-info">
                <span>欢迎，
                    <?php echo htmlspecialchars(getUserDisplayName($currentUser)); ?>
                </span>
            </div>
        <?php endif; ?>
    </header>

    <main>
        <section class="hero">
            <div class="hero-content">
                <h1>发现精彩内容</h1>
                <p>在这里分享你的创作，发现更多有趣的内容</p>
                <div class="hero-search">
                    <form action="search.php" method="GET" class="search-form">
                        <input type="text" name="q" placeholder="搜索内容、用户或标签..." class="search-input">
                        <button type="submit" class="search-btn">搜索</button>
                    </form>
                </div>
                <?php if ($currentUser): ?>
                    <div class="hero-actions">
                        <a href="home.php" class="btn btn-primary">进入个人中心</a>
                        <a href="#categories" class="btn btn-secondary">浏览分类</a>
                    </div>
                <?php else: ?>
                    <div class="hero-actions">
                        <a href="register.php" class="btn btn-primary">立即注册</a>
                        <a href="login.php" class="btn btn-secondary">登录</a>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="featured" id="featured">
            <h2>热门内容</h2>
            <div class="content-grid">
                <?php if (!empty($popularContent)): ?>
                    <?php foreach ($popularContent as $content): ?>
                        <article class="content-card card" data-content-id="<?php echo $content['id']; ?>">
                            <div class="card-image">
                                <?php if (strpos($content['file_type'], 'image/') === 0): ?>
                                    <img src="<?php echo htmlspecialchars($content['file_path']); ?>"
                                        alt="<?php echo htmlspecialchars($content['title']); ?>">
                                <?php else: ?>
                                    <img src="audio/login.svg" alt="<?php echo htmlspecialchars($content['title']); ?>">
                                <?php endif; ?>
                            </div>
                            <div class="card-content">
                                <h3>
                                    <?php echo htmlspecialchars($content['title']); ?>
                                </h3>
                                <p>
                                    <?php echo htmlspecialchars(substr($content['description'], 0, 100)) . (strlen($content['description']) > 100 ? '...' : ''); ?>
                                </p>
                                <div class="card-meta">
                                    <span class="author">
                                        <?php echo htmlspecialchars($content['nickname'] ?: $content['username']); ?>
                                    </span>
                                    <span class="views">
                                        <?php echo number_format($content['view_count']); ?> 观看
                                    </span>
                                    <span class="category">
                                        <?php echo htmlspecialchars($content['category_name']); ?>
                                    </span>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-content">
                        <p>暂无内容，成为第一个分享者吧！</p>
                        <?php if ($isLoggedIn): ?>
                            <a href="home.php" class="btn btn-primary">开始创作</a>
                        <?php else: ?>
                            <a href="register.php" class="btn btn-primary">立即注册</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="categories">
            <h2>内容分类</h2>
            <div class="category-grid">
                <?php if (!empty($categories)): ?>
                    <?php foreach ($categories as $category): ?>
                        <div class="category-card card" onclick="filterByCategory('<?php echo $category['id']; ?>')">
                            <img src="audio/login.svg" alt="<?php echo htmlspecialchars($category['name']); ?>">
                            <h3>
                                <?php echo htmlspecialchars($category['name']); ?>
                            </h3>
                            <p>
                                <?php echo htmlspecialchars($category['description']); ?>
                            </p>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="category-card card">
                        <img src="audio/login.svg" alt="综合">
                        <h3>综合</h3>
                        <p>所有类型内容</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <footer>
        <div class="footer-content">
            <div class="footer-section">
                <h3>XiliXili</h3>
                <p>分享创意，连接世界</p>
            </div>
            <div class="footer-section">
                <h4>关于我们</h4>
                <ul>
                    <li><a href="#">公司介绍</a></li>
                    <li><a href="#">联系我们</a></li>
                    <li><a href="#">加入我们</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h4>帮助与服务</h4>
                <ul>
                    <li><a href="#">用户指南</a></li>
                    <li><a href="#">创作者中心</a></li>
                    <li><a href="#">反馈建议</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>© 2025 XiliXili. All rights reserved.</p>
        </div>
    </footer>

    <script>
        // 搜索功能
        function performSearch() {
            const searchInput = document.getElementById('searchInput');
            const query = searchInput.value.trim();

            if (query) {
                // 这里可以实现实际的搜索逻辑
                console.log('搜索：', query);
                // 可以跳转到搜索结果页面
                // window.location.href = `search.php?q=${encodeURIComponent(query)}`;
                alert('搜索功能开发中：' + query);
            }
        }

        // 回车搜索
        document.getElementById('searchInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                performSearch();
            }
        });

        // 分类过滤
        function filterByCategory(categoryId) {
            console.log('过滤分类：', categoryId);
            // 这里可以实现分类过滤逻辑
            // window.location.href = `category.php?id=${categoryId}`;
            alert('分类过滤功能开发中：分类ID ' + categoryId);
        }

        // 内容卡片点击事件
        document.querySelectorAll('.content-card').forEach(card => {
            card.addEventListener('click', function() {
                const contentId = this.dataset.contentId;
                if (contentId) {
                    window.location.href = `content.php?id=${contentId}`;
                }
            });
        });

        // 平滑滚动
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // 加载动画
        window.addEventListener('load', function() {
            document.body.classList.add('loaded');
        });
    </script>
</body>

</html>