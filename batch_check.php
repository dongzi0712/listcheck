<?php
session_start();

// 检查用户是否已登录
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// 检查是否有链接ID
if (!isset($_GET['ids'])) {
    $_SESSION['error'] = '请选择要检查的链接';
    header('Location: index.php');
    exit;
}

require_once 'check_single_link.php';

try {
    $ids = array_filter(explode(',', $_GET['ids']), 'is_numeric');
    if (empty($ids)) {
        throw new Exception('无效的链接ID');
    }
    
    $checker = new LinkChecker();
    $results = ['valid' => 0, 'invalid' => 0];
    
    foreach ($ids as $id) {
        $isValid = $checker->checkSingleLink($id);
        $results[$isValid ? 'valid' : 'invalid']++;
    }
    
    $_SESSION['success'] = sprintf(
        '检查完成：%d个有效，%d个失效',
        $results['valid'],
        $results['invalid']
    );
} catch (Exception $e) {
    $_SESSION['error'] = '检查失败：' . $e->getMessage();
}

header('Location: index.php');
exit; 