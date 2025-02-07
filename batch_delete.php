<?php
session_start();

// 检查用户是否已登录
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// 检查是否有链接ID
if (!isset($_GET['ids'])) {
    $_SESSION['error'] = '请选择要删除的链接';
    header('Location: index.php');
    exit;
}

require_once 'db.php';

try {
    $ids = array_filter(explode(',', $_GET['ids']), 'is_numeric');
    if (empty($ids)) {
        throw new Exception('无效的链接ID');
    }
    
    $db = new DB();
    $placeholders = str_repeat('?,', count($ids) - 1) . '?';
    $count = $db->query(
        "DELETE FROM share_links WHERE id IN ($placeholders)",
        $ids
    );
    
    $_SESSION['success'] = "成功删除{$count}个链接";
} catch (Exception $e) {
    $_SESSION['error'] = '删除失败：' . $e->getMessage();
}

header('Location: index.php');
exit; 