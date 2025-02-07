<?php
session_start();

// 安装状态检查
if (file_exists('install.lock')) {
    die('系统已经安装，如需重新安装请删除install.lock文件');
}

$step = isset($_GET['step']) ? intval($_GET['step']) : 1;
$error = '';
$success = '';

// 检查必要的PHP扩展
$requiredExtensions = ['pdo', 'pdo_mysql', 'curl'];
$missingExtensions = array_filter($requiredExtensions, function($ext) {
    return !extension_loaded($ext);
});

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($step) {
        case 1: // 数据库配置
            $dbHost = $_POST['db_host'];
            $dbName = $_POST['db_name'];
            $dbUser = $_POST['db_username'];
            $dbPass = $_POST['db_password'];
            
            try {
                // 测试数据库连接
                $dsn = "mysql:host=$dbHost;charset=utf8mb4";
                $pdo = new PDO($dsn, $dbUser, $dbPass);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                // 创建数据库
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
                
                // 保存数据库配置
                $config = [
                    'db' => [
                        'host' => $dbHost,
                        'dbname' => $dbName,
                        'username' => $dbUser,
                        'password' => $dbPass
                    ]
                ];
                
                file_put_contents('config.php', "<?php\nreturn " . var_export($config, true) . ";");
                
                // 导入数据库结构
                $pdo->exec("USE `$dbName`");
                $sql = file_get_contents('db.sql');
                $pdo->exec($sql);
                
                header('Location: install.php?step=2');
                exit;
            } catch (PDOException $e) {
                $error = '数据库配置错误：' . $e->getMessage();
            }
            break;
            
        case 2: // 管理员账号设置
            if (empty($_POST['username']) || empty($_POST['password'])) {
                $error = '请填写完整的管理员信息';
            } else {
                try {
                    require_once 'db.php';
                    $db = new DB();
                    
                    // 创建管理员账号
                    $username = trim($_POST['username']);
                    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    $wxpusher_token = trim($_POST['wxpusher_token']);
                    $wxpusher_uid = trim($_POST['wxpusher_uid']);
                    
                    // 检查用户表是否存在
                    try {
                        $db->query("SELECT 1 FROM users LIMIT 1");
                    } catch (PDOException $e) {
                        // 如果表不存在，重新执行数据库初始化
                        $config = require 'config.php';
                        $pdo = new PDO(
                            "mysql:host={$config['db']['host']};dbname={$config['db']['dbname']};charset=utf8mb4",
                            $config['db']['username'],
                            $config['db']['password']
                        );
                        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                        
                        // 重新导入数据库结构
                        $sql = file_get_contents('db.sql');
                        $pdo->exec($sql);
                    }
                    
                    // 先检查用户是否已存在
                    $existingUser = $db->query("SELECT id FROM users WHERE username = ?", [$username]);
                    if (!empty($existingUser)) {
                        throw new Exception('用户名已存在');
                    }
                    
                    // 创建管理员账号
                    $result = $db->query(
                        "INSERT INTO users (username, password) VALUES (?, ?)",
                        [$username, $password]
                    );
                    
                    // 更新配置文件
                    $config = require 'config.php';
                    $config['wxpusher'] = [
                        'app_token' => $wxpusher_token,
                        'uids' => [$wxpusher_uid]
                    ];
                    
                    // 检查配置文件是否可写
                    if (!is_writable('config.php')) {
                        throw new Exception('配置文件 config.php 不可写，请检查文件权限');
                    }
                    
                    // 保存配置
                    if (file_put_contents('config.php', "<?php\nreturn " . var_export($config, true) . ";") === false) {
                        throw new Exception('无法写入配置文件');
                    }
                    
                    // 检查是否可以创建锁定文件
                    if (!is_writable('.') && !file_exists('install.lock')) {
                        throw new Exception('无法创建安装锁定文件，请检查目录权限');
                    }
                    
                    // 创建安装锁定文件
                    if (file_put_contents('install.lock', date('Y-m-d H:i:s')) === false) {
                        throw new Exception('无法创建安装锁定文件');
                    }
                    
                    $success = '安装完成！';
                    
                } catch (Exception $e) {
                    $error = '创建管理员账号失败：' . $e->getMessage();
                    // 添加错误日志
                    error_log('Install Error: ' . $e->getMessage());
                }
            }
            break;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>安装 - 网盘链接管理</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-6">
                <div class="text-center mb-4">
                    <i class="bi bi-cloud text-primary" style="font-size: 3rem;"></i>
                    <h2 class="mt-2">网盘链接管理系统安装</h2>
                </div>

                <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error) ?>
                </div>
                <?php endif; ?>

                <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($success) ?>
                    <div class="mt-3">
                        <a href="login.php" class="btn btn-success">前往登录</a>
                    </div>
                </div>
                <?php else: ?>

                <div class="card">
                    <div class="card-header">
                        <div class="progress">
                            <div class="progress-bar" role="progressbar" style="width: <?= ($step/2)*100 ?>%"></div>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($missingExtensions)): ?>
                            <div class="alert alert-danger">
                                <h5>缺少必要的PHP扩展：</h5>
                                <ul class="mb-0">
                                    <?php foreach ($missingExtensions as $ext): ?>
                                        <li><?= htmlspecialchars($ext) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php else: ?>
                            <?php if ($step === 1): ?>
                                <h5 class="card-title mb-4">步骤1：数据库配置</h5>
                                <form method="post">
                                    <div class="mb-3">
                                        <label class="form-label">数据库主机</label>
                                        <input type="text" name="db_host" class="form-control" value="localhost" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">数据库名</label>
                                        <input type="text" name="db_name" class="form-control" value="netdisk_monitor" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">数据库用户名</label>
                                        <input type="text" name="db_username" class="form-control" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">数据库密码</label>
                                        <input type="password" name="db_password" class="form-control" required>
                                    </div>
                                    <button type="submit" class="btn btn-primary w-100">下一步</button>
                                </form>
                            <?php elseif ($step === 2): ?>
                                <h5 class="card-title mb-4">步骤2：管理员账号设置</h5>
                                <form method="post">
                                    <div class="mb-3">
                                        <label class="form-label">管理员用户名</label>
                                        <input type="text" name="username" class="form-control" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">管理员密码</label>
                                        <input type="password" name="password" class="form-control" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">WxPusher AppToken</label>
                                        <input type="text" name="wxpusher_token" class="form-control" required>
                                        <div class="form-text">在 <a href="https://wxpusher.zjiecode.com" target="_blank">WxPusher</a> 获取</div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">WxPusher UID</label>
                                        <input type="text" name="wxpusher_uid" class="form-control" required>
                                    </div>
                                    <button type="submit" class="btn btn-primary w-100">完成安装</button>
                                </form>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html> 