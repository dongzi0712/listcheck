<?php
return [
    'db' => [
        'host' => 'localhost',
        'dbname' => 'netdisk_monitor',
        'username' => 'root',
        'password' => 'your_password'
    ],
    'wxpusher' => [
        'app_token' => 'YOUR_WXPUSHER_APP_TOKEN',
        'uids' => ['YOUR_WXPUSHER_UID'] // 接收消息的用户UID
    ],
    'check_interval' => 7200, // 检查间隔时间(秒)
    'api_key' => 'YOUR_API_KEY' // 添加API密钥配置
]; 