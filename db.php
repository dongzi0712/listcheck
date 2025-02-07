<?php
class DB {
    private $pdo;
    
    public function __construct() {
        $config = require 'config.php';
        $dsn = "mysql:host={$config['db']['host']};dbname={$config['db']['dbname']};charset=utf8mb4";
        $this->pdo = new PDO($dsn, $config['db']['username'], $config['db']['password']);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    
    public function query($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            
            // 只在有参数时执行参数绑定
            if (!empty($params)) {
                $stmt->execute($params);
            } else {
                $stmt->execute();
            }
            
            // 如果是SELECT语句，返回结果集
            if (stripos($sql, 'SELECT') === 0) {
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            // 对于INSERT、UPDATE、DELETE等语句，返回受影响的行数
            return $stmt->rowCount();
        } catch (PDOException $e) {
            // 记录详细错误信息
            error_log('Database Error: ' . $e->getMessage() . ' SQL: ' . $sql);
            throw $e;
        }
    }
} 