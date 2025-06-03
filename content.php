<?php

/**
 * 内容详情页面
 */

require_once 'config.php';
require_once 'includes/auth_check.php';

// 启动会话
session_start();

// 数据库连接类
class Database
{
    private $pdo;

    public function __construct()
    {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
        } catch (PDOException $e) {
            throw new Exception('数据库连接失败: ' . $e->getMessage());
        }
    }

    public function getConnection()
    {
        return $this->pdo;
    }
}

// 内容管理类
class ContentManager
{
    private $db;

    public function __construct($database)
    {
        $this->db = $database->getConnection();
    }

    /**
     * 获取内容详情
     */
    public function getContentById($id)
    {
        $sql = "
            SELECT 
                c.*,
                u.username,
                u.display_name,
                u.avatar,
                cat.name as category_name,
                cat.color as category_color,
                cat.description as category_description
            FROM content c
            LEFT JOIN users u ON c.user_id = u.id
            LEFT JOIN categories cat ON c.category_id = cat.id
            WHERE c.id = :id AND c.status = 'published'
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch();
    }

    /**
     * 增加浏览次数
     */
    public function incrementViews($id)
    {
        $sql = "UPDATE content SET views = views + 1 WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * 获取相关内容
     */
    public function getRelatedContent($contentId, $categoryId, $limit = 6)
    {
        $sql = "
            SELECT 
                c.*,
                u.username,
                u.display_name
            FROM content c
            LEFT JOIN users u ON c.user_id = u.id
            WHERE c.id != :content_id 
                AND c.category_id = :category_id 
                AND c.status = 'published'
            ORDER BY c.views DESC, c.created_at DESC
            LIMIT :limit
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':content_id', $contentId, PDO::PARAM_INT);
        $stmt->bindValue(':category_id', $categoryId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * 检查用户是否点赞了内容
     */
    public function hasUserLiked($contentId, $userId)
    {
        if (!$userId) return false;

        $sql = "SELECT 1 FROM content_likes WHERE content_id = :content_id AND user_id = :user_id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':content_id', $contentId, PDO::PARAM_INT);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch() !== false;
    }

    /**
     * 切换点赞状态
     */
    public function toggleLike($contentId, $userId)
    {
        if (!$userId) {
            return ['success' => false, 'message' => '请先登录'];
        }

        try {
            $this->db->beginTransaction();

            // 检查是否已点赞
            $hasLiked = $this->hasUserLiked($contentId, $userId);

            if ($hasLiked) {
                // 取消点赞
                $sql = "DELETE FROM content_likes WHERE content_id = :content_id AND user_id = :user_id";
                $stmt = $this->db->prepare($sql);
                $stmt->bindValue(':content_id', $contentId, PDO::PARAM_INT);
                $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
                $stmt->execute();

                // 减少点赞数
                $sql = "UPDATE content SET likes = GREATEST(0, likes - 1) WHERE id = :id";
                $stmt = $this->db->prepare($sql);
                $stmt->bindValue(':id', $contentId, PDO::PARAM_INT);
                $stmt->execute();

                $action = 'unliked';
            } else {
                // 添加点赞
                $sql = "INSERT INTO content_likes (content_id, user_id, created_at) VALUES (:content_id, :user_id, NOW())";
                $stmt = $this->db->prepare($sql);
                $stmt->bindValue(':content_id', $contentId, PDO::PARAM_INT);
                $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
                $stmt->execute();

                // 增加点赞数
                $sql = "UPDATE content SET likes = likes + 1 WHERE id = :id";
                $stmt = $this->db->prepare($sql);
                $stmt->bindValue(':id', $contentId, PDO::PARAM_INT);
                $stmt->execute();

                $action = 'liked';
            }

            // 获取最新点赞数
            $sql = "SELECT likes FROM content WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':id', $contentId, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch();

            $this->db->commit();

            return [
                'success' => true,
                'action' => $action,
                'likes' => $result['likes']
            ];
        } catch (Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => '操作失败'];
        }
    }
}

// 处理AJAX请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    try {
        $database = new Database();
        $contentManager = new ContentManager($database);
        $currentUser = getCurrentUser();

        if ($_POST['action'] === 'toggle_like') {
            $contentId = intval($_POST['content_id']);
            $userId = $currentUser ? $currentUser['id'] : null;

            $result = $contentManager->toggleLike($contentId, $userId);
            echo json_encode($result);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => '操作失败']);
    }
    exit;
}

// 获取内容ID
$contentId = intval($_GET['id'] ?? 0);

if (!$contentId) {
    header('Location: main.php');
    exit;
}

