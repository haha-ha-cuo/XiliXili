<?php
// 引入认证检查
require_once 'includes/auth_check.php';

// 检查登录状态
requireLogin();

// 获取当前用户信息
$currentUser = getCurrentUser();
if (!$currentUser) {
    header('Location: login.php');
    exit;
}

// 获取用户统计信息
try {
    $db = (new Database())->getConnection();

    // 获取用户内容统计
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as content_count,
            SUM(view_count) as total_views,
            SUM(like_count) as total_likes
        FROM content 
        WHERE user_id = ? AND status = 'published'
    ");
    $stmt->execute([$currentUser['id']]);
    $userStats = $stmt->fetch();

    // 获取最新通知
    $stmt = $db->prepare("
        SELECT title, content, created_at 
        FROM notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$currentUser['id']]);
    $notifications = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error loading user data: " . $e->getMessage());
    $userStats = ['content_count' => 0, 'total_views' => 0, 'total_likes' => 0];
    $notifications = [];
}
?>
<!DOCTYPE html>
<html lang="zh-CN">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>个人中心 - XiliXili</title>
    <link rel="stylesheet" href="css/common.css">
    <link rel="stylesheet" href="css/home.css">
</head>

<body>
    <header class="nav">
        <div class="nav-brand">XiliXili</div>
        <ul class="nav-links">
            <li class="nav-link"><a href="main.php">首页</a></li>
            <li class="nav-link"><a href="home.php" class="active">个人中心</a></li>
            <li class="nav-link"><a href="logout.php">登出</a></li>
        </ul>
        <div class="search-container">
            <input class="search-input" type="text" placeholder="搜索...">
            <button class="search-button"><img src="audio/search.svg" alt="search"></button>
        </div>
        <div class="user-info">
            <span>欢迎，
                <?php echo htmlspecialchars(getUserDisplayName($currentUser)); ?>
            </span>
        </div>
    </header>

    <main class="main-container">
        <aside class="sidebar">
            <div class="user-profile card">
                <img src="<?php echo htmlspecialchars(getUserAvatarUrl($currentUser)); ?>" alt="用户头像"
                    class="user-avatar">
                <h3>
                    <?php echo htmlspecialchars(getUserDisplayName($currentUser)); ?>
                </h3>
                <p>
                    <?php echo htmlspecialchars($currentUser['email']); ?>
                </p>
                <div class="user-stats">
                    <div class="stat">
                        <span class="stat-number">
                            <?php echo number_format($userStats['content_count'] ?? 0); ?>
                        </span>
                        <span class="stat-label">内容</span>
                    </div>
                    <div class="stat">
                        <span class="stat-number">
                            <?php echo number_format($userStats['total_views'] ?? 0); ?>
                        </span>
                        <span class="stat-label">观看</span>
                    </div>
                    <div class="stat">
                        <span class="stat-number">
                            <?php echo number_format($userStats['total_likes'] ?? 0); ?>
                        </span>
                        <span class="stat-label">点赞</span>
                    </div>
                </div>
            </div>

            <nav class="sidebar-nav">
                <ul>
                    <li><a href="#content" class="active">我的内容</a></li>
                    <li><a href="#favorites">收藏夹</a></li>
                    <li><a href="#history">历史记录</a></li>
                    <li><a href="#settings">设置</a></li>
                </ul>
            </nav>
        </aside>

        <div class="content-area">
            <section class="latest-news">
                <h2>系统通知</h2>
                <div class="news-grid">
                    <?php if (!empty($notifications)): ?>
                        <?php foreach ($notifications as $notification): ?>
                            <article class="news-card card">
                                <h3>
                                    <?php echo htmlspecialchars($notification['title']); ?>
                                </h3>
                                <p>
                                    <?php echo htmlspecialchars($notification['content']); ?>
                                </p>
                                <time>
                                    <?php echo date('Y-m-d H:i', strtotime($notification['created_at'])); ?>
                                </time>
                            </article>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <article class="news-card card">
                            <h3>欢迎来到XiliXili</h3>
                            <p>开始您的创作之旅，分享精彩内容给更多人...</p>
                            <time>
                                <?php echo date('Y-m-d H:i'); ?>
                            </time>
                        </article>
                    <?php endif; ?>
                </div>
            </section>

            <section class="activity">
                <h2>快速操作</h2>
                <div class="quick-actions">
                    <div class="action-card card">
                        <h3>上传内容</h3>
                        <p>分享您的创作</p>
                        <button class="btn btn-primary" onclick="showUploadModal()">开始上传</button>
                    </div>
                    <div class="action-card card">
                        <h3>编辑资料</h3>
                        <p>完善个人信息</p>
                        <button class="btn btn-secondary" onclick="location.href='#settings'">编辑资料</button>
                    </div>
                    <div class="action-card card">
                        <h3>数据统计</h3>
                        <p>查看详细数据</p>
                        <button class="btn btn-secondary" onclick="showStatsModal()">查看统计</button>
                    </div>
                </div>
            </section>

            <div class="main-content">
                <section class="card">
                    <h2>近期活跃情况</h2>
                    <div class="card-content">
                        <p>这里会放每周的数据曲线</p>
                        <div class="activity-chart">
                            <!-- 图表将在这里显示 -->
                            <div class="placeholder-chart"></div>
                        </div>
                    </div>
                </section>

                <section class="card">
                    <h3>为你的作品添加封面</h3>
                    <div class="card-content">
                        <div class="upload-area">
                            <img src="#" alt="上传封面" class="upload-icon">
                            <p>点击上传图片</p>
                        </div>
                    </div>
                </section>

                <section class="card">
                    <h3>上传作品</h3>
                    <div class="card-content">
                        <div class="upload-area">
                            <p>支持 mp4/word 格式</p>
                            <button class="btn">选择文件</button>
                        </div>
                    </div>
                </section>

                <section class="card">
                    <h3>作品推荐</h3>
                    <div class="card-content">
                        <div class="recommendations">
                            <div class="recommendation-item">作品1</div>
                            <div class="recommendation-item">作品2</div>
                            <div class="recommendation-item">作品3</div>
                        </div>
                    </div>
                </section>
            </div>

            <aside class="sidebar-right">
                <div class="card">
                    <div class="card-header">
                        <h2>通知</h2>
                        <img src="audio/notice.svg" alt="notice">
                    </div>
                    <div class="card-content">
                        <p>这里会放一些近期的消息</p>
                        <ul class="notification-list">
                            <li>系统通知：平台已更新到最新版本</li>
                            <li>您的作品《示例》获得了10个赞</li>
                            <li>新功能上线：现在可以使用AI辅助创作</li>
                        </ul>
                    </div>
                </div>
            </aside>
        </div>
    </main>

    <footer>
        <p>© 2025 XiliXili. All rights reserved.</p>
    </footer>

    <!-- 上传模态框 -->
    <div id="uploadModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close" onclick="closeUploadModal()">&times;</span>
            <h2>上传内容</h2>
            <form id="uploadForm" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="title">标题</label>
                    <input type="text" id="title" name="title" required>
                </div>
                <div class="form-group">
                    <label for="description">描述</label>
                    <textarea id="description" name="description" rows="4"></textarea>
                </div>
                <div class="form-group">
                    <label for="file">选择文件</label>
                    <input type="file" id="file" name="file" accept="video/*,image/*" required>
                </div>
                <div class="form-group">
                    <label for="category">分类</label>
                    <select id="category" name="category">
                        <option value="video">视频</option>
                        <option value="image">图片</option>
                        <option value="other">其他</option>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">上传</button>
                    <button type="button" class="btn btn-secondary" onclick="closeUploadModal()">取消</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 统计模态框 -->
    <div id="statsModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close" onclick="closeStatsModal()">&times;</span>
            <h2>数据统计</h2>
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>总内容数</h3>
                    <p class="stat-value">
                        <?php echo number_format($userStats['content_count'] ?? 0); ?>
                    </p>
                </div>
                <div class="stat-card">
                    <h3>总观看数</h3>
                    <p class="stat-value">
                        <?php echo number_format($userStats['total_views'] ?? 0); ?>
                    </p>
                </div>
                <div class="stat-card">
                    <h3>总点赞数</h3>
                    <p class="stat-value">
                        <?php echo number_format($userStats['total_likes'] ?? 0); ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // 模态框控制
        function showUploadModal() {
            document.getElementById('uploadModal').style.display = 'block';
        }

        function closeUploadModal() {
            document.getElementById('uploadModal').style.display = 'none';
        }

        function showStatsModal() {
            document.getElementById('statsModal').style.display = 'block';
        }

        function closeStatsModal() {
            document.getElementById('statsModal').style.display = 'none';
        }

        // 点击模态框外部关闭
        window.onclick = function(event) {
            const uploadModal = document.getElementById('uploadModal');
            const statsModal = document.getElementById('statsModal');
            if (event.target === uploadModal) {
                uploadModal.style.display = 'none';
            }
            if (event.target === statsModal) {
                statsModal.style.display = 'none';
            }
        }

        // 上传表单处理
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);

            // 显示加载状态
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.textContent = '上传中...';
            submitBtn.disabled = true;

            // 这里可以添加实际的上传逻辑
            fetch('upload.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('上传成功！');
                        closeUploadModal();
                        location.reload();
                    } else {
                        alert('上传失败：' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('上传过程中发生错误');
                })
                .finally(() => {
                    submitBtn.textContent = originalText;
                    submitBtn.disabled = false;
                });
        });

        // 侧边栏导航
        document.querySelectorAll('.sidebar-nav a').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();

                // 移除所有活跃状态
                document.querySelectorAll('.sidebar-nav a').forEach(l => l.classList.remove('active'));

                // 添加当前活跃状态
                this.classList.add('active');

                // 这里可以添加内容切换逻辑
                const target = this.getAttribute('href').substring(1);
                console.log('切换到：', target);
            });
        });
    </script>
</body>

</html>