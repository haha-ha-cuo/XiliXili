-- XiliXili数据库初始化脚本
-- 创建数据库
CREATE DATABASE IF NOT EXISTS xilixili CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE xilixili;

-- 用户表
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    nickname VARCHAR(100),
    avatar VARCHAR(255) DEFAULT NULL,
    bio TEXT,
    status ENUM('active', 'inactive', 'banned') DEFAULT 'active',
    email_verified BOOLEAN DEFAULT FALSE,
    email_verification_token VARCHAR(255) DEFAULT NULL,
    password_reset_token VARCHAR(255) DEFAULT NULL,
    password_reset_expires DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    login_count INT DEFAULT 0,
    
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB;

-- 会话表
CREATE TABLE IF NOT EXISTS sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(128) NOT NULL UNIQUE,
    user_id INT NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_session_id (session_id),
    INDEX idx_user_id (user_id),
    INDEX idx_last_activity (last_activity)
) ENGINE=InnoDB;

-- 内容分类表
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    icon VARCHAR(255),
    sort_order INT DEFAULT 0,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_status (status),
    INDEX idx_sort_order (sort_order)
) ENGINE=InnoDB;

-- 内容表
CREATE TABLE IF NOT EXISTS content (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    category_id INT,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    content_type ENUM('video', 'image', 'audio', 'document') NOT NULL,
    file_path VARCHAR(500),
    file_size BIGINT DEFAULT 0,
    thumbnail VARCHAR(500),
    duration INT DEFAULT 0, -- 视频/音频时长（秒）
    view_count INT DEFAULT 0,
    like_count INT DEFAULT 0,
    comment_count INT DEFAULT 0,
    share_count INT DEFAULT 0,
    status ENUM('draft', 'published', 'private', 'deleted') DEFAULT 'draft',
    featured BOOLEAN DEFAULT FALSE,
    tags JSON,
    metadata JSON, -- 存储额外的元数据
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    published_at TIMESTAMP NULL,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_category_id (category_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    INDEX idx_published_at (published_at),
    INDEX idx_view_count (view_count),
    INDEX idx_like_count (like_count),
    INDEX idx_featured (featured),
    FULLTEXT idx_title_description (title, description)
) ENGINE=InnoDB;

-- 用户关注表
CREATE TABLE IF NOT EXISTS user_follows (
    id INT AUTO_INCREMENT PRIMARY KEY,
    follower_id INT NOT NULL,
    following_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (follower_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (following_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_follow (follower_id, following_id),
    INDEX idx_follower (follower_id),
    INDEX idx_following (following_id)
) ENGINE=InnoDB;

-- 点赞表
CREATE TABLE IF NOT EXISTS content_likes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    content_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (content_id) REFERENCES content(id) ON DELETE CASCADE,
    UNIQUE KEY unique_like (user_id, content_id),
    INDEX idx_user_id (user_id),
    INDEX idx_content_id (content_id)
) ENGINE=InnoDB;

-- 评论表
CREATE TABLE IF NOT EXISTS comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    content_id INT NOT NULL,
    parent_id INT DEFAULT NULL, -- 用于回复评论
    comment_text TEXT NOT NULL,
    like_count INT DEFAULT 0,
    status ENUM('active', 'hidden', 'deleted') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (content_id) REFERENCES content(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES comments(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_content_id (content_id),
    INDEX idx_parent_id (parent_id),
    INDEX idx_created_at (created_at),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- 文件上传表
CREATE TABLE IF NOT EXISTS file_uploads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size BIGINT NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    file_hash VARCHAR(64), -- 用于去重
    upload_status ENUM('uploading', 'completed', 'failed') DEFAULT 'uploading',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_file_hash (file_hash),
    INDEX idx_upload_status (upload_status)
) ENGINE=InnoDB;

-- 系统通知表
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('like', 'comment', 'follow', 'system') NOT NULL,
    title VARCHAR(200) NOT NULL,
    content TEXT,
    related_id INT DEFAULT NULL, -- 关联的内容ID
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_type (type),
    INDEX idx_is_read (is_read),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB;

-- 插入默认分类数据
INSERT INTO categories (name, description, icon, sort_order) VALUES
('视频', '视频内容分享', 'video.svg', 1),
('音乐', '音乐作品分享', 'music.svg', 2),
('图片', '图片作品展示', 'image.svg', 3),
('文档', '文档资料分享', 'document.svg', 4),
('教程', '学习教程内容', 'tutorial.svg', 5),
('生活', '生活日常分享', 'life.svg', 6),
('科技', '科技相关内容', 'tech.svg', 7),
('游戏', '游戏相关内容', 'game.svg', 8);

-- 插入测试用户数据（密码都是 123456）
INSERT INTO users (username, email, password_hash, nickname, status, email_verified) VALUES
('admin', 'admin@xilixili.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '管理员', 'active', TRUE),
('testuser', 'test@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '测试用户', 'active', TRUE),
('creator1', 'creator1@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '创作者一号', 'active', TRUE);

-- 插入测试内容数据
INSERT INTO content (user_id, category_id, title, description, content_type, status, view_count, like_count, published_at) VALUES
(2, 1, '创意视频标题', '这是一个很有创意的视频内容描述', 'video', 'published', 12000, 150, NOW()),
(3, 2, '旅行日志', '记录美好的旅行时光', 'video', 'published', 8500, 89, DATE_SUB(NOW(), INTERVAL 7 DAY)),
(2, 5, '学习教程', '详细的学习教程内容', 'video', 'published', 5600, 234, DATE_SUB(NOW(), INTERVAL 3 DAY)),
(3, 6, '美食制作', '分享美食制作过程', 'video', 'published', 3200, 67, DATE_SUB(NOW(), INTERVAL 1 DAY));

-- 创建清理过期会话的存储过程
DELIMITER //
CREATE PROCEDURE CleanExpiredSessions()
BEGIN
    DELETE FROM sessions WHERE last_activity < DATE_SUB(NOW(), INTERVAL 24 HOUR);
END //
DELIMITER ;

-- 创建更新内容统计的存储过程
DELIMITER //
CREATE PROCEDURE UpdateContentStats(IN content_id INT)
BEGIN
    UPDATE content SET 
        like_count = (SELECT COUNT(*) FROM content_likes WHERE content_likes.content_id = content.id),
        comment_count = (SELECT COUNT(*) FROM comments WHERE comments.content_id = content.id AND status = 'active')
    WHERE id = content_id;
END //
DELIMITER ;

-- 创建视图：用户统计信息
CREATE VIEW user_stats AS
SELECT 
    u.id,
    u.username,
    u.nickname,
    COUNT(DISTINCT c.id) as content_count,
    SUM(c.view_count) as total_views,
    SUM(c.like_count) as total_likes,
    COUNT(DISTINCT f.follower_id) as follower_count,
    COUNT(DISTINCT f2.following_id) as following_count
FROM users u
LEFT JOIN content c ON u.id = c.user_id AND c.status = 'published'
LEFT JOIN user_follows f ON u.id = f.following_id
LEFT JOIN user_follows f2 ON u.id = f2.follower_id
GROUP BY u.id, u.username, u.nickname;

-- 创建视图：热门内容
CREATE VIEW popular_content AS
SELECT 
    c.*,
    u.username,
    u.nickname,
    cat.name as category_name,
    (c.view_count * 0.3 + c.like_count * 0.5 + c.comment_count * 0.2) as popularity_score
FROM content c
JOIN users u ON c.user_id = u.id
LEFT JOIN categories cat ON c.category_id = cat.id
WHERE c.status = 'published'
ORDER BY popularity_score DESC;

COMMIT;