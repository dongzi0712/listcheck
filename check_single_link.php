<?php
require_once 'db.php';

class LinkChecker {
    private $db;
    private $config;
    
    public function __construct() {
        $this->db = new DB();
        $this->config = require 'config.php';
    }
    
    public function checkSingleLink($linkId) {
        // 获取链接信息
        $links = $this->db->query("SELECT * FROM share_links WHERE id = ?", [$linkId]);
        if (empty($links)) {
            return false;
        }
        
        $link = $links[0];
        
        // 如果链接已经失效，不再检查
        if ($link['status'] === 0) {
            return false;
        }
        
        $isValid = $this->checkLink($link);
        
        if (!$isValid) {
            // 发送通知
            $this->sendNotification($link);
        }
        
        return $isValid;
    }
    
    private function checkLink($link) {
        if ($link['platform'] === 'quark') {
            return $this->checkQuarkLink($link['url']);
        }
        
        $ch = curl_init($link['url']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            return false;
        }
        
        switch ($link['platform']) {
            case 'baidu':
                return $this->checkBaiduLink($response);
            default:
                return true;
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
    
    private function checkQuarkLink($url) {
        // 从URL中提取pwd_id
        if (!preg_match('#https?://pan\.quark\.cn/s/([^/\s]+)#', $url, $matches)) {
            return false;
        }
        
        $pwd_id = $matches[1];
        $apiUrl = 'https://drive-h.quark.cn/1/clouddrive/share/sharepage/token';
        
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['pwd_id' => $pwd_id]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'Origin: https://pan.quark.cn',
            'Referer: https://pan.quark.cn/'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // 404表示链接失效，200表示链接有效
        return $httpCode === 200;
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
        curl_setopt($ch, CURLOPT_TIMEOUT, 5); // 设置超时时间
        curl_exec($ch);
        curl_close($ch);
    }
} 