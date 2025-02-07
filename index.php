<?php
if (!file_exists('install.lock')) {
    header('Location: install.php');
    exit;
}

session_start();
require_once 'db.php';

// 检查用户是否已登录
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$db = new DB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    // 获取输入内容
    $content = isset($_POST['content']) ? trim($_POST['content']) : '';
    $platform = isset($_POST['platform']) ? trim($_POST['platform']) : '';
    
    // 验证数据
    $errors = [];
    if (empty($content)) {
        $errors[] = '内容不能为空';
    }
    if (empty($platform)) {
        $errors[] = '请选择网盘平台';
    }
    
    if (empty($errors)) {
        try {
            $title = '';
            $url = '';
            
            // 按行分割内容
            $lines = explode("\n", $content);
            $lines = array_map('trim', $lines);
            $lines = array_filter($lines); // 移除空行
            
            if ($platform === 'baidu') {
                // 处理百度网盘格式
                // 第一行为标题
                if (!empty($lines)) {
                    $title = array_shift($lines);
                }
                
                // 查找带有"链接："的行
                foreach ($lines as $line) {
                    if (preg_match('/链接：(https?:\/\/[^\s]+)/', $line, $matches)) {
                        $url = $matches[1];
                        break;
                    }
                }
            } elseif ($platform === 'quark') {
                // 处理夸克网盘格式
                // 匹配标题：「xxx」
                if (preg_match('/「([^」]+)」/', $content, $titleMatches)) {
                    $title = $titleMatches[1];
                }
                
                // 匹配链接：链接：http(s)://xxx
                if (preg_match('/链接：(https?:\/\/[^\s]+)/', $content, $urlMatches)) {
                    $url = $urlMatches[1];
                }
                
                // 如果没有找到标题，使用默认标题
                if (empty($title)) {
                    $title = '夸克网盘分享';
                }
            } else {
                // 其他平台的处理逻辑
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($title) && !filter_var($line, FILTER_VALIDATE_URL)) {
                        $title = $line;
                    } elseif (empty($url) && filter_var($line, FILTER_VALIDATE_URL)) {
                        $url = $line;
                    }
                }
            }
            
            // 验证提取的数据
            if (empty($url)) {
                throw new Exception('无法识别分享链接');
            }
            if (empty($title)) {
                $title = '网盘分享';
            }
            
            // 插入新链接
            $db->query(
                "INSERT INTO share_links (title, url, platform) VALUES (?, ?, ?)", 
                [$title, $url, $platform]
            );
            
            // 获取新插入的链接ID
            $newLinkId = $db->query("SELECT LAST_INSERT_ID() as id")[0]['id'];
            
            // 立即检查新添加的链接
            require_once 'check_single_link.php';
            $checker = new LinkChecker();
            $isValid = $checker->checkSingleLink($newLinkId);
            
            // 更新链接状态
            $db->query(
                "UPDATE share_links SET status = ?, last_check_time = NOW() WHERE id = ?",
                [$isValid ? 1 : 0, $newLinkId]
            );
            
            // 设置成功消息
            $_SESSION['success'] = '链接添加成功！';
            
            header('Location: index.php');
            exit;
        } catch (Exception $e) {
            $errors[] = '添加链接失败：' . $e->getMessage();
        }
    }
}

// 获取消息
$success = isset($_SESSION['success']) ? $_SESSION['success'] : '';
$error = isset($_SESSION['error']) ? $_SESSION['error'] : '';
unset($_SESSION['success']);
unset($_SESSION['error']);

// 处理配置更新
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_config') {
        $check_interval = intval($_POST['check_interval']);
        $check_delay_min = intval($_POST['check_delay_min']);
        $check_delay_max = intval($_POST['check_delay_max']);
        
        // 更新配置
        $db->query("UPDATE system_config SET value = ? WHERE `key` = 'check_interval'", [$check_interval]);
        $db->query("UPDATE system_config SET value = ? WHERE `key` = 'check_delay_min'", [$check_delay_min]);
        $db->query("UPDATE system_config SET value = ? WHERE `key` = 'check_delay_max'", [$check_delay_max]);
        
        header('Location: index.php');
        exit;
    }
}

