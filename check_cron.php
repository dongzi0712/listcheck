<?php
session_start();
require_once 'db.php';

// 检查用户是否已登录
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Unauthorized');
}

$db = new DB();
$lastCronTime = intval($db->query("SELECT value FROM system_config WHERE `key` = 'last_cron_time'")[0]['value']);
$timeDiff = time() - $lastCronTime;

$response = [
    'status' => 'success',
    'data' => [
        'last_run' => $lastCronTime > 0 ? date('Y-m-d H:i:s', $lastCronTime) : null,
        'status' => $lastCronTime === 0 ? 'not_run' : ($timeDiff > 3600 ? 'error' : 'normal'),
        'time_diff' => $timeDiff
    ]
];

header('Content-Type: application/json');
echo json_encode($response); 