try {
    $database = new Database();
    $contentManager = new ContentManager($database);

    // 获取内容详情
    $content = $contentManager->getContentById($contentId);

    if (!$content) {
        header('Location: main.php');
        exit;
    }

    // 增加浏览次数
    $contentManager->incrementViews($contentId);

    // 获取相关内容
    $relatedContent = $contentManager->getRelatedContent($contentId, $content['category_id']);

    // 获取当前用户信息
    $currentUser = getCurrentUser();

    // 检查是否已点赞
    $hasLiked = $contentManager->hasUserLiked($contentId, $currentUser ? $currentUser['id'] : null);

    // 处理内容数据
    $content['thumbnail_url'] = !empty($content['thumbnail']) ?
        'uploads/' . $content['thumbnail'] : 'audio/default-thumbnail.svg';
    $content['created_at_formatted'] = date('Y-m-d H:i', strtotime($content['created_at']));
    $content['views_formatted'] = number_format($content['views']);
    $content['likes_formatted'] = number_format($content['likes']);
    $content['author_avatar'] = !empty($content['avatar']) ?
        'uploads/avatars/' . $content['avatar'] : 'audio/default-avatar.svg';

    // 处理相关内容数据
    foreach ($relatedContent as &$item) {
        $item['thumbnail_url'] = !empty($item['thumbnail']) ?
            'uploads/' . $item['thumbnail'] : 'audio/default-thumbnail.svg';
        $item['views_formatted'] = number_format($item['views']);
    }
} catch (Exception $e) {
    $error = '内容加载失败，请稍后再试。';
}
?>
<!DOCTYPE html>
<html lang="zh-CN">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($content['title']); ?> - <?php echo SITE_NAME; ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($content['description']); ?>">
    <link rel="stylesheet" href="css/common.css">
    <link rel="stylesheet" href="css/main.css">
    <style>
        .content-header {
            background: var(--bg-secondary);
            padding: 2rem 0;
            border-bottom: 1px solid var(--border-color);
        }

        .content-meta {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }

        .author-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .author-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }

        .author-name {
            font-weight: 500;
            color: var(--text-primary);
        }

        .content-stats {
            display: flex;
            gap: 1rem;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .content-main {
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 2rem;
            margin: 2rem 0;
        }

        .content-body {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .content-image {
            width: 100%;
            max-height: 400px;
            object-fit: cover;
            border-radius: 10px;
            margin-bottom: 1.5rem;
        }

        .content-description {
            line-height: 1.6;
            color: var(--text-primary);
            margin-bottom: 2rem;
        }

        .content-actions {
            display: flex;
            gap: 1rem;
            padding: 1rem 0;
            border-top: 1px solid var(--border-color);
        }

        .action-btn {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border: 1px solid var(--border-color);
            background: white;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            color: var(--text-primary);
        }

        .action-btn:hover {
            background: var(--bg-secondary);
            transform: translateY(-1px);
        }

        .action-btn.liked {
            background: var(--accent-color);
            color: white;
            border-color: var(--accent-color);
        }

        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .sidebar-section {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .sidebar-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--text-primary);
        }

        .related-item {
            display: flex;
            gap: 0.75rem;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border-color);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .related-item:last-child {
            border-bottom: none;
        }

        .related-item:hover {
            background: var(--bg-secondary);
            margin: 0 -1rem;
            padding-left: 1rem;
            padding-right: 1rem;
            border-radius: 5px;
        }

        .related-thumbnail {
            width: 60px;
            height: 45px;
            object-fit: cover;
            border-radius: 5px;
            flex-shrink: 0;
        }

        .related-info {
            flex: 1;
            min-width: 0;
        }

        .related-title {
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 0.25rem;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .related-meta {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }

        .category-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
            color: white;
            margin-bottom: 1rem;
        }

        @media (max-width: 768px) {
            .content-main {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .content-body {
                padding: 1rem;
            }

            .content-meta {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }

            .content-actions {
                flex-wrap: wrap;
            }
        }
    </style>
</head>

<body>
    <header class="header">
        <div class="container">
            <div class="nav-brand">
                <a href="main.php"><?php echo SITE_NAME; ?></a>
            </div>
            <nav class="nav">
                <ul class="nav-links">
                    <li class="nav-link"><a href="main.php">首页</a></li>
                    <?php if ($currentUser): ?>
                        <li class="nav-link"><a href="home.php">个人中心</a></li>
                        <li class="nav-link"><a href="logout.php">退出</a></li>
                    <?php else: ?>
                        <li class="nav-link"><a href="login.php">登录</a></li>
                        <li class="nav-link"><a href="register.php">注册</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>

    <main class="main">
        <div class="container">
            <div class="content-header">
                <div class="content-meta">
                    <div class="author-info">
                        <img src="<?php echo htmlspecialchars($content['author_avatar']); ?>"
                            alt="<?php echo htmlspecialchars($content['display_name'] ?: $content['username']); ?>"
                            class="author-avatar">
                        <span class="author-name">
                            <?php echo htmlspecialchars($content['display_name'] ?: $content['username']); ?>
                        </span>
                    </div>
                    <div class="content-stats">
                        <span><?php echo $content['views_formatted']; ?> 次观看</span>
                        <span><?php echo $content['likes_formatted']; ?> 个赞</span>
                        <span><?php echo $content['created_at_formatted']; ?></span>
                    </div>
                </div>

                <?php if (!empty($content['category_name'])): ?>
                    <div class="category-badge"
                        style="background-color: <?php echo htmlspecialchars($content['category_color']); ?>">
                        <?php echo htmlspecialchars($content['category_name']); ?>
                    </div>
                <?php endif; ?>

                <h1><?php echo htmlspecialchars($content['title']); ?></h1>
            </div>

            <div class="content-main">
                <div class="content-body">
                    <?php if (!empty($content['thumbnail'])): ?>
                        <img src="<?php echo htmlspecialchars($content['thumbnail_url']); ?>"
                            alt="<?php echo htmlspecialchars($content['title']); ?>"
                            class="content-image">
                    <?php endif; ?>

                    <div class="content-description">
                        <?php echo nl2br(htmlspecialchars($content['description'])); ?>
                    </div>

                    <?php if (!empty($content['tags'])): ?>
                        <div class="content-tags">
                            <strong>标签：</strong>
                            <?php
                            $tags = explode(',', $content['tags']);
                            foreach ($tags as $tag):
                                $tag = trim($tag);
                                if (!empty($tag)):
                            ?>
                                    <a href="search.php?q=<?php echo urlencode($tag); ?>" class="tag">#<?php echo htmlspecialchars($tag); ?></a>
                            <?php
                                endif;
                            endforeach;
                            ?>
                        </div>
                    <?php endif; ?>

                    <div class="content-actions">
                        <button class="action-btn like-btn <?php echo $hasLiked ? 'liked' : ''; ?>"
                            onclick="toggleLike(<?php echo $content['id']; ?>)">
                            <span class="like-icon"><?php echo $hasLiked ? '❤️' : '🤍'; ?></span>
                            <span class="like-count"><?php echo $content['likes_formatted']; ?></span>
                        </button>

                        <button class="action-btn" onclick="shareContent()">
                            <span>📤</span>
                            分享
                        </button>

                        <?php if ($currentUser && $currentUser['id'] == $content['user_id']): ?>
                            <a href="edit_content.php?id=<?php echo $content['id']; ?>" class="action-btn">
                                <span>✏️</span>
                                编辑
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="sidebar">
                    <?php if (!empty($relatedContent)): ?>
                        <div class="sidebar-section">
                            <h3 class="sidebar-title">相关内容</h3>
                            <?php foreach ($relatedContent as $item): ?>
                                <div class="related-item" onclick="location.href='content.php?id=<?php echo $item['id']; ?>'">
                                    <img src="<?php echo htmlspecialchars($item['thumbnail_url']); ?>"
                                        alt="<?php echo htmlspecialchars($item['title']); ?>"
                                        class="related-thumbnail">
                                    <div class="related-info">
                                        <div class="related-title"><?php echo htmlspecialchars($item['title']); ?></div>
                                        <div class="related-meta">
                                            <?php echo htmlspecialchars($item['display_name'] ?: $item['username']); ?> •
                                            <?php echo $item['views_formatted']; ?> 次观看
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="sidebar-section">
                        <h3 class="sidebar-title">分类信息</h3>
                        <p><?php echo htmlspecialchars($content['category_description'] ?: '暂无描述'); ?></p>
                        <a href="search.php?category=<?php echo $content['category_id']; ?>" class="btn btn-primary">
                            浏览更多 <?php echo htmlspecialchars($content['category_name']); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        function toggleLike(contentId) {
            <?php if (!$currentUser): ?>
                alert('请先登录');
                return;
            <?php endif; ?>

            const likeBtn = document.querySelector('.like-btn');
            const likeIcon = likeBtn.querySelector('.like-icon');
            const likeCount = likeBtn.querySelector('.like-count');

            // 禁用按钮防止重复点击
            likeBtn.disabled = true;

            fetch('content.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=toggle_like&content_id=${contentId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (data.action === 'liked') {
                            likeBtn.classList.add('liked');
                            likeIcon.textContent = '❤️';
                        } else {
                            likeBtn.classList.remove('liked');
                            likeIcon.textContent = '🤍';
                        }
                        likeCount.textContent = new Intl.NumberFormat().format(data.likes);
                    } else {
                        alert(data.message || '操作失败');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('操作失败，请稍后再试');
                })
                .finally(() => {
                    likeBtn.disabled = false;
                });
        }

        function shareContent() {
            if (navigator.share) {
                navigator.share({
                    title: '<?php echo addslashes($content['title']); ?>',
                    text: '<?php echo addslashes($content['description']); ?>',
                    url: window.location.href
                });
            } else {
                // 复制链接到剪贴板
                navigator.clipboard.writeText(window.location.href).then(() => {
                    alert('链接已复制到剪贴板');
                }).catch(() => {
                    alert('分享失败');
                });
            }
        }
    </script>
</body>

</html>