// 获取当前配置
$configs = $db->query("SELECT * FROM system_config");
$config_map = [];
foreach ($configs as $config) {
    $config_map[$config['key']] = $config['value'];
}

// 获取搜索参数
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : 20;

// 验证每页显示数量
if (!in_array($per_page, [20, 50, 100])) {
    $per_page = 20;
}

// 计算偏移量
$offset = ($page - 1) * $per_page;

// 构建查询条件
$where = [];
$params = [];
if ($search !== '') {
    $where[] = "title LIKE ?";
    $params[] = "%{$search}%";
}

$whereClause = !empty($where) ? "WHERE " . implode(' AND ', $where) : '';

// 获取总记录数
if (!empty($params)) {
    $total = $db->query("SELECT COUNT(*) as count FROM share_links {$whereClause}", $params)[0]['count'];
} else {
    $total = $db->query("SELECT COUNT(*) as count FROM share_links")[0]['count'];
}

// 计算总页数
$total_pages = ceil($total / $per_page);

// 获取当前页的数据
if (!empty($params)) {
    // 有搜索条件时
    $sql = "SELECT * FROM share_links {$whereClause} ORDER BY create_time DESC LIMIT {$per_page} OFFSET {$offset}";
    $links = $db->query($sql, $params);
} else {
    // 无搜索条件时
    $sql = "SELECT * FROM share_links ORDER BY create_time DESC LIMIT {$per_page} OFFSET {$offset}";
    $links = $db->query($sql);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>网盘链接管理</title>
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
            <a class="navbar-brand" href="#">
                <i class="bi bi-cloud me-2"></i>网盘链接管理
            </a>
            <div class="d-flex align-items-center">
                <span class="text-light me-3">
                    <i class="bi bi-person-circle me-1"></i><?= htmlspecialchars($_SESSION['username']) ?>
                </span>
                <a href="logout.php" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-box-arrow-right me-1"></i>退出
                </a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="row">
            <div class="col-md-4 mb-4">
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-plus-circle me-2"></i>添加新链接
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
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($success) ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="post" id="shareForm">
                            <div class="mb-3">
                                <label class="form-label">分享内容</label>
                                <textarea name="content" id="shareContent" class="form-control" rows="5" required 
                                          placeholder="夸克网盘格式：&#10;「标题」&#10;链接：https://pan.quark.cn/s/xxx&#10;&#10;其他格式：&#10;标题&#10;链接"><?= isset($content) ? htmlspecialchars($content) : '' ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">网盘平台</label>
                                <select name="platform" id="platformSelect" class="form-control" required>
                                    <option value="">请选择平台</option>
                                    <option value="baidu" <?= isset($platform) && $platform === 'baidu' ? 'selected' : '' ?>>百度网盘</option>
                                    <option value="quark" <?= isset($platform) && $platform === 'quark' ? 'selected' : '' ?>>夸克网盘</option>
                                    <option value="aliyun" <?= isset($platform) && $platform === 'aliyun' ? 'selected' : '' ?>>阿里云盘</option>
                                    <option value="other" <?= isset($platform) && $platform === 'other' ? 'selected' : '' ?>>其他</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-plus-lg me-1"></i>添加
                            </button>
                        </form>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-header bg-white">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-gear me-2"></i>系统配置
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="post">
                            <input type="hidden" name="action" value="update_config">
                            <div class="mb-3">
                                <label class="form-label">检查间隔时间(秒)</label>
                                <input type="number" name="check_interval" class="form-control" 
                                       value="<?= htmlspecialchars($config_map['check_interval']) ?>" required min="300">
                                <div class="form-text">建议不小于300秒</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">最小延迟时间(秒)</label>
                                <input type="number" name="check_delay_min" class="form-control" 
                                       value="<?= htmlspecialchars($config_map['check_delay_min']) ?>" required min="1">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">最大延迟时间(秒)</label>
                                <input type="number" name="check_delay_max" class="form-control" 
                                       value="<?= htmlspecialchars($config_map['check_delay_max']) ?>" required min="1">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">定时任务状态</label>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="flex-grow-1">
                                        <?php
                                        $lastCronTime = intval($config_map['last_cron_time']);
                                        $timeDiff = time() - $lastCronTime;
                                        $status = '';
                                        
                                        if ($lastCronTime === 0) {
                                            $status = '<span class="text-warning">未运行</span>';
                                        } elseif ($timeDiff > 3600) {
                                            $status = '<span class="text-danger">异常</span>';
                                        } else {
                                            $status = '<span class="text-success">正常</span>';
                                        }
                                        
                                        echo '<div class="cron-status">';
                                        echo $status;
                                        if ($lastCronTime > 0) {
                                            echo ' (最后运行: ' . date('Y-m-d H:i:s', $lastCronTime) . ')';
                                        }
                                        echo '</div>';
                                        ?>
                                    </div>
                                    <button type="button" class="btn btn-outline-primary check-cron">
                                        <i class="bi bi-arrow-clockwise me-1"></i>检测
                                    </button>
                                </div>
                                <div class="form-text">显示定时任务是否正常运行</div>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-save me-1"></i>保存配置
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-link-45deg me-2"></i>链接列表
                            </h5>
                            <div class="btn-group">
                                <button type="button" class="btn btn-outline-primary btn-sm batch-check" disabled>
                                    <i class="bi bi-arrow-clockwise me-1"></i>批量检查
                                </button>
                                <button type="button" class="btn btn-outline-danger btn-sm batch-delete" disabled>
                                    <i class="bi bi-trash me-1"></i>批量删除
                                </button>
                            </div>
                        </div>
                        <div class="mt-3">
                            <form method="get" class="row g-2">
                                <div class="col-md-6">
                                    <div class="input-group">
                                        <input type="text" class="form-control" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="搜索标题...">
                                        <button class="btn btn-outline-secondary" type="submit">
                                            <i class="bi bi-search"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-auto">
                                    <select class="form-select" name="per_page" onchange="this.form.submit()">
                                        <option value="20" <?= $per_page == 20 ? 'selected' : '' ?>>20条/页</option>
                                        <option value="50" <?= $per_page == 50 ? 'selected' : '' ?>>50条/页</option>
                                        <option value="100" <?= $per_page == 100 ? 'selected' : '' ?>>100条/页</option>
                                    </select>
                                </div>
                            </form>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if ($error): ?>
                            <div class="alert alert-danger m-3">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error) ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success m-3">
                                <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($success) ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th style="width: 40px" class="text-center">
                                            <div class="form-check">
                                                <input class="form-check-input select-all" type="checkbox">
                                            </div>
                                        </th>
                                        <th style="width: 120px">标题</th>
                                        <th style="width: 200px">链接</th>
                                        <th style="width: 80px">平台</th>
                                        <th style="width: 60px" class="text-center">状态</th>
                                        <th style="width: 80px">检查时间</th>
                                        <th style="width: 110px">操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($links as $link): ?>
                                    <tr>
                                        <td class="text-center">
                                            <div class="form-check">
                                                <input class="form-check-input select-item" type="checkbox" value="<?= $link['id'] ?>">
                                            </div>
                                        </td>
                                        <td>
                                            <div class="link-title" title="<?= htmlspecialchars($link['title']) ?>">
                                                <?= htmlspecialchars($link['title']) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="link-url" title="<?= htmlspecialchars($link['url']) ?>">
                                                <a href="<?= htmlspecialchars($link['url']) ?>" target="_blank" class="text-decoration-none">
                                                    <i class="bi bi-box-arrow-up-right me-1"></i><?= htmlspecialchars($link['url']) ?>
                                                </a>
                                            </div>
                                        </td>
                                        <td>
                                            <?php
                                            switch($link['platform']) {
                                                case 'baidu':
                                                    $platformIcon = 'bi-cloud-fill text-primary';
                                                    break;
                                                case 'quark':
                                                    $platformIcon = 'bi-lightning-fill text-warning';
                                                    break;
                                                case 'aliyun':
                                                    $platformIcon = 'bi-cloud-fill text-info';
                                                    break;
                                                default:
                                                    $platformIcon = 'bi-question-circle-fill text-secondary';
                                            }
                                            ?>
                                            <div class="platform-label">
                                                <i class="bi <?= $platformIcon ?>"></i>
                                                <span class="text-truncate"><?= htmlspecialchars($link['platform']) ?></span>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <div class="status-wrapper">
                                                <span class="status-badge <?= $link['status'] ? 'status-valid' : 'status-invalid' ?>">
                                                    <?= $link['status'] ? '有效' : '失效' ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td class="text-nowrap time-cell">
                                            <?php 
                                            $checkTime = $link['last_check_time'] ?? null;
                                            if ($checkTime) {
                                                echo date('m-d H:i', strtotime($checkTime));
                                            } else {
                                                echo '<span class="text-muted">未检查</span>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="check.php?id=<?= $link['id'] ?>" class="btn btn-sm btn-outline-primary btn-check-link" title="检查">
                                                    <i class="bi bi-arrow-clockwise"></i>
                                                </a>
                                                <a href="edit.php?id=<?= $link['id'] ?>" class="btn btn-sm btn-outline-secondary" title="编辑">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <a href="delete.php?id=<?= $link['id'] ?>" class="btn btn-sm btn-outline-danger" 
                                                   onclick="return confirm('确定要删除这个链接吗？')" title="删除">
                                                    <i class="bi bi-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer bg-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="text-muted">
                                共 <?= $total ?> 条记录
                            </div>
                            <?php if ($total_pages > 1): ?>
                            <nav aria-label="Page navigation">
                                <ul class="pagination pagination-sm mb-0">
                                    <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=1<?= $search ? '&search='.urlencode($search) : '' ?>&per_page=<?= $per_page ?>">
                                            <i class="bi bi-chevron-double-left"></i>
                                        </a>
                                    </li>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?= $page-1 ?><?= $search ? '&search='.urlencode($search) : '' ?>&per_page=<?= $per_page ?>">
                                            <i class="bi bi-chevron-left"></i>
                                        </a>
                                    </li>
                                    <?php endif; ?>
                                    
                                    <?php
                                    $start = max(1, $page - 2);
                                    $end = min($total_pages, $page + 2);
                                    for ($i = $start; $i <= $end; $i++):
                                    ?>
                                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                        <a class="page-link" href="?page=<?= $i ?><?= $search ? '&search='.urlencode($search) : '' ?>&per_page=<?= $per_page ?>">
                                            <?= $i ?>
                                        </a>
                                    </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?= $page+1 ?><?= $search ? '&search='.urlencode($search) : '' ?>&per_page=<?= $per_page ?>">
                                            <i class="bi bi-chevron-right"></i>
                                        </a>
                                    </li>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?= $total_pages ?><?= $search ? '&search='.urlencode($search) : '' ?>&per_page=<?= $per_page ?>">
                                            <i class="bi bi-chevron-double-right"></i>
                                        </a>
                                    </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const selectAll = document.querySelector('.select-all');
        const selectItems = document.querySelectorAll('.select-item');
        const batchCheck = document.querySelector('.batch-check');
        const batchDelete = document.querySelector('.batch-delete');
        
        // 全选/取消全选
        selectAll.addEventListener('change', function() {
            selectItems.forEach(item => {
                item.checked = this.checked;
            });
            updateBatchButtons();
        });
        
        // 单个选择
        selectItems.forEach(item => {
            item.addEventListener('change', function() {
                const allChecked = Array.from(selectItems).every(item => item.checked);
                const anyChecked = Array.from(selectItems).some(item => item.checked);
                selectAll.checked = allChecked;
                updateBatchButtons();
            });
        });
        
        // 更新批量操作按钮状态
        function updateBatchButtons() {
            const checkedCount = Array.from(selectItems).filter(item => item.checked).length;
            batchCheck.disabled = checkedCount === 0;
            batchDelete.disabled = checkedCount === 0;
        }
        
        // 批量检查
        batchCheck.addEventListener('click', function() {
            const ids = Array.from(selectItems)
                .filter(item => item.checked)
                .map(item => item.value);
            
            if (ids.length > 0) {
                window.location.href = `batch_check.php?ids=${ids.join(',')}`;
            }
        });
        
        // 批量删除
        batchDelete.addEventListener('click', function() {
            const ids = Array.from(selectItems)
                .filter(item => item.checked)
                .map(item => item.value);
            
            if (ids.length > 0 && confirm('确定要删除选中的链接吗？')) {
                window.location.href = `batch_delete.php?ids=${ids.join(',')}`;
            }
        });
        
        // 添加自动识别平台功能
        const shareContent = document.getElementById('shareContent');
        const platformSelect = document.getElementById('platformSelect');
        
        // 自动识别平台
        function detectPlatform(content) {
            // 获取链接部分
            const linkMatch = content.match(/链接：(https?:\/\/[^\s]+)/);
            if (!linkMatch) {
                // 尝试直接匹配URL
                const urlMatch = content.match(/(https?:\/\/[^\s]+)/);
                if (!urlMatch) return;
                content = urlMatch[1].toLowerCase();
            } else {
                content = linkMatch[1].toLowerCase();
            }
            
            // 判断平台
            if (content.includes('pan.baidu.com')) {
                return 'baidu';
            } else if (content.includes('pan.quark.cn')) {
                return 'quark';
            } else if (content.includes('aliyundrive.com')) {
                return 'aliyun';
            }
            
            return 'other';
        }
        
        // 监听内容变化
        let timeout = null;
        shareContent.addEventListener('input', function() {
            // 使用防抖，避免频繁检测
            clearTimeout(timeout);
            timeout = setTimeout(() => {
                const content = this.value;
                const platform = detectPlatform(content);
                if (platform) {
                    platformSelect.value = platform;
                }
            }, 300);
        });
        
        // 监听粘贴事件
        shareContent.addEventListener('paste', function(e) {
            // 延迟执行，确保内容已经粘贴
            setTimeout(() => {
                const content = this.value;
                const platform = detectPlatform(content);
                if (platform) {
                    platformSelect.value = platform;
                }
            }, 0);
        });
        
        // 检测定时任务状态
        const checkCronBtn = document.querySelector('.check-cron');
        const cronStatus = document.querySelector('.cron-status');
        
        if (checkCronBtn) {
            checkCronBtn.addEventListener('click', function() {
                this.disabled = true;
                const icon = this.querySelector('i');
                icon.classList.add('spin');
                this.querySelector('i').classList.add('spin');
                
                fetch('check_cron.php')
                    .then(response => response.json())
                    .then(data => {
                        let statusHtml = '';
                        if (data.data.status === 'not_run') {
                            statusHtml = '<span class="text-warning">未运行</span>';
                        } else if (data.data.status === 'error') {
                            statusHtml = '<span class="text-danger">异常</span>';
                        } else {
                            statusHtml = '<span class="text-success">正常</span>';
                        }
                        
                        if (data.data.last_run) {
                            statusHtml += ` (最后运行: ${data.data.last_run})`;
                        }
                        
                        cronStatus.innerHTML = statusHtml;
                    })
                    .catch(error => {
                        console.error('检测失败:', error);
                        cronStatus.innerHTML = '<span class="text-danger">检测失败</span>';
                    })
                    .finally(() => {
                        this.disabled = false;
                        icon.classList.remove('spin');
                    });
            });
        }
    });
    </script>
</body>
</html> 