-- 1033：新增「上传流量包」套餐，相关决定 DEC-20261025-002。
-- 套餐、订单加 upload_traffic（上传流量包加多少 MB，0 表示不是上传流量包），用户加 upload_traffic_pack（上传流量包余额，字节，永久有效）。
-- 默认值都是 0，升级前后谁都不受影响；原来的「流量包」（分享流量）不动。
-- 各条语句分开写：某一条因为字段已存在而报重复结构时，update.php 会跳过它，其余照常执行。

ALTER TABLE `pre_plan` ADD COLUMN `upload_traffic` int(11) NOT NULL DEFAULT '0' COMMENT '上传流量包加多少MB，0表示不是上传流量包' AFTER `traffic`;

ALTER TABLE `pre_order` ADD COLUMN `upload_traffic` int(11) NOT NULL DEFAULT '0' COMMENT '下单时的上传流量包MB快照' AFTER `traffic`;

ALTER TABLE `pre_user` ADD COLUMN `upload_traffic_pack` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT '上传流量包余额（字节），永久有效，月上传流量用完后才扣' AFTER `traffic_pack`;
