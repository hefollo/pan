CREATE TABLE IF NOT EXISTS `pre_greenlog` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `file_id` int(11) unsigned NOT NULL DEFAULT '0',
  `name` varchar(255) NOT NULL DEFAULT '',
  `type` varchar(50) DEFAULT NULL,
  `hash` varchar(32) DEFAULT NULL,
  `engine` varchar(20) NOT NULL DEFAULT '' COMMENT '检测引擎：aliyun/qcloud/self/self-video',
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

CREATE TABLE IF NOT EXISTS `pre_greenjob` (
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

-- 上面那张 pre_greenlog 本该是 1019 建的，这里为什么要再带一份完整建表语句：
-- update.php 是「合并 SQL 再无条件写版本号」，1019 那次要是没跑成（比如 update_1019.sql
-- 没传上服务器），版本号照样会被写成 1019。等升到 1020 时，`$version < 1019` 不成立，
-- 1019 的建表就被跳过，而下面这三条 ALTER 全落在一张不存在的表上静默失败——
-- 结果就是检测在跑、文件也挂起了，后台却一条记录都没有。所以这里不信任版本号。
--
-- 表刚由上面建出来时已经带了这三个字段，下面的 ALTER 会报「Duplicate column name」，
-- 属正常，update.php 会跳过继续执行。
ALTER TABLE `pre_greenlog` ADD COLUMN `frames` varchar(20) NOT NULL DEFAULT '' COMMENT '视频抽帧命中数/总数';
ALTER TABLE `pre_greenlog` ADD COLUMN `hit_at` int(11) NOT NULL DEFAULT '0' COMMENT '最高分出现在第几秒';
ALTER TABLE `pre_greenlog` ADD COLUMN `shot` varchar(80) NOT NULL DEFAULT '' COMMENT '证据帧文件名';

INSERT IGNORE INTO `pre_config` VALUES ('green_video', '0');
INSERT IGNORE INTO `pre_config` VALUES ('green_video_block', '0.85');
INSERT IGNORE INTO `pre_config` VALUES ('green_video_review', '0.6');
INSERT IGNORE INTO `pre_config` VALUES ('green_video_hit', '2');
INSERT IGNORE INTO `pre_config` VALUES ('green_video_interval', '5');
INSERT IGNORE INTO `pre_config` VALUES ('green_video_frames', '40');
INSERT IGNORE INTO `pre_config` VALUES ('green_video_maxlen', '7200');
INSERT IGNORE INTO `pre_config` VALUES ('green_video_maxsize', '2048');
INSERT IGNORE INTO `pre_config` VALUES ('green_video_timeout', '30');
INSERT IGNORE INTO `pre_config` VALUES ('green_video_shot', '1');
INSERT IGNORE INTO `pre_config` VALUES ('green_poll_time', '0');
INSERT IGNORE INTO `pre_config` VALUES ('sponsor_open', '1');
