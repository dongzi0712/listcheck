<?php
header('Content-Type: application/json');

require_once 'db.php';

// API密钥验证
function validateApiKey() {
    $config = require 'config.php';
    $headers = getallheaders();
    $apiKey = $headers['X-API-Key'] ?? '';
    return $apiKey === $config['api_key'];
}

// 响应函数
function response($success, $data = null, $message = '') {
    echo json_encode([
        'success' => $success,
        'data' => $data,
        'message' => $message
    ]);
    exit;
}

// 验证API密钥
if (!validateApiKey()) {
    response(false, null, '无效的API密钥');
}

$db = new DB();

// 获取请求方法和操作
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($method) {
        case 'POST':
            // 添加链接
            if ($action === 'add') {
                $data = json_decode(file_get_contents('php://input'), true);
                
                // 验证必要参数
                if (empty($data['title']) || empty($data['url']) || empty($data['platform'])) {
                    response(false, null, '缺少必要参数');
                }
                
                // 插入链接
                $db->query(
                    "INSERT INTO share_links (title, url, platform) VALUES (?, ?, ?)",
                    [$data['title'], $data['url'], $data['platform']]
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
                
                response(true, ['id' => $newLinkId], '链接添加成功');
            }
            break;
            
        case 'DELETE':
            // 删除链接
            if ($action === 'delete') {
                $id = $_GET['id'] ?? 0;
                if (!$id) {
                    response(false, null, '缺少链接ID');
                }
                
                $result = $db->query("DELETE FROM share_links WHERE id = ?", [$id]);
                if ($result) {
                    response(true, null, '链接删除成功');
                } else {
                    response(false, null, '链接不存在');
                }
            }
            break;
            
        case 'GET':
            // 获取链接列表
            if ($action === 'list') {
                $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
                $limit = isset($_GET['limit']) ? min(100, max(1, intval($_GET['limit']))) : 20;
                $offset = ($page - 1) * $limit;
                
                // 获取总数
                $total = $db->query("SELECT COUNT(*) as count FROM share_links")[0]['count'];
                
                // 获取链接列表
                $links = $db->query(
                    "SELECT * FROM share_links ORDER BY create_time DESC LIMIT ? OFFSET ?",
                    [$limit, $offset]
                );
                
                response(true, [
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit,
                    'links' => $links
                ]);
            }
            // 获取单个链接
            elseif ($action === 'get') {
                $id = $_GET['id'] ?? 0;
                if (!$id) {
                    response(false, null, '缺少链接ID');
                }
                
                $link = $db->query("SELECT * FROM share_links WHERE id = ?", [$id]);
                if ($link) {
                    response(true, $link[0]);
                } else {
                    response(false, null, '链接不存在');
                }
            }
            break;
            
        default:
            response(false, null, '不支持的请求方法');
    }
} catch (Exception $e) {
    response(false, null, $e->getMessage());
} 