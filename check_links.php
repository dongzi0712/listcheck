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
        
        // 获取所有链接（不仅仅是有效的链接）
        $links = $this->db->query("SELECT * FROM share_links");
        
        // 记录检查结果
        $results = [
            'total' => count($links),
            'checked' => 0,
            'valid' => 0,
            'invalid' => 0,
            'failed' => []
        ];
        
        // 随机打乱链接顺序
        shuffle($links);
        
        foreach ($links as $link) {
            try {
                // 随机延迟
                $delay = rand(
                    $config_map['check_delay_min'], 
                    $config_map['check_delay_max']
                );
                sleep($delay);
                
                $isValid = $this->checkLink($link);
                $results['checked']++;
                
                // 状态发生变化时才更新数据库和发送通知
                if ($isValid !== (bool)$link['status']) {
                    // 更新链接状态
                    $this->db->query(
                        "UPDATE share_links SET status = ?, last_check_time = NOW() WHERE id = ?",
                        [$isValid ? 1 : 0, $link['id']]
                    );
                    
                    // 如果链接从有效变为无效，发送通知
                    if (!$isValid && $link['status'] == 1) {
                        $this->sendNotification($link);
                        $results['failed'][] = $link;
                    }
                } else {
                    // 仅更新检查时间
                    $this->db->query(
                        "UPDATE share_links SET last_check_time = NOW() WHERE id = ?",
                        [$link['id']]
                    );
                }
                
                $isValid ? $results['valid']++ : $results['invalid']++;
                
            } catch (Exception $e) {
                error_log("检查链接失败 (ID: {$link['id']}): " . $e->getMessage());
            }
        }
        
        // 更新最后运行时间
        $this->db->query(
            "UPDATE system_config SET value = ? WHERE `key` = 'last_cron_time'",
            [time()]
        );
        
        // 输出检查结果
        echo "检查完成：\n";
        echo "总计：{$results['total']} 个链接\n";
        echo "已检查：{$results['checked']} 个\n";
        echo "有效：{$results['valid']} 个\n";
        echo "无效：{$results['invalid']} 个\n";
        
        if (!empty($results['failed'])) {
            echo "\n失效链接：\n";
            foreach ($results['failed'] as $link) {
                echo "- {$link['title']} ({$link['url']})\n";
            }
        }
    }
    
    private function checkLink($link) {
        // 根据不同平台使用不同的检测方法
        switch ($link['platform']) {
            case 'quark':
                return $this->checkQuarkLink($link['url']);
            case 'baidu':
                return $this->checkBaiduLink($this->getUrlContent($link['url']));
            default:
                // 其他平台使用通用检测方法
                $response = $this->getUrlContent($link['url']);
                return $response !== false;
        }
    }
    
    private function getUrlContent($url) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $httpCode === 200 ? $response : false;
    }
    
    private function checkQuarkLink($url) {
        // 从URL中提取pwd_id
        if (!preg_match('#https?://pan\.quark\.cn/s/([^/\s]+)#', $url, $matches)) {
            return false;
        }
        
        $pwd_id = $matches[1];
        $apiUrl = 'https://drive-h.quark.cn/1/clouddrive/share/sharepage/token';
        
        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(['pwd_id' => $pwd_id]),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                'Origin: https://pan.quark.cn',
                'Referer: https://pan.quark.cn/'
            ],
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // 404表示链接失效，200表示链接有效
        return $httpCode === 200;
    }
    
    private function checkBaiduLink($response) {
        if ($response === false) {
            return false;
        }
        
        // 百度网盘失效特征
        $invalidPatterns = [
            '啊哦，你来晚了，分享的文件已经被删除了，下次要早点哟',
            '链接不存在',
            '此链接分享内容可能因为涉及侵权、色情、反动、低俗等信息',
            '分享的文件已经被取消了',
            '分享的文件已经被删除了',
            '分享文件存在违规内容',
            '分享的文件已过期',
            '分享内容可能因为涉及侵权、色情、反动、低俗等信息，无法访问',
            '分享的内容已经被取消了',
            '分享的内容已经被删除了'
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