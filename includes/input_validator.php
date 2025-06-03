<?php

/**
 * 输入验证工具类
 * 提供各种输入验证和清理功能
 */

class InputValidator
{
    /**
     * 验证并清理字符串输入
     */
    public static function sanitizeString($input, $maxLength = null)
    {
        if (!is_string($input)) {
            return '';
        }

        $cleaned = trim($input);
        $cleaned = htmlspecialchars($cleaned, ENT_QUOTES, 'UTF-8');

        if ($maxLength && strlen($cleaned) > $maxLength) {
            $cleaned = substr($cleaned, 0, $maxLength);
        }

        return $cleaned;
    }

    /**
     * 验证邮箱格式
     */
    public static function validateEmail($email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * 验证用户名格式（只允许字母、数字、下划线）
     */
    public static function validateUsername($username)
    {
        return preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username);
    }

    /**
     * 验证密码强度
     */
    public static function validatePassword($password)
    {
        // 至少8位，包含字母和数字
        return strlen($password) >= 8 && preg_match('/[A-Za-z]/', $password) && preg_match('/[0-9]/', $password);
    }

    /**
     * 验证整数
     */
    public static function validateInteger($value, $min = null, $max = null)
    {
        if (!is_numeric($value)) {
            return false;
        }

        $int = (int)$value;

        if ($min !== null && $int < $min) {
            return false;
        }

        if ($max !== null && $int > $max) {
            return false;
        }

        return $int;
    }

    /**
     * 验证URL格式
     */
    public static function validateUrl($url)
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * 验证文件类型
     */
    public static function validateFileType($filename, $allowedTypes)
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return in_array($extension, $allowedTypes);
    }

    /**
     * 验证文件大小
     */
    public static function validateFileSize($fileSize, $maxSize)
    {
        return $fileSize <= $maxSize;
    }

    /**
     * 清理HTML内容（保留安全标签）
     */
    public static function sanitizeHtml($html)
    {
        $allowedTags = '<p><br><strong><em><u><a><ul><ol><li><h1><h2><h3><h4><h5><h6>';
        return strip_tags($html, $allowedTags);
    }

    /**
     * 验证分页参数
     */
    public static function validatePagination($page, $limit = 20)
    {
        $page = self::validateInteger($page, 1);
        $limit = self::validateInteger($limit, 1, 100);

        return [
            'page' => $page ?: 1,
            'limit' => $limit ?: 20
        ];
    }

    /**
     * 验证搜索关键词
     */
    public static function validateSearchQuery($query)
    {
        $query = self::sanitizeString($query, 100);

        // 移除特殊字符，只保留字母、数字、空格和中文
        $query = preg_replace('/[^\w\s\x{4e00}-\x{9fff}]/u', '', $query);

        return trim($query);
    }

    /**
     * 验证重定向URL（防止开放重定向）
     */
    public static function validateRedirectUrl($url, $allowedDomains = [])
    {
        // 如果是相对路径，直接返回
        if (strpos($url, '/') === 0 && strpos($url, '//') !== 0) {
            return $url;
        }

        // 解析URL
        $parsed = parse_url($url);
        if (!$parsed || !isset($parsed['host'])) {
            return false;
        }

        // 检查是否在允许的域名列表中
        if (!empty($allowedDomains)) {
            return in_array($parsed['host'], $allowedDomains) ? $url : false;
        }

        // 默认只允许当前域名
        $currentHost = $_SERVER['HTTP_HOST'] ?? '';
        return $parsed['host'] === $currentHost ? $url : false;
    }
}
