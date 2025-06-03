<?php

/**
 * 搜索功能处理
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

// 搜索类
class ContentSearch
{
    private $db;

    public function __construct($database)
    {
        $this->db = $database->getConnection();
    }

    /**
     * 搜索内容
     */
    public function searchContent($query, $category = null, $limit = 20, $offset = 0)
    {
        $sql = "
            SELECT 
                c.*,
                u.username,
                u.display_name,
                cat.name as category_name,
                cat.color as category_color
            FROM content c
            LEFT JOIN users u ON c.user_id = u.id
            LEFT JOIN categories cat ON c.category_id = cat.id
            WHERE c.status = 'published'
        ";

        $params = [];

        // 添加搜索条件
        if (!empty($query)) {
            $sql .= " AND (c.title LIKE :query OR c.description LIKE :query OR c.tags LIKE :query)";
            $params['query'] = '%' . $query . '%';
        }

        // 添加分类过滤
        if (!empty($category)) {
            $sql .= " AND c.category_id = :category";
            $params['category'] = $category;
        }

        $sql .= " ORDER BY c.created_at DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);

        // 绑定参数
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * 获取搜索结果总数
     */
    public function getSearchCount($query, $category = null)
    {
        $sql = "
            SELECT COUNT(*) as total
            FROM content c
            WHERE c.status = 'published'
        ";

        $params = [];

        if (!empty($query)) {
            $sql .= " AND (c.title LIKE :query OR c.description LIKE :query OR c.tags LIKE :query)";
            $params['query'] = '%' . $query . '%';
        }

        if (!empty($category)) {
            $sql .= " AND c.category_id = :category";
            $params['category'] = $category;
        }

        $stmt = $this->db->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }

        $stmt->execute();
        $result = $stmt->fetch();
        return $result['total'];
    }

    /**
     * 获取热门搜索词
     */
    public function getPopularSearches($limit = 10)
    {
        // 这里可以实现搜索词统计功能
        // 暂时返回一些示例数据
        return [
            '视频教程',
            '音乐分享',
            '游戏攻略',
            '美食制作',
            '旅行日记',
            '科技资讯',
            '编程学习',
            '摄影技巧'
        ];
    }
}

// 处理AJAX搜索请求
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['ajax'])) {
    header('Content-Type: application/json');

    try {
        $database = new Database();
        $search = new ContentSearch($database);

        $query = $_GET['q'] ?? '';
        $category = $_GET['category'] ?? null;
        $page = max(1, intval($_GET['page'] ?? 1));
        $limit = 12;
        $offset = ($page - 1) * $limit;

        $results = $search->searchContent($query, $category, $limit, $offset);
        $total = $search->getSearchCount($query, $category);

        // 处理结果数据
        foreach ($results as &$item) {
            $item['thumbnail_url'] = !empty($item['thumbnail']) ?
                'uploads/' . $item['thumbnail'] : 'audio/default-thumbnail.svg';
            $item['created_at_formatted'] = date('Y-m-d H:i', strtotime($item['created_at']));
            $item['views_formatted'] = number_format($item['views']);
        }

        echo json_encode([
            'success' => true,
            'data' => $results,
            'total' => $total,
            'page' => $page,
            'pages' => ceil($total / $limit)
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => '搜索失败: ' . $e->getMessage()
        ]);
    }
    exit;
}

// 获取搜索参数
$query = $_GET['q'] ?? '';
$category = $_GET['category'] ?? null;
$page = max(1, intval($_GET['page'] ?? 1));

