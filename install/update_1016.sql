ALTER TABLE `pre_user` ADD COLUMN `password` varchar(255) NOT NULL DEFAULT '' COMMENT '邮箱账号的密码哈希，快捷登录的账号为空' AFTER `openid`;
ALTER TABLE `pre_user` MODIFY COLUMN `regip` varchar(45) DEFAULT NULL;
ALTER TABLE `pre_user` MODIFY COLUMN `loginip` varchar(45) DEFAULT NULL;
ALTER TABLE `pre_user` DROP INDEX `openid`;
ALTER TABLE `pre_user` ADD UNIQUE KEY `openid` (`openid`,`type`);
CREATE TABLE IF NOT EXISTS `pre_mailcode` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(120) NOT NULL COMMENT '收件邮箱（已归一化为小写）',
  `code` varchar(8) NOT NULL COMMENT '6位数字验证码',
  `purpose` varchar(16) NOT NULL DEFAULT 'register' COMMENT '用途：register注册 reset找回密码 changemail换邮箱',
  `uid` int(11) NOT NULL DEFAULT '0' COMMENT '找回密码/换邮箱时关联的用户',
  `ip` varchar(45) DEFAULT NULL,
  `used` tinyint(1) NOT NULL DEFAULT '0' COMMENT '用过就作废，不能重复使用',
  `trycount` int(11) NOT NULL DEFAULT '0' COMMENT '输错次数，超过上限直接作废',
  `addtime` datetime NOT NULL,
  `expiretime` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `email_purpose` (`email`,`purpose`),
  KEY `addtime` (`addtime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
REPLACE INTO `pre_config` VALUES ('mail_reg_open', '0');
REPLACE INTO `pre_config` VALUES ('mail_code_expire', '10');
REPLACE INTO `pre_config` VALUES ('mail_send_interval', '60');
REPLACE INTO `pre_config` VALUES ('mail_daily_limit', '10');
REPLACE INTO `pre_config` VALUES ('mail_ip_daily_limit', '20');
REPLACE INTO `pre_config` VALUES ('mail_domain_deny', '');
