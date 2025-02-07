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

require_once 'check_single_link.php';
require_once 'db.php';

try {
    $checker = new LinkChecker();
    $isValid = $checker->checkSingleLink($_GET['id']);
    
    // 更新链接状态
    $db = new DB();
    $db->query(
        "UPDATE share_links SET status = ?, last_check_time = NOW() WHERE id = ?",
        [$isValid ? 1 : 0, $_GET['id']]
    );
    
    if ($isValid) {
        $_SESSION['success'] = '链接检查完成：链接有效';
    } else {
        $_SESSION['error'] = '链接检查完成：链接已失效';
    }
} catch (Exception $e) {
    $_SESSION['error'] = '检查失败：' . $e->getMessage();
}

header('Location: index.php');
exit; 