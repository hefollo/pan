DROP TABLE IF EXISTS `pre_config`;
create table `pre_config` (
  `k` varchar(32) NOT NULL,
  `v` text NULL,
  PRIMARY KEY  (`k`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `pre_config` VALUES ('version', '1009');
INSERT INTO `pre_config` VALUES ('admin_user', 'admin');
INSERT INTO `pre_config` VALUES ('admin_pwd', '123456');
INSERT INTO `pre_config` VALUES ('blackip', '');
INSERT INTO `pre_config` VALUES ('title', '彩虹外链网盘');
INSERT INTO `pre_config` VALUES ('keywords', '外链网盘,免费外链,免费图床,图片外链');
INSERT INTO `pre_config` VALUES ('description', '彩虹外链网盘提供大容量云存储服务');
INSERT INTO `pre_config` VALUES ('site_theme', 'cloud');
INSERT INTO `pre_config` VALUES ('iptype', '0');
INSERT INTO `pre_config` VALUES ('filesearch', '1');
INSERT INTO `pre_config` VALUES ('storage', 'local');
INSERT INTO `pre_config` VALUES ('filepath', '');
INSERT INTO `pre_config` VALUES ('s3_ak', '');
INSERT INTO `pre_config` VALUES ('s3_sk', '');
INSERT INTO `pre_config` VALUES ('s3_endpoint', '');
INSERT INTO `pre_config` VALUES ('s3_region', 'us-east-1');
INSERT INTO `pre_config` VALUES ('s3_bucket', '');
INSERT INTO `pre_config` VALUES ('s3_prefix', 'file/');
INSERT INTO `pre_config` VALUES ('s3_path_style', '0');
INSERT INTO `pre_config` VALUES ('aliyun_ak', '');
INSERT INTO `pre_config` VALUES ('aliyun_sk', '');
INSERT INTO `pre_config` VALUES ('name_block', '');
INSERT INTO `pre_config` VALUES ('type_block', '');
INSERT INTO `pre_config` VALUES ('type_image', 'png|jpg|jpeg|gif|bmp|webp|ico|svg|svgz|tif|tiff|heic|exif');
INSERT INTO `pre_config` VALUES ('type_audio', 'mp3|wav|ogg|m4a|flac|aac');
INSERT INTO `pre_config` VALUES ('type_video', 'mp4|webm|flv|f4v|mov|3gp|3gpp|avi|mpg|mpeg|wmv|mkv|ts|dat|asf|mts|m2ts|m3u8|m4v');
INSERT INTO `pre_config` VALUES ('green_check', '0');
INSERT INTO `pre_config` VALUES ('green_check_region', 'cn-beijing');
INSERT INTO `pre_config` VALUES ('green_check_porn', '0');
INSERT INTO `pre_config` VALUES ('green_check_terrorism', '0');
INSERT INTO `pre_config` VALUES ('green_label_porn', 'sexy,porn');
INSERT INTO `pre_config` VALUES ('green_label_terrorism', 'bloody,explosion,outfit,logo,weapon,politics');
INSERT INTO `pre_config` VALUES ('gg_file', '网站所有文件内容均由用户自行上传分享，本站严格遵守国家相关法律法规，尊重著作权、版权等第三方权利，如果当前文件侵犯了您的相关权利，请邮件反馈至@qq.com，我们将及时处理。');
INSERT INTO `pre_config` VALUES ('violation_open', '1');
INSERT INTO `pre_config` VALUES ('violation_notice', '本站严格遵守国家法律法规，对用户举报及系统检测发现的违规文件一律予以封禁，并在此公示。文件名、上传IP等信息已做脱敏处理。');

DROP TABLE IF EXISTS `pre_file`;
CREATE TABLE `pre_file` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `type` varchar(50) DEFAULT NULL,
  `size` int(11) unsigned NOT NULL,
  `hash` varchar(32) NOT NULL,
  `token` varchar(32) NOT NULL,
  `addtime` datetime NOT NULL,
  `lasttime` datetime DEFAULT NULL,
  `ip` varchar(45) NOT NULL,
  `ipkey` varchar(45) NOT NULL DEFAULT '' COMMENT '限流维度：IPv4存完整地址，IPv6存/64前缀',
  `hide` int(1) NOT NULL DEFAULT '0',
  `pwd` varchar(255) DEFAULT NULL,
  `block` int(1) NOT NULL DEFAULT '0',
  `count` int(11) unsigned NOT NULL DEFAULT '0',
  `uid` int(11) unsigned NOT NULL DEFAULT '0',
   PRIMARY KEY (`id`),
   UNIQUE KEY `token` (`token`),
   KEY `hash` (`hash`),
   KEY `ipkey` (`ipkey`,`addtime`),
   KEY `uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `pre_sponsor`;
CREATE TABLE `pre_sponsor` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `platform` varchar(20) NOT NULL DEFAULT '微信',
  `amount` varchar(100) NOT NULL,
  `sponsor_time` varchar(20) NOT NULL,
  `addtime` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `pre_violation`;
CREATE TABLE `pre_violation` (
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

DROP TABLE IF EXISTS `pre_replace_log`;
CREATE TABLE `pre_replace_log` (
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

DROP TABLE IF EXISTS `pre_user`;
CREATE TABLE `pre_user` (
  `uid` int(11) NOT NULL AUTO_INCREMENT,
  `type` varchar(20) NOT NULL,
  `openid` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL DEFAULT '' COMMENT '邮箱账号的密码哈希，快捷登录的账号为空',
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
  KEY `openid` (`openid`,`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1000;
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

INSERT INTO `pre_plan` (`name`,`category`,`price`,`upload_limit`,`limit_mode`,`upload_size`,`days`,`remark`,`sort`,`enable`,`addtime`) VALUES ('体验周卡', '包月套餐', '1.00', 50, 'set', 100, 7, '先试试水，随时可续', 10, 1, NOW());
INSERT INTO `pre_plan` (`name`,`category`,`price`,`upload_limit`,`limit_mode`,`upload_size`,`days`,`remark`,`sort`,`enable`,`addtime`) VALUES ('入门月卡', '包月套餐', '4.90', 100, 'set', 300, 30, '轻度使用，一个月够了', 11, 1, NOW());
INSERT INTO `pre_plan` (`name`,`category`,`price`,`upload_limit`,`limit_mode`,`upload_size`,`days`,`remark`,`sort`,`enable`,`addtime`) VALUES ('标准月卡', '包月套餐', '9.90', 200, 'set', 500, 30, '日常够用，最受欢迎', 12, 1, NOW());
INSERT INTO `pre_plan` (`name`,`category`,`price`,`upload_limit`,`limit_mode`,`upload_size`,`days`,`remark`,`sort`,`enable`,`addtime`) VALUES ('超值季卡', '包月套餐', '25.00', 500, 'set', 1024, 90, '三个月，折合每月更便宜', 13, 1, NOW());
INSERT INTO `pre_plan` (`name`,`category`,`price`,`upload_limit`,`limit_mode`,`upload_size`,`days`,`remark`,`sort`,`enable`,`addtime`) VALUES ('半年卡', '包月套餐', '48.00', 800, 'set', 1536, 180, '半年长期，额度翻倍', 14, 1, NOW());
INSERT INTO `pre_plan` (`name`,`category`,`price`,`upload_limit`,`limit_mode`,`upload_size`,`days`,`remark`,`sort`,`enable`,`addtime`) VALUES ('至尊年卡', '包月套餐', '88.00', 0, 'set', 2048, 365, '整年不限数量，省心', 15, 1, NOW());
INSERT INTO `pre_plan` (`name`,`category`,`price`,`upload_limit`,`limit_mode`,`upload_size`,`days`,`remark`,`sort`,`enable`,`addtime`) VALUES ('加量包 +50', '加量包', '3.00', 50, 'add', -1, 30, '30 天内每天多传 50 个', 20, 1, NOW());
INSERT INTO `pre_plan` (`name`,`category`,`price`,`upload_limit`,`limit_mode`,`upload_size`,`days`,`remark`,`sort`,`enable`,`addtime`) VALUES ('加量包 +100', '加量包', '5.00', 100, 'add', -1, 30, '30 天内每天多传 100 个', 21, 1, NOW());
INSERT INTO `pre_plan` (`name`,`category`,`price`,`upload_limit`,`limit_mode`,`upload_size`,`days`,`remark`,`sort`,`enable`,`addtime`) VALUES ('加量包 +200', '加量包', '8.00', 200, 'add', -1, 30, '30 天内每天多传 200 个', 22, 1, NOW());
INSERT INTO `pre_plan` (`name`,`category`,`price`,`upload_limit`,`limit_mode`,`upload_size`,`days`,`remark`,`sort`,`enable`,`addtime`) VALUES ('加量包 +500', '加量包', '18.00', 500, 'add', -1, 30, '30 天内每天多传 500 个', 23, 1, NOW());
INSERT INTO `pre_plan` (`name`,`category`,`price`,`upload_limit`,`limit_mode`,`upload_size`,`days`,`remark`,`sort`,`enable`,`addtime`) VALUES ('加量包 +1000', '加量包', '30.00', 1000, 'add', -1, 30, '30 天内每天多传 1000 个', 24, 1, NOW());
INSERT INTO `pre_plan` (`name`,`category`,`price`,`upload_limit`,`limit_mode`,`upload_size`,`days`,`remark`,`sort`,`enable`,`addtime`) VALUES ('加量包 +2000', '加量包', '50.00', 2000, 'add', -1, 30, '30 天内每天多传 2000 个，量大更划算', 25, 1, NOW());
INSERT INTO `pre_plan` (`name`,`category`,`price`,`upload_limit`,`limit_mode`,`upload_size`,`days`,`remark`,`sort`,`enable`,`addtime`) VALUES ('大文件包 512MB', '单文件加强', '2.00', -1, 'set', 512, 30, '单文件上限提到 512MB，不动每日数量', 30, 1, NOW());
INSERT INTO `pre_plan` (`name`,`category`,`price`,`upload_limit`,`limit_mode`,`upload_size`,`days`,`remark`,`sort`,`enable`,`addtime`) VALUES ('大文件包 1GB', '单文件加强', '3.00', -1, 'set', 1024, 30, '单文件上限提到 1GB，不动每日数量', 31, 1, NOW());
INSERT INTO `pre_plan` (`name`,`category`,`price`,`upload_limit`,`limit_mode`,`upload_size`,`days`,`remark`,`sort`,`enable`,`addtime`) VALUES ('大文件包 2GB', '单文件加强', '5.00', -1, 'set', 2048, 30, '单文件上限提到 2GB，不动每日数量', 32, 1, NOW());
INSERT INTO `pre_plan` (`name`,`category`,`price`,`upload_limit`,`limit_mode`,`upload_size`,`days`,`remark`,`sort`,`enable`,`addtime`) VALUES ('大文件包 5GB', '单文件加强', '12.00', -1, 'set', 5120, 30, '单文件上限提到 5GB，不动每日数量', 33, 1, NOW());
INSERT INTO `pre_plan` (`name`,`category`,`price`,`upload_limit`,`limit_mode`,`upload_size`,`days`,`remark`,`sort`,`enable`,`addtime`) VALUES ('大文件包 10GB', '单文件加强', '20.00', -1, 'set', 10240, 30, '单文件上限提到 10GB，不动每日数量', 34, 1, NOW());
INSERT INTO `pre_plan` (`name`,`category`,`price`,`upload_limit`,`limit_mode`,`upload_size`,`days`,`remark`,`sort`,`enable`,`addtime`) VALUES ('大文件包 20GB', '单文件加强', '35.00', -1, 'set', 20480, 30, '单文件上限提到 20GB，适合视频素材', 35, 1, NOW());
INSERT INTO `pre_plan` (`name`,`category`,`price`,`upload_limit`,`limit_mode`,`upload_size`,`days`,`remark`,`sort`,`enable`,`addtime`) VALUES ('永久入门版', '永久会员', '68.00', 100, 'set', 1024, 0, '一次买断，每天 100 个', 40, 1, NOW());
INSERT INTO `pre_plan` (`name`,`category`,`price`,`upload_limit`,`limit_mode`,`upload_size`,`days`,`remark`,`sort`,`enable`,`addtime`) VALUES ('永久基础版', '永久会员', '98.00', 300, 'set', 2048, 0, '一次买断，每天 300 个', 41, 1, NOW());
INSERT INTO `pre_plan` (`name`,`category`,`price`,`upload_limit`,`limit_mode`,`upload_size`,`days`,`remark`,`sort`,`enable`,`addtime`) VALUES ('永久标准版', '永久会员', '138.00', 600, 'set', 3072, 0, '一次买断，每天 600 个', 42, 1, NOW());
INSERT INTO `pre_plan` (`name`,`category`,`price`,`upload_limit`,`limit_mode`,`upload_size`,`days`,`remark`,`sort`,`enable`,`addtime`) VALUES ('永久会员', '永久会员', '198.00', 0, 'set', 5120, 0, '一次买断，不限每日数量', 43, 1, NOW());
INSERT INTO `pre_plan` (`name`,`category`,`price`,`upload_limit`,`limit_mode`,`upload_size`,`days`,`remark`,`sort`,`enable`,`addtime`) VALUES ('永久尊享版', '永久会员', '298.00', 0, 'set', 10240, 0, '不限数量，单文件 10GB', 44, 1, NOW());
INSERT INTO `pre_plan` (`name`,`category`,`price`,`upload_limit`,`limit_mode`,`upload_size`,`days`,`remark`,`sort`,`enable`,`addtime`) VALUES ('永久旗舰版', '永久会员', '498.00', 0, 'set', 0, 0, '数量和大小都不限，一步到位', 45, 1, NOW());

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
