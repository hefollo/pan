-- 1027：会员等级，相关决定 DEC-20261010-002。
-- 新表 pre_level：每个等级一张权限表。type 为 1 游客、2 普通用户、3 管理员的三行是内置的，由 update.php 按站点现有设置补上。
-- 用户表加 level_id（0 表示普通用户，等级到期时间沿用 expiretime）和 bonus_expire（加量包自己的到期时间）。
-- 套餐、订单加 level_id（卖的是哪个等级），订单另加 kind（level 买等级或续费、upgrade 补差价升级、bonus 加量包、edit 在线编辑包，空表示升级前的旧订单）。
-- 各条语句分开写：某一条因为表或字段已存在而报重复结构时，update.php 会跳过它，其余照常执行。

CREATE TABLE IF NOT EXISTS `pre_level` (
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

ALTER TABLE `pre_user` ADD COLUMN `level_id` int(11) NOT NULL DEFAULT '0' COMMENT '会员等级，0为普通用户，到期时间见expiretime';

ALTER TABLE `pre_user` ADD COLUMN `bonus_expire` datetime DEFAULT NULL COMMENT '加量包到期时间，有加量额度且为空表示永久';

ALTER TABLE `pre_plan` ADD COLUMN `level_id` int(11) NOT NULL DEFAULT '0' COMMENT '卖的是哪个会员等级，0表示附加包或旧版套餐';

ALTER TABLE `pre_order` ADD COLUMN `level_id` int(11) NOT NULL DEFAULT '0' COMMENT '下单时的会员等级快照';

ALTER TABLE `pre_order` ADD COLUMN `kind` varchar(10) NOT NULL DEFAULT '' COMMENT '订单类型：level upgrade bonus edit，空为旧订单';
