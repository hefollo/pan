-- 1036：个人空间覆盖额度；旧用户默认按等级，不改变既有空间包。
ALTER TABLE `pre_user` ADD COLUMN `space` int(11) NOT NULL DEFAULT '-1' COMMENT '个人存储空间MB：-1按等级，0不限，正数覆盖等级容量' AFTER `down_speed`;
