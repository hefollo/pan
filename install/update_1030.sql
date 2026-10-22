-- 1030：会员等级加「每月分享流量」，可购买流量包，相关决定 DEC-20261022-001。
-- 等级加 traffic（MB/月）：-1 同普通用户、0 不限；游客、普通用户必须是具体值，升级时写 0（不限），升级前后谁都不受影响。
-- 套餐、订单加 traffic（流量包加多少 MB），用户加 traffic_pack（流量包余额，字节，永久有效）。
-- 新表 pre_traffic：每个上传者每个月被取走了多少字节，uid 为 0 的一行是游客上传的文件（共用游客等级的额度）。
-- 各条语句分开写：某一条因为表或字段已存在而报重复结构时，update.php 会跳过它，其余照常执行。

ALTER TABLE `pre_level` ADD COLUMN `traffic` int(11) NOT NULL DEFAULT '-1' COMMENT '每月分享流量MB：-1同普通用户（游客、普通用户须填具体值） 0不限' AFTER `down_speed`;

UPDATE `pre_level` SET `traffic`=0 WHERE `type` IN (1,2,3) AND `traffic`<0;

ALTER TABLE `pre_plan` ADD COLUMN `traffic` int(11) NOT NULL DEFAULT '0' COMMENT '流量包加多少MB，0表示不是流量包' AFTER `online_edit`;

ALTER TABLE `pre_order` ADD COLUMN `traffic` int(11) NOT NULL DEFAULT '0' COMMENT '下单时的流量包MB快照' AFTER `online_edit`;

ALTER TABLE `pre_user` ADD COLUMN `traffic_pack` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT '流量包余额（字节），永久有效，月流量用完后才扣' AFTER `bonus_expire`;

CREATE TABLE IF NOT EXISTS `pre_traffic` (
  `uid` int(11) unsigned NOT NULL COMMENT '上传者，0是游客上传的文件',
  `month` char(6) NOT NULL COMMENT '年月，如202610',
  `bytes` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT '这个月被取走的字节数',
  PRIMARY KEY (`uid`,`month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='分享流量用量';
