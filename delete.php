<?php
session_start();

// 检查用户是否已登录
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// 检查是否有链接ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = '无效的链接ID';
    header('Location: index.php');
    exit;
}

require_once 'db.php';

try {
    $db = new DB();
    $db->query("DELETE FROM share_links WHERE id = ?", [$_GET['id']]);
    $_SESSION['success'] = '链接删除成功';
} catch (Exception $e) {
    $_SESSION['error'] = '删除失败：' . $e->getMessage();
}

header('Location: index.php');
exit; 