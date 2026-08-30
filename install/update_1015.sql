ALTER TABLE `pre_user` ADD COLUMN `bonus_limit` int(11) NOT NULL DEFAULT '0' COMMENT '加量包累计的每日额度，独立记录，不会被时长套餐覆盖' AFTER `upload_limit`;
