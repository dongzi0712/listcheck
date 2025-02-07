<?php
// 确保脚本只能通过命令行运行
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from command line');
}

require_once 'db.php';

class LinkChecker {
    private $db;
    private $config;
    
    public function __construct() {
        $this->db = new DB();
        $this->config = require 'config.php';
    }
    
    public function checkLinks() {
        // 获取配置
        $configs = $this->db->query("SELECT * FROM system_config");
        $config_map = [];
        foreach ($configs as $config) {
            $config_map[$config['key']] = intval($config['value']);
        }
        
        // 获取所有有效链接
        $links = $this->db->query("SELECT * FROM share_links WHERE status = 1");
        
        // 随机打乱链接顺序
        shuffle($links);
        
        foreach ($links as $link) {
            // 随机延迟
            $delay = rand(
                $config_map['check_delay_min'], 
                $config_map['check_delay_max']
            );
            sleep($delay);
            
            $isValid = $this->checkLink($link);
            
            if (!$isValid) {
                // 更新链接状态
                $this->db->query(
                    "UPDATE share_links SET status = 0, last_check_time = NOW() WHERE id = ?",
                    [$link['id']]
                );
                
                // 发送通知
                $this->sendNotification($link);
            } else {
                $this->db->query(
                    "UPDATE share_links SET last_check_time = NOW() WHERE id = ?",
                    [$link['id']]
                );
            }
        }
    }
    
    private function checkLink($link) {
        $ch = curl_init($link['url']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // 如果链接无法访问，直接返回失效
        if ($httpCode !== 200) {
            return false;
        }
        
        switch ($link['platform']) {
            case 'baidu':
                return $this->checkBaiduLink($response);
            case 'quark':
                return $this->checkQuarkLink($response);
            default:
                return true; // 其他平台仅检查HTTP状态码
        }
    }
    
    private function checkBaiduLink($response) {
        // 百度网盘失效特征
        $invalidPatterns = [
            '啊哦，你来晚了，分享的文件已经被删除了，下次要早点哟',
            '链接不存在',
            '此链接分享内容可能因为涉及侵权、色情、反动、低俗等信息',
            '分享的文件已经被取消了',
            '分享的文件已经被删除了',
            '分享文件存在违规内容',
            '分享的文件已过期'
        ];
        
        foreach ($invalidPatterns as $pattern) {
            if (strpos($response, $pattern) !== false) {
                return false;
            }
        }
        
        return true;
    }
    
    private function checkQuarkLink($response) {
        // 夸克网盘失效特征
        $invalidPatterns = [
            '分享的文件已过期',
            '分享的文件已被删除',
            '分享的文件已被取消',
            '分享的文件不存在',
            '页面不存在',
            '分享内容已被删除'
        ];
        
        foreach ($invalidPatterns as $pattern) {
            if (strpos($response, $pattern) !== false) {
                return false;
            }
        }
        
        return true;
    }
    
    private function sendNotification($link) {
        $content = "网盘链接失效提醒\n\n";
        $content .= "标题：{$link['title']}\n";
        $content .= "链接：{$link['url']}\n";
        $content .= "平台：{$link['platform']}\n";
        $content .= "失效时间：" . date('Y-m-d H:i:s') . "\n";
        
        $data = [
            'appToken' => $this->config['wxpusher']['app_token'],
            'content' => $content,
            'contentType' => 1,
            'uids' => $this->config['wxpusher']['uids'],
        ];
        
        $ch = curl_init('http://wxpusher.zjiecode.com/api/send/message');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_exec($ch);
        curl_close($ch);
    }
}

$checker = new LinkChecker();
$checker->checkLinks(); 