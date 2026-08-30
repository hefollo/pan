CREATE TABLE IF NOT EXISTS `pre_plan` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(64) NOT NULL COMMENT '套餐名称',
  `category` varchar(32) NOT NULL DEFAULT '' COMMENT '套餐分类，购买页按它分区展示',
  `price` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '售价（元）',
  `upload_limit` int(11) NOT NULL DEFAULT '-1' COMMENT '每日上传数量：-1继承全站 0不限 N每天N个',
  `limit_mode` varchar(8) NOT NULL DEFAULT 'set' COMMENT '每日数量发放方式：set设为 add在现有基础上增加',
  `upload_size` int(11) NOT NULL DEFAULT '-1' COMMENT '单文件大小MB：-1继承全站 0不限',
  `days` int(11) NOT NULL DEFAULT '0' COMMENT '有效期天数，0为永久',
  `remark` varchar(255) DEFAULT NULL COMMENT '套餐说明',
  `sort` int(11) NOT NULL DEFAULT '0' COMMENT '排序，小的在前',
  `enable` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否上架',
  `addtime` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `enable` (`enable`,`sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `pre_order` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `trade_no` varchar(32) NOT NULL COMMENT '商户订单号',
  `alipay_no` varchar(64) DEFAULT NULL COMMENT '支付宝交易号',
  `uid` int(11) NOT NULL DEFAULT '0',
  `plan_id` int(11) NOT NULL DEFAULT '0',
  `plan_name` varchar(64) NOT NULL COMMENT '下单时的套餐名快照',
  `price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `pay_type` varchar(10) NOT NULL DEFAULT 'alipay' COMMENT '支付方式：alipay 当面付 / epay 易支付',
  `upload_limit` int(11) NOT NULL DEFAULT '-1',
  `limit_mode` varchar(8) NOT NULL DEFAULT 'set',
  `upload_size` int(11) NOT NULL DEFAULT '-1',
  `days` int(11) NOT NULL DEFAULT '0',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0待支付 1已支付并发放 2已关闭',
  `ip` varchar(46) DEFAULT NULL,
  `addtime` datetime NOT NULL,
  `paytime` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `trade_no` (`trade_no`),
  KEY `uid` (`uid`,`id`),
  KEY `status` (`status`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

REPLACE INTO `pre_config` VALUES ('alipay_open', '0');
REPLACE INTO `pre_config` VALUES ('epay_open', '0');
REPLACE INTO `pre_config` VALUES ('pay_subject', '赞助');
REPLACE INTO `pre_config` VALUES ('epay_charset', 'UTF-8');
REPLACE INTO `pre_config` VALUES ('alipay_appid', '');
REPLACE INTO `pre_config` VALUES ('alipay_public_key', '');
REPLACE INTO `pre_config` VALUES ('alipay_private_key', '');
REPLACE INTO `pre_config` VALUES ('buy_notice', '');
