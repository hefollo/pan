ALTER TABLE `pre_plan` ADD COLUMN `limit_mode` varchar(8) NOT NULL DEFAULT 'set' COMMENT '每日数量的发放方式：set设为 add在现有基础上增加' AFTER `upload_limit`;
ALTER TABLE `pre_order` ADD COLUMN `limit_mode` varchar(8) NOT NULL DEFAULT 'set' COMMENT '下单时的发放方式快照' AFTER `upload_limit`;