try {
    $database = new Database();
    $search = new ContentSearch($database);

    // 获取分类列表
    $stmt = $database->getConnection()->query("
        SELECT id, name, color, description 
        FROM categories 
        WHERE status = 'active' 
        ORDER BY sort_order, name
    ");
    $categories = $stmt->fetchAll();

    // 执行搜索
    $limit = 12;
    $offset = ($page - 1) * $limit;
    $results = $search->searchContent($query, $category, $limit, $offset);
    $total = $search->getSearchCount($query, $category);
    $totalPages = ceil($total / $limit);

    // 获取热门搜索
    $popularSearches = $search->getPopularSearches();

    // 处理结果数据
    foreach ($results as &$item) {
        $item['thumbnail_url'] = !empty($item['thumbnail']) ?
            'uploads/' . $item['thumbnail'] : 'audio/default-thumbnail.svg';
        $item['created_at_formatted'] = date('Y-m-d H:i', strtotime($item['created_at']));
        $item['views_formatted'] = number_format($item['views']);
    }
} catch (Exception $e) {
    $error = '搜索功能暂时不可用，请稍后再试。';
    $results = [];
    $total = 0;
    $totalPages = 0;
}

// 获取当前用户信息
$currentUser = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="zh-CN">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo !empty($query) ? '搜索: ' . htmlspecialchars($query) : '搜索'; ?> - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="css/common.css">
    <link rel="stylesheet" href="css/main.css">
    <style>
        .search-header {
            background: var(--primary-gradient);
            padding: 2rem 0;
            color: white;
        }

        .search-form {
            max-width: 600px;
            margin: 0 auto;
            display: flex;
            gap: 1rem;
        }

        .search-input {
            flex: 1;
            padding: 0.75rem 1rem;
            border: none;
            border-radius: 25px;
            font-size: 1rem;
        }

        .search-btn {
            padding: 0.75rem 1.5rem;
            background: var(--accent-color);
            color: white;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .search-btn:hover {
            background: var(--accent-hover);
            transform: translateY(-2px);
        }

        .search-filters {
            margin: 2rem 0;
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: center;
        }

        .filter-select {
            padding: 0.5rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: 20px;
            background: white;
        }

        .search-results {
            margin: 2rem 0;
        }

        .results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
        }

        .results-count {
            color: var(--text-secondary);
        }

        .popular-searches {
            background: var(--bg-secondary);
            padding: 1.5rem;
            border-radius: 10px;
            margin: 2rem 0;
        }

        .popular-tags {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-top: 1rem;
        }

        .popular-tag {
            padding: 0.25rem 0.75rem;
            background: var(--primary-color);
            color: white;
            text-decoration: none;
            border-radius: 15px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .popular-tag:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin: 2rem 0;
        }

        .page-btn {
            padding: 0.5rem 1rem;
            border: 1px solid var(--border-color);
            background: white;
            color: var(--text-primary);
            text-decoration: none;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        .page-btn:hover,
        .page-btn.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .no-results {
            text-align: center;
            padding: 3rem;
            color: var(--text-secondary);
        }

        .no-results-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
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

    <section class="search-header">
        <div class="container">
            <form class="search-form" method="GET">
                <input type="text" name="q" class="search-input"
                    placeholder="搜索内容、用户或标签..."
                    value="<?php echo htmlspecialchars($query); ?>">
                <button type="submit" class="search-btn">搜索</button>
            </form>
        </div>
    </section>

    <main class="main">
        <div class="container">
            <?php if (!empty($query) || !empty($category)): ?>
                <div class="search-filters">
                    <span>筛选条件：</span>
                    <select name="category" class="filter-select" onchange="filterByCategory(this.value)">
                        <option value="">所有分类</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>"
                                <?php echo $category == $cat['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="search-results">
                    <div class="results-header">
                        <h2>搜索结果</h2>
                        <span class="results-count">
                            <?php if (!empty($query)): ?>
                                "<?php echo htmlspecialchars($query); ?>" 的搜索结果，共 <?php echo $total; ?> 条
                            <?php else: ?>
                                共 <?php echo $total; ?> 条结果
                            <?php endif; ?>
                        </span>
                    </div>

                    <?php if (!empty($results)): ?>
                        <div class="content-grid">
                            <?php foreach ($results as $item): ?>
                                <div class="content-card" onclick="viewContent(<?php echo $item['id']; ?>)">
                                    <div class="card-image">
                                        <img src="<?php echo htmlspecialchars($item['thumbnail_url']); ?>"
                                            alt="<?php echo htmlspecialchars($item['title']); ?>">
                                        <?php if (!empty($item['category_name'])): ?>
                                            <span class="category-tag"
                                                style="background-color: <?php echo htmlspecialchars($item['category_color']); ?>">
                                                <?php echo htmlspecialchars($item['category_name']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="card-content">
                                        <h3 class="card-title"><?php echo htmlspecialchars($item['title']); ?></h3>
                                        <p class="card-description"><?php echo htmlspecialchars($item['description']); ?></p>
                                        <div class="card-meta">
                                            <span class="author">@<?php echo htmlspecialchars($item['display_name'] ?: $item['username']); ?></span>
                                            <span class="views"><?php echo $item['views_formatted']; ?> 次观看</span>
                                            <span class="date"><?php echo $item['created_at_formatted']; ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <?php if ($totalPages > 1): ?>
                            <div class="pagination">
                                <?php if ($page > 1): ?>
                                    <a href="?q=<?php echo urlencode($query); ?>&category=<?php echo $category; ?>&page=<?php echo $page - 1; ?>"
                                        class="page-btn">上一页</a>
                                <?php endif; ?>

                                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                    <a href="?q=<?php echo urlencode($query); ?>&category=<?php echo $category; ?>&page=<?php echo $i; ?>"
                                        class="page-btn <?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                                <?php endfor; ?>

                                <?php if ($page < $totalPages): ?>
                                    <a href="?q=<?php echo urlencode($query); ?>&category=<?php echo $category; ?>&page=<?php echo $page + 1; ?>"
                                        class="page-btn">下一页</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="no-results">
                            <div class="no-results-icon">🔍</div>
                            <h3>没有找到相关内容</h3>
                            <p>尝试使用不同的关键词或浏览其他分类</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if (empty($query) && empty($category)): ?>
                <div class="popular-searches">
                    <h3>热门搜索</h3>
                    <div class="popular-tags">
                        <?php foreach ($popularSearches as $tag): ?>
                            <a href="?q=<?php echo urlencode($tag); ?>" class="popular-tag"><?php echo htmlspecialchars($tag); ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        function filterByCategory(categoryId) {
            const url = new URL(window.location);
            if (categoryId) {
                url.searchParams.set('category', categoryId);
            } else {
                url.searchParams.delete('category');
            }
            url.searchParams.set('page', '1');
            window.location.href = url.toString();
        }

        function viewContent(contentId) {
            window.location.href = `content.php?id=${contentId}`;
        }

        // 搜索表单增强
        document.querySelector('.search-form').addEventListener('submit', function(e) {
            const input = this.querySelector('.search-input');
            if (!input.value.trim()) {
                e.preventDefault();
                input.focus();
            }
        });
    </script>
</body>

</html>