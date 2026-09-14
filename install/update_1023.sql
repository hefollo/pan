-- 1023：用户上传 API 密钥。密钥正文只在创建时展示，数据库仅保存 HMAC 哈希。
CREATE TABLE IF NOT EXISTS `pre_api_key` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `uid` int(11) unsigned NOT NULL DEFAULT '0',
  `name` varchar(64) NOT NULL DEFAULT '',
  `key_prefix` varchar(16) NOT NULL DEFAULT '',
  `key_hash` char(64) NOT NULL,
  `enable` tinyint(1) NOT NULL DEFAULT '1',
  `allow_ip` varchar(255) NOT NULL DEFAULT '',
  `expiretime` datetime DEFAULT NULL,
  `lasttime` datetime DEFAULT NULL,
  `lastip` varchar(45) DEFAULT NULL,
  `addtime` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_hash` (`key_hash`),
  KEY `uid` (`uid`,`id`),
  KEY `enable` (`enable`,`expiretime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户上传API密钥';

INSERT IGNORE INTO `pre_config` VALUES ('api_auth_mode', 'user');
INSERT IGNORE INTO `pre_config` VALUES ('api_key_limit', '5');
INSERT IGNORE INTO `pre_config` VALUES ('api_key_expire_days', '365');
