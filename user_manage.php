<?php
session_start();
require_once 'db.php';

// 检查用户是否已登录
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$db = new DB();
$config = require 'config.php';

// 处理API密钥重置
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'reset_api_key') {
        try {
            // 生成新的API密钥
            $newApiKey = bin2hex(random_bytes(16));
            
            // 更新配置文件
            $config['api_key'] = $newApiKey;
            file_put_contents('config.php', "<?php\nreturn " . var_export($config, true) . ";");
            
            $_SESSION['success'] = 'API密钥重置成功！';
        } catch (Exception $e) {
            $_SESSION['error'] = '重置失败：' . $e->getMessage();
        }
        header('Location: user_manage.php');
        exit;
    }
}

// 获取消息
$success = isset($_SESSION['success']) ? $_SESSION['success'] : '';
$error = isset($_SESSION['error']) ? $_SESSION['error'] : '';
unset($_SESSION['success']);
unset($_SESSION['error']);
?>

<!DOCTYPE html>
<html>
<head>
    <title>用户管理 - 网盘链接管理</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="shortcut icon" href="assets/favicon.ico" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/style.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-cloud me-2"></i>网盘链接管理
            </a>
            <div class="d-flex align-items-center">
                <a href="user_manage.php" class="btn btn-light btn-sm me-2">
                    <i class="bi bi-gear-fill me-1"></i>用户管理
                </a>
                <a href="logout.php" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-box-arrow-right me-1"></i>退出
                </a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?= htmlspecialchars($success) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">API密钥管理</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">当前API密钥</label>
                            <div class="input-group">
                                <input type="text" class="form-control" value="<?= htmlspecialchars($config['api_key']) ?>" readonly>
                                <button class="btn btn-outline-secondary copy-btn" type="button" data-clipboard-text="<?= htmlspecialchars($config['api_key']) ?>">
                                    <i class="bi bi-clipboard"></i>
                                </button>
                            </div>
                        </div>
                        <form method="post" onsubmit="return confirm('确定要重置API密钥吗？重置后需要更新所有使用API的客户端。')">
                            <input type="hidden" name="action" value="reset_api_key">
                            <button type="submit" class="btn btn-warning">
                                <i class="bi bi-arrow-clockwise me-1"></i>重置API密钥
                            </button>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">API使用说明</h5>
                    </div>
                    <div class="card-body">
                        <h6>认证方式</h6>
                        <p>在请求头中添加 <code>X-API-Key</code> 字段，值为API密钥。</p>
                        
                        <h6>接口列表</h6>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>接口</th>
                                        <th>方法</th>
                                        <th>说明</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><code>/api.php?action=add</code></td>
                                        <td>POST</td>
                                        <td>添加链接</td>
                                    </tr>
                                    <tr>
                                        <td><code>/api.php?action=delete&id={id}</code></td>
                                        <td>DELETE</td>
                                        <td>删除链接</td>
                                    </tr>
                                    <tr>
                                        <td><code>/api.php?action=list</code></td>
                                        <td>GET</td>
                                        <td>获取链接列表</td>
                                    </tr>
                                    <tr>
                                        <td><code>/api.php?action=get&id={id}</code></td>
                                        <td>GET</td>
                                        <td>获取单个链接</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <h6>示例代码</h6>
                        <div class="accordion" id="apiExamples">
                            <!-- 添加链接示例 -->
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#addExample">
                                        添加链接
                                    </button>
                                </h2>
                                <div id="addExample" class="accordion-collapse collapse" data-bs-parent="#apiExamples">
                                    <div class="accordion-body">
                                        <pre><code>curl -X POST http://your-domain/api.php?action=add \
  -H "X-API-Key: <?= htmlspecialchars($config['api_key']) ?>" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "测试分享",
    "url": "https://pan.baidu.com/s/xxx",
    "platform": "baidu"
  }'</code></pre>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- 删除链接示例 -->
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#deleteExample">
                                        删除链接
                                    </button>
                                </h2>
                                <div id="deleteExample" class="accordion-collapse collapse" data-bs-parent="#apiExamples">
                                    <div class="accordion-body">
                                        <pre><code>curl -X DELETE http://your-domain/api.php?action=delete&id=123 \
  -H "X-API-Key: <?= htmlspecialchars($config['api_key']) ?>"</code></pre>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- 获取链接列表示例 -->
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#listExample">
                                        获取链接列表
                                    </button>
                                </h2>
                                <div id="listExample" class="accordion-collapse collapse" data-bs-parent="#apiExamples">
                                    <div class="accordion-body">
                                        <pre><code>curl http://your-domain/api.php?action=list&page=1&limit=20 \
  -H "X-API-Key: <?= htmlspecialchars($config['api_key']) ?>"</code></pre>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- 获取单个链接示例 -->
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#getExample">
                                        获取单个链接
                                    </button>
                                </h2>
                                <div id="getExample" class="accordion-collapse collapse" data-bs-parent="#apiExamples">
                                    <div class="accordion-body">
                                        <pre><code>curl http://your-domain/api.php?action=get&id=123 \
  -H "X-API-Key: <?= htmlspecialchars($config['api_key']) ?>"</code></pre>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/clipboard@2.0.8/dist/clipboard.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // 初始化剪贴板
        new ClipboardJS('.copy-btn');
        
        // 添加复制成功提示
        document.querySelector('.copy-btn').addEventListener('click', function() {
            const icon = this.querySelector('i');
            icon.className = 'bi bi-check-lg';
            setTimeout(() => {
                icon.className = 'bi bi-clipboard';
            }, 2000);
        });
    });
    </script>
</body>
</html> 