# 网盘链接管理系统

一个简单的网盘分享链接管理系统，支持多个网盘平台，可以自动检测链接有效性并通过微信推送通知。

## 功能特性

- 支持多个网盘平台
  - 百度网盘
  - 夸克网盘
  - 阿里云盘
  - 其他自定义平台
- 自动识别分享格式
- 定时检测链接有效性
- 失效链接微信通知
- 批量操作功能
- 搜索和分页
- 响应式界面设计

## 安装要求

- PHP 7.0+
- MySQL 5.6+
- PHP扩展：
  - PDO
  - PDO_MySQL
  - cURL
- Web服务器（Apache/Nginx）
- 定时任务支持（Cron）

## 快速安装

1. 下载源代码到网站目录
2. 访问安装页面（如：`http://your-domain/install.php`）
3. 按照安装向导设置：
   - 数据库配置
   - 管理员账号
   - WxPusher配置（用于微信通知）
4. 设置定时任务：
bash
编辑crontab
crontab -e
添加以下内容（每分钟执行一次）
/usr/bin/php /path/to/your/website/check_interval.php >> /path/to/your/website/cron.log 2>&1


## 使用说明

### 添加链接
1. 百度网盘格式：
分享标题
链接：https://pan.baidu.com/s/xxx
提取码：xxxx

2. 夸克网盘格式：
「分享标题」
链接：https://pan.quark.cn/s/xxx
提取码：xxxx

3. 阿里云盘格式：
「分享标题」
链接：https://www.aliyundrive.com/s/xxx
提取码：xxxx

4. 其他自定义平台格式：
分享标题
https://www.xxx.com/s/xxx

### 功能说明
- 自动检测：系统会按设定的间隔自动检测链接有效性
- 手动检测：可以手动检测单个或批量检测多个链接
- 失效通知：链接失效时会通过微信推送通知
- 批量操作：支持批量检测和删除
- 搜索功能：可以按标题搜索链接
- 分页显示：支持自定义每页显示数量

## 配置说明

### 系统配置
- 检查间隔时间：两次检查之间的最小间隔（秒）
- 最小延迟时间：检查时的最小随机延迟（秒）
- 最大延迟时间：检查时的最大随机延迟（秒）

### WxPusher配置
1. 访问 [WxPusher](https://wxpusher.zjiecode.com) 获取配置
2. 设置以下参数：
   - AppToken
   - 接收用户的UID

## 注意事项

1. 确保PHP有足够的执行时间
2. 建议将检查间隔设置在300秒以上
3. 使用随机延迟避免频繁请求
4. 定期检查日志文件大小
5. 建议配置SSL证书提高安全性

## 许可证

MIT License

## 技术支持

如有问题，请提交 Issue 或 Pull Request