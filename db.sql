CREATE TABLE `share_links` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL COMMENT '分享标题',
  `url` varchar(1000) NOT NULL COMMENT '分享链接',
  `platform` varchar(50) NOT NULL COMMENT '网盘平台',
  `status` tinyint(1) DEFAULT 1 COMMENT '状态 1:有效 0:失效',
  `last_check_time` datetime DEFAULT NULL COMMENT '最后检查时间',
  `create_time` datetime DEFAULT CURRENT_TIMESTAMP,
  `update_time` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `create_time` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `system_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `key` varchar(50) NOT NULL COMMENT '配置键名',
  `value` text NOT NULL COMMENT '配置值',
  `description` varchar(255) DEFAULT NULL COMMENT '配置说明',
  `update_time` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 插入默认配置
INSERT INTO `system_config` (`key`, `value`, `description`) VALUES
('check_interval', '7200', '检查间隔时间(秒)'),
('check_delay_min', '5', '检查间隔最小延迟(秒)'),
('check_delay_max', '15', '检查间隔最大延迟(秒)'),
('last_cron_time', '0', '最后一次定时任务运行时间'); 