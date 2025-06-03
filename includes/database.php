<?php

/**
 * 数据库连接单例类
 * 避免重复创建数据库连接
 */

require_once dirname(__DIR__) . '/config.php';

class Database
{
    private static $instance = null;
    private $pdo;

    private function __construct()
    {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
            ];
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("数据库连接失败");
        }
    }

    /**
     * 获取数据库实例
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 获取PDO连接
     */
    public function getConnection()
    {
        return $this->pdo;
    }

    /**
     * 防止克隆
     */
    private function __clone() {}

    /**
     * 防止反序列化
     */
    public function __wakeup()
    {
        throw new Exception("Cannot unserialize singleton");
    }

    /**
     * 执行查询并返回结果
     */
    public function query($sql, $params = [])
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Database query failed: " . $e->getMessage());
            throw new Exception("数据库查询失败");
        }
    }

    /**
     * 获取最后插入的ID
     */
    public function lastInsertId()
    {
        return $this->pdo->lastInsertId();
    }

    /**
     * 开始事务
     */
    public function beginTransaction()
    {
        return $this->pdo->beginTransaction();
    }

    /**
     * 提交事务
     */
    public function commit()
    {
        return $this->pdo->commit();
    }

    /**
     * 回滚事务
     */
    public function rollback()
    {
        return $this->pdo->rollback();
    }
}
