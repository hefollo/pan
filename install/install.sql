DROP TABLE IF EXISTS `pre_config`;
create table `pre_config` (
  `k` varchar(32) NOT NULL,
  `v` text NULL,
  PRIMARY KEY  (`k`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 这个版本号必须和 includes/common.php 的 DB_VERSION 保持一致。
-- 本文件的建表语句已经是最新结构（1010~1023 的表、字段、配置项都已并入），
-- 填旧版本号会让每一次全新安装装完立刻撞上 common.php 的版本门禁，
-- 前台后台一起被「请先完成网站升级」拦住，必须再跑一次 /install/update.php 才能进站，
-- 而那趟升级其实什么都不改（全是 CREATE TABLE IF NOT EXISTS / INSERT IGNORE 空转）。
--
-- 维护规则：以后新增 update_XXXX.sql 时，
--   · 如果顺手把它的结构也并进了本文件 → 这里跟着改成 XXXX；
--   · 如果没有并进来 → 这里保持旧值不动，让全新安装照常跑一次升级把它补上。
-- 改错方向（并进来了却不改版本号）只是多跑一趟空升级；
-- 改反了（没并进来却改了版本号）会让新装的站永久缺表缺字段，且毫无报错。
INSERT INTO `pre_config` VALUES ('version', '1027');
INSERT INTO `pre_config` VALUES ('level_migrated', '1');
INSERT INTO `pre_config` VALUES ('admin_user', 'admin');
INSERT INTO `pre_config` VALUES ('admin_pwd', '123456');
INSERT INTO `pre_config` VALUES ('blackip', '');
INSERT INTO `pre_config` VALUES ('title', '彩虹外链网盘');
INSERT INTO `pre_config` VALUES ('keywords', '外链网盘,免费外链,免费图床,图片外链');
INSERT INTO `pre_config` VALUES ('description', '彩虹外链网盘提供大容量云存储服务');
INSERT INTO `pre_config` VALUES ('site_theme', 'console');
INSERT INTO `pre_config` VALUES ('iptype', '0');
INSERT INTO `pre_config` VALUES ('filesearch', '1');
INSERT INTO `pre_config` VALUES ('storage', 'local');
INSERT INTO `pre_config` VALUES ('filepath', '');
INSERT INTO `pre_config` VALUES ('api_auth_mode', 'user');
INSERT INTO `pre_config` VALUES ('api_key_limit', '5');
INSERT INTO `pre_config` VALUES ('api_key_expire_days', '365');
INSERT INTO `pre_config` VALUES ('down_speed_guest', '0');
INSERT INTO `pre_config` VALUES ('down_speed_guest_unit', 'KB');
INSERT INTO `pre_config` VALUES ('down_speed_user', '0');
INSERT INTO `pre_config` VALUES ('down_speed_user_unit', 'KB');
INSERT INTO `pre_config` VALUES ('down_speed_vip', '0');
INSERT INTO `pre_config` VALUES ('down_speed_vip_unit', 'KB');
INSERT INTO `pre_config` VALUES ('s3_ak', '');
INSERT INTO `pre_config` VALUES ('s3_sk', '');
INSERT INTO `pre_config` VALUES ('s3_endpoint', '');
INSERT INTO `pre_config` VALUES ('s3_region', 'us-east-1');
INSERT INTO `pre_config` VALUES ('s3_bucket', '');
INSERT INTO `pre_config` VALUES ('s3_prefix', 'file/');
INSERT INTO `pre_config` VALUES ('s3_path_style', '0');
INSERT INTO `pre_config` VALUES ('webdav_url', '');
INSERT INTO `pre_config` VALUES ('webdav_user', '');
INSERT INTO `pre_config` VALUES ('webdav_pass', '');
INSERT INTO `pre_config` VALUES ('webdav_path', 'file');
INSERT INTO `pre_config` VALUES ('onedrive_type', 'common');
INSERT INTO `pre_config` VALUES ('onedrive_client_id', '');
INSERT INTO `pre_config` VALUES ('onedrive_client_secret', '');
INSERT INTO `pre_config` VALUES ('onedrive_path', 'pan/file');
INSERT INTO `pre_config` VALUES ('onedrive_refresh_token', '');
INSERT INTO `pre_config` VALUES ('onedrive_access_token', '');
INSERT INTO `pre_config` VALUES ('onedrive_token_expire', '0');
INSERT INTO `pre_config` VALUES ('openlist_url', '');
INSERT INTO `pre_config` VALUES ('openlist_user', '');
INSERT INTO `pre_config` VALUES ('openlist_pass', '');
INSERT INTO `pre_config` VALUES ('openlist_token', '');
INSERT INTO `pre_config` VALUES ('openlist_path', 'pan/file');
INSERT INTO `pre_config` VALUES ('openlist_cache_token', '');
INSERT INTO `pre_config` VALUES ('openlist_token_expire', '0');
INSERT INTO `pre_config` VALUES ('storage_multi', '0');
INSERT INTO `pre_config` VALUES ('storage_pool', '');
INSERT INTO `pre_config` VALUES ('aliyun_ak', '');
INSERT INTO `pre_config` VALUES ('aliyun_sk', '');
INSERT INTO `pre_config` VALUES ('name_block', '');
INSERT INTO `pre_config` VALUES ('type_block', '');
INSERT INTO `pre_config` VALUES ('type_image', 'png|jpg|jpeg|gif|bmp|webp|ico|tif|tiff|heic|exif');
INSERT INTO `pre_config` VALUES ('type_audio', 'mp3|wav|ogg|m4a|flac|aac');
INSERT INTO `pre_config` VALUES ('type_video', 'mp4|webm|flv|f4v|mov|3gp|3gpp|avi|mpg|mpeg|wmv|mkv|ts|dat|asf|mts|m2ts|m3u8|m4v');
INSERT INTO `pre_config` VALUES ('green_check', '0');
INSERT INTO `pre_config` VALUES ('green_self_api', '');
INSERT INTO `pre_config` VALUES ('green_self_token', '');
INSERT INTO `pre_config` VALUES ('green_self_block', '0.85');
INSERT INTO `pre_config` VALUES ('green_self_review', '0.6');
INSERT INTO `pre_config` VALUES ('green_self_timeout', '5');
INSERT INTO `pre_config` VALUES ('green_video', '0');
INSERT INTO `pre_config` VALUES ('green_video_block', '0.85');
INSERT INTO `pre_config` VALUES ('green_video_review', '0.6');
INSERT INTO `pre_config` VALUES ('green_video_hit', '2');
INSERT INTO `pre_config` VALUES ('green_video_interval', '5');
INSERT INTO `pre_config` VALUES ('green_video_frames', '40');
INSERT INTO `pre_config` VALUES ('green_video_maxlen', '7200');
INSERT INTO `pre_config` VALUES ('green_video_maxsize', '2048');
INSERT INTO `pre_config` VALUES ('green_video_timeout', '30');
INSERT INTO `pre_config` VALUES ('green_video_shot', '1');
INSERT INTO `pre_config` VALUES ('green_poll_time', '0');
INSERT INTO `pre_config` VALUES ('green_check_region', 'cn-beijing');
INSERT INTO `pre_config` VALUES ('green_check_porn', '0');
INSERT INTO `pre_config` VALUES ('green_check_terrorism', '0');
INSERT INTO `pre_config` VALUES ('green_label_porn', 'sexy,porn');
INSERT INTO `pre_config` VALUES ('green_label_terrorism', 'bloody,explosion,outfit,logo,weapon,politics');
INSERT INTO `pre_config` VALUES ('gg_file', '网站所有文件内容均由用户自行上传分享，本站严格遵守国家相关法律法规，尊重著作权、版权等第三方权利，如果当前文件侵犯了您的相关权利，请邮件反馈至@qq.com，我们将及时处理。');
INSERT INTO `pre_config` VALUES ('violation_open', '1');
INSERT INTO `pre_config` VALUES ('sponsor_open', '1');
INSERT INTO `pre_config` VALUES ('violation_notice', '本站严格遵守国家法律法规，对用户举报及系统检测发现的违规文件一律予以封禁，并在此公示。文件名、上传IP等信息已做脱敏处理。');

DROP TABLE IF EXISTS `pre_file`;
CREATE TABLE `pre_file` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `type` varchar(50) DEFAULT NULL,
  `size` int(11) unsigned NOT NULL,
  `hash` varchar(32) NOT NULL,
  `storage` varchar(20) NOT NULL DEFAULT '' COMMENT '物理文件存在哪个存储里，空表示按当前存储处理',
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
  `folder_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '所在的用户文件夹，0 表示根目录',
  `copied` tinyint(1) NOT NULL DEFAULT '0' COMMENT '用户复制出来的记录，不计入每日上传数',
   PRIMARY KEY (`id`),
   UNIQUE KEY `token` (`token`),
   KEY `hash` (`hash`),
   KEY `ipkey` (`ipkey`,`addtime`),
   KEY `uid` (`uid`),
   KEY `folder_id` (`folder_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `pre_folder`;
CREATE TABLE `pre_folder` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `uid` int(11) unsigned NOT NULL DEFAULT '0',
  `gkey` varchar(32) NOT NULL DEFAULT '' COMMENT '游客文件夹归属的会话标识，登录用户的文件夹为空',
  `parent_id` int(11) unsigned NOT NULL DEFAULT '0',
  `name` varchar(100) NOT NULL,
  `addtime` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `owner` (`uid`,`gkey`),
  KEY `addtime` (`addtime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户文件夹（虚拟目录）';

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
  `down_speed` int(11) NOT NULL DEFAULT '-1' COMMENT '下载限速KB/s：-1跟随身份档位 0不限速 N每秒N KB',
  `expiretime` datetime DEFAULT NULL,
  `online_edit` tinyint(1) NOT NULL DEFAULT '0' COMMENT '在线编辑：0未开通 1已开通',
  `edit_expire` datetime DEFAULT NULL COMMENT '在线编辑到期时间，已开通且为空表示永久',
  `level_id` int(11) NOT NULL DEFAULT '0' COMMENT '会员等级，0为普通用户，到期时间见expiretime',
  `bonus_expire` datetime DEFAULT NULL COMMENT '加量包到期时间，有加量额度且为空表示永久',
  `addtime` datetime NOT NULL,
  `lasttime` datetime NOT NULL,
  PRIMARY KEY (`uid`),
  KEY `openid` (`openid`,`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1000;

DROP TABLE IF EXISTS `pre_user_bind`;
CREATE TABLE `pre_user_bind` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `uid` int(11) unsigned NOT NULL DEFAULT '0',
  `type` varchar(20) NOT NULL COMMENT '登录方式：qq/wx/mail',
  `openid` varchar(150) NOT NULL COMMENT 'qq/wx存社交平台uid，mail存邮箱地址',
  `addtime` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `identity` (`type`,`openid`),
  KEY `uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `pre_api_key`;
CREATE TABLE `pre_api_key` (
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

DROP TABLE IF EXISTS `pre_greenlog`;
CREATE TABLE `pre_greenlog` (
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
  `frames` varchar(20) NOT NULL DEFAULT '' COMMENT '视频抽帧命中数/总数',
  `hit_at` int(11) NOT NULL DEFAULT '0' COMMENT '最高分出现在第几秒',
  `shot` varchar(80) NOT NULL DEFAULT '' COMMENT '证据帧文件名',
  `uid` int(11) unsigned NOT NULL DEFAULT '0',
  `ip` varchar(45) DEFAULT NULL,
  `addtime` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `verdict` (`verdict`,`id`),
  KEY `addtime` (`addtime`),
  KEY `file_id` (`file_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `pre_greenjob`;
CREATE TABLE `pre_greenjob` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `file_id` int(11) unsigned NOT NULL DEFAULT '0',
  `hash` varchar(32) NOT NULL DEFAULT '',
  `name` varchar(255) NOT NULL DEFAULT '',
  `type` varchar(50) NOT NULL DEFAULT '',
  `job` varchar(64) NOT NULL DEFAULT '' COMMENT '检测服务返回的任务号',
  `cbkey` varchar(64) NOT NULL DEFAULT '' COMMENT '回调地址里带的一次性密钥，认这个才收结果',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0待结果 1已完成 2检测失败 3超时自动放行',
  `tries` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '轮询次数',
  `uid` int(11) unsigned NOT NULL DEFAULT '0',
  `ip` varchar(45) DEFAULT NULL,
  `addtime` datetime NOT NULL,
  `updatetime` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `job` (`job`),
  KEY `status` (`status`,`id`),
  KEY `hash` (`hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='视频检测任务，异步跑完回来更新文件状态';

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

DROP TABLE IF EXISTS `pre_level`;
CREATE TABLE `pre_level` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(32) NOT NULL COMMENT '等级名称',
  `type` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0自建 1游客 2普通用户 3管理员，后三种是内置的不能删',
  `sort` int(11) NOT NULL DEFAULT '0' COMMENT '高低顺序，数字大的等级更高',
  `upload_limit` int(11) NOT NULL DEFAULT '-1' COMMENT '每日上传数量：-1跟随站点设置 0不限 N每天N个',
  `upload_size` int(11) NOT NULL DEFAULT '-1' COMMENT '单文件大小MB：-1跟随站点设置 0不限',
  `down_speed` int(11) NOT NULL DEFAULT '-1' COMMENT '下载限速KB/s：-1跟随站点设置 0不限速',
  `online_edit` tinyint(1) NOT NULL DEFAULT '0' COMMENT '在线编辑',
  `folder` tinyint(1) NOT NULL DEFAULT '0' COMMENT '用户文件夹',
  `api` tinyint(1) NOT NULL DEFAULT '0' COMMENT '上传API',
  `storage_all` tinyint(1) NOT NULL DEFAULT '0' COMMENT '能否使用多存储里限定会员的存储',
  `no_review` tinyint(1) NOT NULL DEFAULT '0' COMMENT '上传免审核',
  `remark` varchar(255) DEFAULT NULL COMMENT '等级说明',
  `addtime` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `pre_level` (`id`,`name`,`type`,`sort`,`upload_limit`,`upload_size`,`down_speed`,`online_edit`,`folder`,`api`,`storage_all`,`no_review`,`remark`,`addtime`) VALUES (1, '游客', 1, 0, -1, -1, -1, 0, 0, 0, 0, 0, '没有登录的访客', NOW());
INSERT INTO `pre_level` (`id`,`name`,`type`,`sort`,`upload_limit`,`upload_size`,`down_speed`,`online_edit`,`folder`,`api`,`storage_all`,`no_review`,`remark`,`addtime`) VALUES (2, '普通用户', 2, 10, -1, -1, -1, 0, 0, 0, 0, 0, '注册后默认的等级，会员到期后回到这里', NOW());
INSERT INTO `pre_level` (`id`,`name`,`type`,`sort`,`upload_limit`,`upload_size`,`down_speed`,`online_edit`,`folder`,`api`,`storage_all`,`no_review`,`remark`,`addtime`) VALUES (3, '管理员', 3, 100000, 0, 0, 0, 1, 1, 1, 1, 1, '不受任何限制，只能在用户管理里手动设置，不能购买', NOW());
INSERT INTO `pre_level` (`id`,`name`,`type`,`sort`,`upload_limit`,`upload_size`,`down_speed`,`online_edit`,`folder`,`api`,`storage_all`,`no_review`,`remark`,`addtime`) VALUES (4, '入门会员', 0, 100, 50, 200, -1, 0, 1, 0, 0, 0, '轻度使用', NOW());
INSERT INTO `pre_level` (`id`,`name`,`type`,`sort`,`upload_limit`,`upload_size`,`down_speed`,`online_edit`,`folder`,`api`,`storage_all`,`no_review`,`remark`,`addtime`) VALUES (5, '基础会员', 0, 200, 100, 500, -1, 0, 1, 0, 0, 0, '额度翻倍', NOW());
INSERT INTO `pre_level` (`id`,`name`,`type`,`sort`,`upload_limit`,`upload_size`,`down_speed`,`online_edit`,`folder`,`api`,`storage_all`,`no_review`,`remark`,`addtime`) VALUES (6, '标准会员', 0, 300, 300, 1024, -1, 1, 1, 0, 0, 0, '日常够用，带在线编辑', NOW());
INSERT INTO `pre_level` (`id`,`name`,`type`,`sort`,`upload_limit`,`upload_size`,`down_speed`,`online_edit`,`folder`,`api`,`storage_all`,`no_review`,`remark`,`addtime`) VALUES (7, '进阶会员', 0, 400, 600, 2048, 0, 1, 1, 1, 0, 0, '下载不限速，可用上传 API', NOW());
INSERT INTO `pre_level` (`id`,`name`,`type`,`sort`,`upload_limit`,`upload_size`,`down_speed`,`online_edit`,`folder`,`api`,`storage_all`,`no_review`,`remark`,`addtime`) VALUES (8, '尊享会员', 0, 500, 1500, 5120, 0, 1, 1, 1, 1, 0, '可用全部存储', NOW());
INSERT INTO `pre_level` (`id`,`name`,`type`,`sort`,`upload_limit`,`upload_size`,`down_speed`,`online_edit`,`folder`,`api`,`storage_all`,`no_review`,`remark`,`addtime`) VALUES (9, '旗舰会员', 0, 600, 0, 0, 0, 1, 1, 1, 1, 0, '数量和大小都不限', NOW());

DROP TABLE IF EXISTS `pre_plan`;
CREATE TABLE `pre_plan` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(64) NOT NULL COMMENT '套餐名称',
  `category` varchar(32) NOT NULL DEFAULT '' COMMENT '套餐分类，购买页按它分区展示',
  `price` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '售价（元）',
  `upload_limit` int(11) NOT NULL DEFAULT '-1' COMMENT '每日上传数量：-1继承全站 0不限 N每天N个',
  `limit_mode` varchar(8) NOT NULL DEFAULT 'set' COMMENT '每日数量发放方式：set设为 add在现有基础上增加',
  `upload_size` int(11) NOT NULL DEFAULT '-1' COMMENT '单文件大小MB：-1继承全站 0不限',
  `down_speed` int(11) NOT NULL DEFAULT '-1' COMMENT '下载限速KB/s：-1不改动 0不限速 N每秒N KB',
  `online_edit` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否含在线编辑权限：0不含 1含',
  `level_id` int(11) NOT NULL DEFAULT '0' COMMENT '卖的是哪个会员等级，0表示附加包或旧版套餐',
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
  `down_speed` int(11) NOT NULL DEFAULT '-1' COMMENT '下单时的套餐下载限速快照',
  `online_edit` tinyint(1) NOT NULL DEFAULT '0' COMMENT '下单时套餐是否含在线编辑的快照',
  `level_id` int(11) NOT NULL DEFAULT '0' COMMENT '下单时的会员等级快照',
  `kind` varchar(10) NOT NULL DEFAULT '' COMMENT '订单类型：level upgrade bonus edit，空为旧订单',
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

INSERT INTO `pre_plan` (`name`,`category`,`price`,`level_id`,`upload_limit`,`limit_mode`,`upload_size`,`days`,`remark`,`sort`,`enable`,`addtime`) VALUES ('入门会员月卡', '入门会员', '2.90', 4, -1, 'set', -1, 30, NULL, 10, 1, NOW());
INSERT INTO `pre_plan` (`name`,`category`,`price`,`level_id`,`upload_limit`,`limit_mode`,`upload_size`,`days`,`remark`,`sort`,`enable`,`addtime`) VALUES ('入门会员季卡', '入门会员', '7.90', 4, -1, 'set', -1, 90, '三个月，折合每月更便宜', 11, 1, NOW());
INSERT INTO `pre_plan` (`name`,`category`,`price`,`level_id`,`upload_limit`,`limit_mode`,`upload_size`,`days`,`remark`,`sort`,`enable`,`addtime`) VALUES ('入门会员年卡', '入门会员', '28.00', 4, -1, 'set', -1, 365, '整年省心，折合每月最便宜', 12, 1, NOW());
INSERT INTO `pre_plan` (`name`,`category`,`price`,`level_id`,`upload_limit`,`limit_mode`,`upload_size`,`days`,`remark`,`sort`,`enable`,`addtime`) VALUES ('入门会员永久', '入门会员', '68.00', 4, -1, 'set', -1, 0, '一次买断，不再到期', 13, 1, NOW());
INSERT INTO `pre_plan` (`name`,`category`,`price`,`level_id`,`upload_limit`,`limit_mode`,`upload_size`,`days`,`remark`,`sort`,`enable`,`addtime`) VALUES ('基础会员月卡', '基础会员', '4.90', 5, -1, 'set', -1, 30, NULL, 20, 1, NOW());
INSERT INTO `pre_plan` (`name`,`category`,`price`,`level_id`,`upload_limit`,`limit_mode`,`upload_size`,`days`,`remark`,`sort`,`enable`,`addtime`) VALUES ('基础会员季卡', '基础会员', '12.90', 5, -1, 'set', -1, 90, '三个月，折合每月更便宜', 21, 1, NOW());
INSERT INTO `pre_plan` (`name`,`category`,`price`,`level_id`,`upload_limit`,`limit_mode`,`upload_size`,`days`,`remark`,`sort`,`enable`,`addtime`) VALUES ('基础会员年卡', '基础会员', '45.00', 5, -1, 'set', -1, 365, '整年省心，折合每月最便宜', 22, 1, NOW());
INSERT INTO `pre_plan` (`name`,`category`,`price`,`level_id`,`upload_limit`,`limit_mode`,`upload_size`,`days`,`remark`,`sort`,`enable`,`addtime`) VALUES ('基础会员永久', '基础会员', '98.00', 5, -1, 'set', -1, 0, '一次买断，不再到期', 23, 1, NOW());
INSERT INTO `pre_plan` (`name`,`category`,`price`,`level_id`,`upload_limit`,`limit_mode`,`upload_size`,`days`,`remark`,`sort`,`enable`,`addtime`) VALUES ('标准会员月卡', '标准会员', '9.90', 6, -1, 'set', -1, 30, NULL, 30, 1, NOW());
INSERT INTO `pre_plan` (`name`,`category`,`price`,`level_id`,`upload_limit`,`limit_mode`,`upload_size`,`days`,`remark`,`sort`,`enable`,`addtime`) VALUES ('标准会员季卡', '标准会员', '25.00', 6, -1, 'set', -1, 90, '三个月，折合每月更便宜', 31, 1, NOW());
INSERT INTO `pre_plan` (`name`,`category`,`price`,`level_id`,`upload_limit`,`limit_mode`,`upload_size`,`days`,`remark`,`sort`,`enable`,`addtime`) VALUES ('标准会员年卡', '标准会员', '88.00', 6, -1, 'set', -1, 365, '整年省心，折合每月最便宜', 32, 1, NOW());
INSERT INTO `pre_plan` (`name`,`category`,`price`,`level_id`,`upload_limit`,`limit_mode`,`upload_size`,`days`,`remark`,`sort`,`enable`,`addtime`) VALUES ('标准会员永久', '标准会员', '198.00', 6, -1, 'set', -1, 0, '一次买断，不再到期', 33, 1, NOW());
INSERT INTO `pre_plan` (`name`,`category`,`price`,`level_id`,`upload_limit`,`limit_mode`,`upload_size`,`days`,`remark`,`sort`,`enable`,`addtime`) VALUES ('进阶会员月卡', '进阶会员', '14.90', 7, -1, 'set', -1, 30, NULL, 40, 1, NOW());
INSERT INTO `pre_plan` (`name`,`category`,`price`,`level_id`,`upload_limit`,`limit_mode`,`upload_size`,`days`,`remark`,`sort`,`enable`,`addtime`) VALUES ('进阶会员季卡', '进阶会员', '39.00', 7, -1, 'set', -1, 90, '三个月，折合每月更便宜', 41, 1, NOW());
INSERT INTO `pre_plan` (`name`,`category`,`price`,`level_id`,`upload_limit`,`limit_mode`,`upload_size`,`days`,`remark`,`sort`,`enable`,`addtime`) VALUES ('进阶会员年卡', '进阶会员', '128.00', 7, -1, 'set', -1, 365, '整年省心，折合每月最便宜', 42, 1, NOW());
INSERT INTO `pre_plan` (`name`,`category`,`price`,`level_id`,`upload_limit`,`limit_mode`,`upload_size`,`days`,`remark`,`sort`,`enable`,`addtime`) VALUES ('进阶会员永久', '进阶会员', '298.00', 7, -1, 'set', -1, 0, '一次买断，不再到期', 43, 1, NOW());
INSERT INTO `pre_plan` (`name`,`category`,`price`,`level_id`,`upload_limit`,`limit_mode`,`upload_size`,`days`,`remark`,`sort`,`enable`,`addtime`) VALUES ('尊享会员月卡', '尊享会员', '19.90', 8, -1, 'set', -1, 30, NULL, 50, 1, NOW());
INSERT INTO `pre_plan` (`name`,`category`,`price`,`level_id`,`upload_limit`,`limit_mode`,`upload_size`,`days`,`remark`,`sort`,`enable`,`addtime`) VALUES ('尊享会员季卡', '尊享会员', '49.00', 8, -1, 'set', -1, 90, '三个月，折合每月更便宜', 51, 1, NOW());
INSERT INTO `pre_plan` (`name`,`category`,`price`,`level_id`,`upload_limit`,`limit_mode`,`upload_size`,`days`,`remark`,`sort`,`enable`,`addtime`) VALUES ('尊享会员年卡', '尊享会员', '168.00', 8, -1, 'set', -1, 365, '整年省心，折合每月最便宜', 52, 1, NOW());
INSERT INTO `pre_plan` (`name`,`category`,`price`,`level_id`,`upload_limit`,`limit_mode`,`upload_size`,`days`,`remark`,`sort`,`enable`,`addtime`) VALUES ('尊享会员永久', '尊享会员', '398.00', 8, -1, 'set', -1, 0, '一次买断，不再到期', 53, 1, NOW());
INSERT INTO `pre_plan` (`name`,`category`,`price`,`level_id`,`upload_limit`,`limit_mode`,`upload_size`,`days`,`remark`,`sort`,`enable`,`addtime`) VALUES ('旗舰会员月卡', '旗舰会员', '29.90', 9, -1, 'set', -1, 30, NULL, 60, 1, NOW());
INSERT INTO `pre_plan` (`name`,`category`,`price`,`level_id`,`upload_limit`,`limit_mode`,`upload_size`,`days`,`remark`,`sort`,`enable`,`addtime`) VALUES ('旗舰会员季卡', '旗舰会员', '79.00', 9, -1, 'set', -1, 90, '三个月，折合每月更便宜', 61, 1, NOW());
INSERT INTO `pre_plan` (`name`,`category`,`price`,`level_id`,`upload_limit`,`limit_mode`,`upload_size`,`days`,`remark`,`sort`,`enable`,`addtime`) VALUES ('旗舰会员年卡', '旗舰会员', '258.00', 9, -1, 'set', -1, 365, '整年省心，折合每月最便宜', 62, 1, NOW());
INSERT INTO `pre_plan` (`name`,`category`,`price`,`level_id`,`upload_limit`,`limit_mode`,`upload_size`,`days`,`remark`,`sort`,`enable`,`addtime`) VALUES ('旗舰会员永久', '旗舰会员', '598.00', 9, -1, 'set', -1, 0, '一次买断，不再到期', 63, 1, NOW());
INSERT INTO `pre_plan` (`name`,`category`,`price`,`upload_limit`,`limit_mode`,`upload_size`,`days`,`remark`,`sort`,`enable`,`addtime`) VALUES ('加量包 +50', '加量包', '3.00', 50, 'add', -1, 30, '30 天内每天多传 50 个', 100, 1, NOW());
INSERT INTO `pre_plan` (`name`,`category`,`price`,`upload_limit`,`limit_mode`,`upload_size`,`days`,`remark`,`sort`,`enable`,`addtime`) VALUES ('加量包 +100', '加量包', '5.00', 100, 'add', -1, 30, '30 天内每天多传 100 个', 101, 1, NOW());
INSERT INTO `pre_plan` (`name`,`category`,`price`,`upload_limit`,`limit_mode`,`upload_size`,`days`,`remark`,`sort`,`enable`,`addtime`) VALUES ('加量包 +200', '加量包', '8.00', 200, 'add', -1, 30, '30 天内每天多传 200 个', 102, 1, NOW());
INSERT INTO `pre_plan` (`name`,`category`,`price`,`upload_limit`,`limit_mode`,`upload_size`,`days`,`remark`,`sort`,`enable`,`addtime`) VALUES ('加量包 +500', '加量包', '18.00', 500, 'add', -1, 30, '30 天内每天多传 500 个', 103, 1, NOW());
INSERT INTO `pre_plan` (`name`,`category`,`price`,`upload_limit`,`limit_mode`,`upload_size`,`days`,`remark`,`sort`,`enable`,`addtime`) VALUES ('加量包 +1000', '加量包', '30.00', 1000, 'add', -1, 30, '30 天内每天多传 1000 个', 104, 1, NOW());
INSERT INTO `pre_plan` (`name`,`category`,`price`,`upload_limit`,`limit_mode`,`upload_size`,`days`,`remark`,`sort`,`enable`,`addtime`) VALUES ('加量包 +2000', '加量包', '50.00', 2000, 'add', -1, 30, '30 天内每天多传 2000 个，量大更划算', 105, 1, NOW());
INSERT INTO `pre_plan` (`name`,`category`,`price`,`upload_limit`,`limit_mode`,`upload_size`,`online_edit`,`days`,`remark`,`sort`,`enable`,`addtime`) VALUES ('在线编辑月卡', '在线编辑', '3.00', -1, 'set', -1, 1, 30, '文本、代码文件直接在网页里改', 110, 1, NOW());
INSERT INTO `pre_plan` (`name`,`category`,`price`,`upload_limit`,`limit_mode`,`upload_size`,`online_edit`,`days`,`remark`,`sort`,`enable`,`addtime`) VALUES ('在线编辑季卡', '在线编辑', '8.00', -1, 'set', -1, 1, 90, '三个月，折合每月更便宜', 111, 1, NOW());
INSERT INTO `pre_plan` (`name`,`category`,`price`,`upload_limit`,`limit_mode`,`upload_size`,`online_edit`,`days`,`remark`,`sort`,`enable`,`addtime`) VALUES ('在线编辑年卡', '在线编辑', '25.00', -1, 'set', -1, 1, 365, '整年可用，经常改文件选它', 112, 1, NOW());
INSERT INTO `pre_plan` (`name`,`category`,`price`,`upload_limit`,`limit_mode`,`upload_size`,`online_edit`,`days`,`remark`,`sort`,`enable`,`addtime`) VALUES ('在线编辑永久', '在线编辑', '48.00', -1, 'set', -1, 1, 0, '一次买断，永久可用', 113, 1, NOW());

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
