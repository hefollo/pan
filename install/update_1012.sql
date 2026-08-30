ALTER TABLE `pre_plan` ADD COLUMN `category` varchar(32) NOT NULL DEFAULT '' COMMENT '套餐分类，购买页按它分区展示' AFTER `name`;
