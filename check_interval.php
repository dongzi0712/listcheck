<?php
require_once 'db.php';

// 确保脚本只能通过命令行运行
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from command line');
}

$db = new DB();

// 更新最后运行时间
$db->query("UPDATE system_config SET value = ? WHERE `key` = 'last_cron_time'", [time()]);

// 获取上次检查时间和检查间隔
$lastCheck = $db->query("SELECT MAX(last_check_time) as last_check FROM share_links")[0]['last_check'];
$checkInterval = intval($db->query("SELECT value FROM system_config WHERE `key` = 'check_interval'")[0]['value']);

if ($lastCheck) {
    $timeSinceLastCheck = time() - strtotime($lastCheck);
    if ($timeSinceLastCheck >= $checkInterval) {
        // 执行检查
        require_once 'check_links.php';
    }
} else {
    // 首次运行
    require_once 'check_links.php';
} 