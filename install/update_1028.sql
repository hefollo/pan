CREATE TABLE IF NOT EXISTS `pre_folder_share` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `uid` int(11) unsigned NOT NULL,
  `folder_id` int(11) unsigned NOT NULL,
  `token` char(64) NOT NULL,
  `password_hash` varchar(255) NOT NULL DEFAULT '',
  `auth_version` char(32) NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT '1',
  `expiretime` datetime DEFAULT NULL,
  `addtime` datetime NOT NULL,
  `updatetime` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `folder_id` (`folder_id`),
  UNIQUE KEY `token` (`token`),
  KEY `uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='文件夹分享';
