CREATE TABLE IF NOT EXISTS `pre_user_bind` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `uid` int(11) unsigned NOT NULL DEFAULT '0',
  `type` varchar(20) NOT NULL COMMENT '登录方式：qq/wx/mail',
  `openid` varchar(150) NOT NULL COMMENT 'qq/wx存社交平台uid，mail存邮箱地址',
  `addtime` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `identity` (`type`,`openid`),
  KEY `uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
