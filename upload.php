<?php
session_start();

// 引入认证检查
require_once 'includes/auth_check.php';
require_once 'login.php';

// 检查登录状态
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => '请先登录']);
    exit;
}

// 只处理POST请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => '方法不允许']);
    exit;
}

try {
    // 验证输入
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = $_POST['category'] ?? 'other';

    if (empty($title)) {
        throw new Exception('标题不能为空');
    }

    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('文件上传失败');
    }

    $file = $_FILES['file'];

    // 验证文件类型和大小
    $allowedTypes = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'video/mp4',
        'video/webm',
        'video/ogg',
        'application/pdf',
        'text/plain'
    ];

    if (!in_array($file['type'], $allowedTypes)) {
        throw new Exception('不支持的文件类型');
    }

    // 限制文件大小 (50MB)
    $maxSize = 50 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        throw new Exception('文件大小不能超过50MB');
    }

    // 创建上传目录
    $uploadDir = 'uploads/' . date('Y/m/');
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // 生成唯一文件名
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '_' . time() . '.' . $extension;
    $filepath = $uploadDir . $filename;

    // 移动文件
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception('文件保存失败');
    }

    // 保存到数据库
    $db = (new Database())->getConnection();

    $stmt = $db->prepare("
        INSERT INTO content (user_id, title, description, file_path, file_type, file_size, category, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, 'published')
    ");

    $stmt->execute([
        $_SESSION['user_id'],
        $title,
        $description,
        $filepath,
        $file['type'],
        $file['size'],
        $category
    ]);

    $contentId = $db->lastInsertId();

    // 记录文件上传信息
    $stmt = $db->prepare("
        INSERT INTO file_uploads (user_id, content_id, original_name, file_path, file_size, mime_type) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $_SESSION['user_id'],
        $contentId,
        $file['name'],
        $filepath,
        $file['size'],
        $file['type']
    ]);

    echo json_encode([
        'success' => true,
        'message' => '上传成功',
        'content_id' => $contentId,
        'file_path' => $filepath
    ]);
} catch (Exception $e) {
    // 如果数据库操作失败，删除已上传的文件
    if (isset($filepath) && file_exists($filepath)) {
        unlink($filepath);
    }

    error_log("Upload error: " . $e->getMessage());

    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
