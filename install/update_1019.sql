CREATE TABLE IF NOT EXISTS `pre_greenlog` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `file_id` int(11) unsigned NOT NULL DEFAULT '0',
  `name` varchar(255) NOT NULL DEFAULT '',
  `type` varchar(50) DEFAULT NULL,
  `hash` varchar(32) DEFAULT NULL,
  `engine` varchar(20) NOT NULL DEFAULT '' COMMENT '检测引擎：aliyun/qcloud/self',
  `score` decimal(6,4) NOT NULL DEFAULT '0.0000' COMMENT '评分，云接口没有分数固定为0',
  `detail` varchar(255) NOT NULL DEFAULT '' COMMENT '各模型分数明细',
  `verdict` varchar(10) NOT NULL DEFAULT 'pass' COMMENT 'pass放行 review待审 block封禁 error检测失败',
  `ms` int(11) NOT NULL DEFAULT '0' COMMENT '耗时毫秒',
  `uid` int(11) unsigned NOT NULL DEFAULT '0',
  `ip` varchar(45) DEFAULT NULL,
  `addtime` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `verdict` (`verdict`,`id`),
  KEY `addtime` (`addtime`),
  KEY `file_id` (`file_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
