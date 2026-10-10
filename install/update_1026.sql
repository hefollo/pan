-- 1026：套餐可带在线编辑权限，相关决定 DEC-20261010-001。
-- 套餐、订单快照各加一列 online_edit：0 不含在线编辑，1 含在线编辑。
-- 用户表加两列：online_edit（0 未开通 1 已开通）和 edit_expire（在线编辑到期时间，已开通且为空表示永久）。
-- 在线编辑的有效期单独计算，不和上传权限共用 expiretime。
-- 四条语句分开写：某一条因为字段已存在而报 1060 时，update.php 会跳过它，其余照常执行。

ALTER TABLE `pre_plan` ADD COLUMN `online_edit` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否含在线编辑权限：0不含 1含';

ALTER TABLE `pre_order` ADD COLUMN `online_edit` tinyint(1) NOT NULL DEFAULT '0' COMMENT '下单时套餐是否含在线编辑的快照';

ALTER TABLE `pre_user` ADD COLUMN `online_edit` tinyint(1) NOT NULL DEFAULT '0' COMMENT '在线编辑：0未开通 1已开通';

ALTER TABLE `pre_user` ADD COLUMN `edit_expire` datetime DEFAULT NULL COMMENT '在线编辑到期时间，已开通且为空表示永久';
