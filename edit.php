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
$db = new DB();

// 获取链接信息
$links = $db->query("SELECT * FROM share_links WHERE id = ?", [$_GET['id']]);
if (empty($links)) {
    $_SESSION['error'] = '链接不存在';
    header('Location: index.php');
    exit;
}
$link = $links[0];

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $url = isset($_POST['url']) ? trim($_POST['url']) : '';
    $platform = isset($_POST['platform']) ? trim($_POST['platform']) : '';
    $status = isset($_POST['status']) ? intval($_POST['status']) : 1;
    $last_check_time = isset($_POST['last_check_time']) ? trim($_POST['last_check_time']) : null;
    
    $errors = [];
    if (empty($title)) {
        $errors[] = '标题不能为空';
    }
    if (empty($url)) {
        $errors[] = '链接不能为空';
    } elseif (!filter_var($url, FILTER_VALIDATE_URL)) {
        $errors[] = '请输入有效的URL';
    }
    if (empty($platform)) {
        $errors[] = '请选择网盘平台';
    }
    
    if (empty($errors)) {
        try {
            $db->query(
                "UPDATE share_links SET title = ?, url = ?, platform = ?, status = ?, last_check_time = ? WHERE id = ?",
                [$title, $url, $platform, $status, $last_check_time ?: null, $_GET['id']]
            );
            $_SESSION['success'] = '链接更新成功';
            header('Location: index.php');
            exit;
        } catch (Exception $e) {
            $errors[] = '更新失败：' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>编辑链接 - 网盘链接管理</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="shortcut icon" href="assets/favicon.ico" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/style.css" rel="stylesheet">
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-cloud me-2"></i>网盘链接管理
            </a>
        </div>
    </nav>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-pencil-square me-2"></i>编辑链接
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?= htmlspecialchars($error) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <form method="post">
                            <div class="mb-3">
                                <label class="form-label">分享标题</label>
                                <input type="text" name="title" class="form-control" 
                                       value="<?= htmlspecialchars($link['title']) ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">分享链接</label>
                                <input type="url" name="url" class="form-control" 
                                       value="<?= htmlspecialchars($link['url']) ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">网盘平台</label>
                                <select name="platform" class="form-control" required>
                                    <option value="">请选择平台</option>
                                    <option value="baidu" <?= $link['platform'] === 'baidu' ? 'selected' : '' ?>>百度网盘</option>
                                    <option value="quark" <?= $link['platform'] === 'quark' ? 'selected' : '' ?>>夸克网盘</option>
                                    <option value="aliyun" <?= $link['platform'] === 'aliyun' ? 'selected' : '' ?>>阿里云盘</option>
                                    <option value="other" <?= $link['platform'] === 'other' ? 'selected' : '' ?>>其他</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">链接状态</label>
                                <select name="status" class="form-control" required>
                                    <option value="1" <?= $link['status'] == 1 ? 'selected' : '' ?>>有效</option>
                                    <option value="0" <?= $link['status'] == 0 ? 'selected' : '' ?>>失效</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">最后检查时间</label>
                                <input type="datetime-local" name="last_check_time" class="form-control" 
                                       value="<?= $link['last_check_time'] ? date('Y-m-d\TH:i', strtotime($link['last_check_time'])) : '' ?>">
                            </div>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save me-1"></i>保存更改
                                </button>
                                <a href="index.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-x-lg me-1"></i>取消
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 