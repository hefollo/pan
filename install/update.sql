REPLACE INTO `pre_config` VALUES ('type_image', 'png|jpg|jpeg|gif|bmp|webp|ico|svg|svgz|tif|tiff|heic|exif');
REPLACE INTO `pre_config` VALUES ('type_audio', 'mp3|wav|ogg|m4a|flac|aac');
REPLACE INTO `pre_config` VALUES ('type_video', 'mp4|webm|flv|f4v|mov|3gp|3gpp|avi|mpg|mpeg|wmv|mkv|ts|dat|asf|mts|m2ts|m3u8|m4v');
REPLACE INTO `pre_config` VALUES ('filesearch', '1');
INSERT IGNORE INTO `pre_config` VALUES ('site_theme', 'cloud');

ALTER TABLE `pre_file`
ADD COLUMN `uid` int(11) unsigned NOT NULL DEFAULT '0';

ALTER TABLE `pre_file`
ADD INDEX `uid` (`uid`);

CREATE TABLE IF EXISTS `pre_user` (
  `uid` int(11) NOT NULL AUTO_INCREMENT,
  `type` varchar(20) NOT NULL,
  `openid` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL DEFAULT '',
  `nickname` varchar(255) NOT NULL,
  `faceimg` varchar(255) DEFAULT NULL,
  `enable` tinyint(1) NOT NULL DEFAULT '1',
  `regip` varchar(45) DEFAULT NULL,
  `loginip` varchar(45) DEFAULT NULL,
  `level` tinyint(4) NOT NULL DEFAULT '0',
  `upload_size` int(11) NOT NULL DEFAULT '-1',
  `upload_limit` int(11) NOT NULL DEFAULT '-1',
  `bonus_limit` int(11) NOT NULL DEFAULT '0' COMMENT '加量包累计的每日额度',
  `expiretime` datetime DEFAULT NULL,
  `addtime` datetime NOT NULL,
  `lasttime` datetime NOT NULL,
  PRIMARY KEY (`uid`),
  UNIQUE KEY `openid` (`openid`,`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1000;

ALTER TABLE `pre_user`
ADD COLUMN `upload_size` int(11) NOT NULL DEFAULT '-1' AFTER `level`;

ALTER TABLE `pre_user`
ADD COLUMN `upload_limit` int(11) NOT NULL DEFAULT '-1' AFTER `upload_size`;

ALTER TABLE `pre_user`
ADD COLUMN `expiretime` datetime DEFAULT NULL AFTER `upload_limit`;

ALTER TABLE `pre_file`
ADD COLUMN `token` varchar(32) DEFAULT NULL AFTER `hash`;

UPDATE `pre_file` SET `token` = `hash` WHERE `token` IS NULL OR `token` = '';

ALTER TABLE `pre_file`
ADD UNIQUE KEY `token` (`token`);

CREATE TABLE IF NOT EXISTS `pre_sponsor` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `platform` varchar(20) NOT NULL DEFAULT '微信',
  `amount` varchar(100) NOT NULL,
  `sponsor_time` varchar(20) NOT NULL,
  `addtime` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `pre_sponsor`
ADD COLUMN `platform` varchar(20) NOT NULL DEFAULT '微信' AFTER `name`;

CREATE TABLE IF NOT EXISTS `pre_violation` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `file_id` int(11) unsigned NOT NULL DEFAULT '0',
  `name` varchar(255) NOT NULL,
  `type` varchar(50) DEFAULT NULL,
  `size` int(11) unsigned NOT NULL DEFAULT '0',
  `hash` varchar(32) DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `uid` int(11) unsigned NOT NULL DEFAULT '0',
  `source` varchar(20) NOT NULL DEFAULT 'admin',
  `remark` varchar(255) DEFAULT NULL,
  `is_show` tinyint(1) NOT NULL DEFAULT '1',
  `addtime` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `file_id` (`file_id`),
  KEY `is_show` (`is_show`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `pre_config` VALUES ('violation_notice', '本站严格遵守国家法律法规，对用户举报及系统检测发现的违规文件一律予以封禁，并在此公示。文件名、上传IP等信息已做脱敏处理。');
INSERT IGNORE INTO `pre_config` VALUES ('violation_open', '1');

INSERT INTO `pre_violation` (`file_id`,`name`,`type`,`size`,`hash`,`ip`,`uid`,`source`,`is_show`,`addtime`)
SELECT f.`id`, f.`name`, f.`type`, f.`size`, f.`hash`, f.`ip`, f.`uid`, 'admin', 1, NOW()
FROM `pre_file` f LEFT JOIN `pre_violation` v ON v.`file_id`=f.`id`
WHERE f.`block`=1 AND v.`id` IS NULL;

UPDATE IGNORE `pre_file` SET `token` = `hash` WHERE `token` IS NULL OR `token` = '';

UPDATE `pre_file` SET `token` = REPLACE(UUID(),'-','') WHERE `token` IS NULL OR `token` = '';

CREATE TABLE IF NOT EXISTS `pre_replace_log` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `file_id` int(11) unsigned NOT NULL DEFAULT '0',
  `token` varchar(32) DEFAULT NULL,
  `old_name` varchar(255) NOT NULL,
  `old_type` varchar(50) DEFAULT NULL,
  `old_size` int(11) unsigned NOT NULL DEFAULT '0',
  `old_hash` varchar(32) DEFAULT NULL,
  `new_name` varchar(255) NOT NULL,
  `new_type` varchar(50) DEFAULT NULL,
  `new_size` int(11) unsigned NOT NULL DEFAULT '0',
  `new_hash` varchar(32) DEFAULT NULL,
  `uid` int(11) unsigned NOT NULL DEFAULT '0',
  `ip` varchar(45) DEFAULT NULL,
  `source` varchar(20) NOT NULL DEFAULT 'replace',
  `checked` tinyint(1) NOT NULL DEFAULT '0',
  `addtime` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `file_id` (`file_id`),
  KEY `checked` (`checked`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `pre_mailcode`;
CREATE TABLE `pre_mailcode` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(120) NOT NULL COMMENT '收件邮箱（已归一化为小写）',
  `code` varchar(8) NOT NULL COMMENT '6位数字验证码',
  `purpose` varchar(16) NOT NULL DEFAULT 'register' COMMENT '用途：register注册 reset找回密码 changemail换邮箱',
  `uid` int(11) NOT NULL DEFAULT '0' COMMENT '找回密码/换邮箱时关联的用户',
  `ip` varchar(45) DEFAULT NULL,
  `used` tinyint(1) NOT NULL DEFAULT '0' COMMENT '用过就作废，不能重复使用',
  `trycount` int(11) NOT NULL DEFAULT '0' COMMENT '输错次数，超过上限直接作废',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0已发送 1已验证 2发送失败 3已作废',
  `sender` varchar(20) NOT NULL DEFAULT '' COMMENT '实际发出去的通道',
  `errmsg` varchar(255) NOT NULL DEFAULT '' COMMENT '发送失败的原因',
  `addtime` datetime NOT NULL,
  `expiretime` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `email_purpose` (`email`,`purpose`),
  KEY `addtime` (`addtime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `pre_plan`;
CREATE TABLE `pre_plan` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(64) NOT NULL COMMENT '套餐名称',
  `category` varchar(32) NOT NULL DEFAULT '' COMMENT '套餐分类，购买页按它分区展示',
  `price` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '售价（元）',
  `upload_limit` int(11) NOT NULL DEFAULT '-1' COMMENT '每日上传数量：-1继承全站 0不限 N每天N个',
  `limit_mode` varchar(8) NOT NULL DEFAULT 'set' COMMENT '每日数量发放方式：set设为 add在现有基础上增加',
  `upload_size` int(11) NOT NULL DEFAULT '-1' COMMENT '单文件大小MB：-1继承全站 0不限',
  `days` int(11) NOT NULL DEFAULT '0' COMMENT '有效期天数，0为永久',
  `remark` varchar(255) DEFAULT NULL COMMENT '套餐说明',
  `sort` int(11) NOT NULL DEFAULT '0' COMMENT '排序，小的在前',
  `enable` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否上架',
  `addtime` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `enable` (`enable`,`sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `pre_order`;
CREATE TABLE `pre_order` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `trade_no` varchar(32) NOT NULL COMMENT '商户订单号',
  `alipay_no` varchar(64) DEFAULT NULL COMMENT '支付宝交易号',
  `uid` int(11) NOT NULL DEFAULT '0',
  `plan_id` int(11) NOT NULL DEFAULT '0',
  `plan_name` varchar(64) NOT NULL COMMENT '下单时的套餐名快照',
  `price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `pay_type` varchar(10) NOT NULL DEFAULT 'alipay' COMMENT '支付方式：alipay 当面付 / epay 易支付',
  `upload_limit` int(11) NOT NULL DEFAULT '-1',
  `limit_mode` varchar(8) NOT NULL DEFAULT 'set',
  `upload_size` int(11) NOT NULL DEFAULT '-1',
  `days` int(11) NOT NULL DEFAULT '0',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0待支付 1已支付并发放 2已关闭',
  `ip` varchar(46) DEFAULT NULL,
  `addtime` datetime NOT NULL,
  `paytime` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `trade_no` (`trade_no`),
  KEY `uid` (`uid`,`id`),
  KEY `status` (`status`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `pre_config` VALUES ('alipay_open', '0');
INSERT INTO `pre_config` VALUES ('epay_open', '0');
INSERT INTO `pre_config` VALUES ('mail_reg_open', '0');
INSERT INTO `pre_config` VALUES ('mail_code_expire', '10');
INSERT INTO `pre_config` VALUES ('mail_send_interval', '60');
INSERT INTO `pre_config` VALUES ('mail_daily_limit', '10');
INSERT INTO `pre_config` VALUES ('mail_ip_daily_limit', '20');
INSERT INTO `pre_config` VALUES ('mail_domain_deny', '');
INSERT INTO `pre_config` VALUES ('pay_subject', '赞助');
INSERT INTO `pre_config` VALUES ('epay_charset', 'UTF-8');
INSERT INTO `pre_config` VALUES ('alipay_appid', '');
INSERT INTO `pre_config` VALUES ('alipay_public_key', '');
INSERT INTO `pre_config` VALUES ('alipay_private_key', '');
INSERT INTO `pre_config` VALUES ('buy_notice', '');

-- 1017：文件表加限流维度 ipkey，ip 加宽到能存 IPv6
ALTER TABLE `pre_file` MODIFY COLUMN `ip` varchar(45) NOT NULL;
ALTER TABLE `pre_file` ADD COLUMN `ipkey` varchar(45) NOT NULL DEFAULT '' COMMENT '限流维度：IPv4存完整地址，IPv6存/64前缀' AFTER `ip`;
ALTER TABLE `pre_file` ADD KEY `ipkey` (`ipkey`,`addtime`);
UPDATE `pre_file` SET `ipkey`=`ip` WHERE `ipkey`='' AND `ip` NOT LIKE '%:%';
