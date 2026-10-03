-- 1025：套餐下载限速，相关决定 DEC-20261003-002。
-- 套餐、订单快照、用户三张表各加一列 down_speed，单位 KB/s：
--   -1 套餐里表示「不改动」，用户身上表示「跟随身份档位」（游客 / 普通登录用户 / 有效高级用户的速度）
--    0 不限速
--    N 每秒 N KB
-- 三条语句分开写：某一条因为字段已存在而报 1060 时，update.php 会跳过它，其余照常执行。

ALTER TABLE `pre_plan` ADD COLUMN `down_speed` int(11) NOT NULL DEFAULT '-1' COMMENT '下载限速KB/s：-1不改动 0不限速 N每秒N KB';

ALTER TABLE `pre_order` ADD COLUMN `down_speed` int(11) NOT NULL DEFAULT '-1' COMMENT '下单时的套餐下载限速快照';

ALTER TABLE `pre_user` ADD COLUMN `down_speed` int(11) NOT NULL DEFAULT '-1' COMMENT '下载限速KB/s：-1跟随身份档位 0不限速 N每秒N KB';
