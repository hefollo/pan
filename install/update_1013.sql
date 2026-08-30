ALTER TABLE `pre_order` ADD COLUMN `pay_type` varchar(10) NOT NULL DEFAULT 'alipay' COMMENT '支付方式：alipay 当面付 / epay 易支付' AFTER `price`;
REPLACE INTO `pre_config` VALUES ('epay_open', '0');
