-- 1035：会员存储空间与按订单独立到期的空间包。旧等级默认不限，不改变现有权益。
ALTER TABLE `pre_level` ADD COLUMN `space` int(11) NOT NULL DEFAULT '0' COMMENT '存储空间MB：-1同普通用户，0不限' AFTER `upload_traffic`;

ALTER TABLE `pre_plan` ADD COLUMN `space` int(11) NOT NULL DEFAULT '0' COMMENT '空间包容量MB，0不是空间包' AFTER `upload_traffic`;

ALTER TABLE `pre_order` ADD COLUMN `space` int(11) NOT NULL DEFAULT '0' COMMENT '下单时空间包容量MB快照' AFTER `upload_traffic`;

CREATE TABLE IF NOT EXISTS `pre_space_pack` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uid` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `bytes` bigint(20) unsigned NOT NULL,
  `addtime` datetime NOT NULL,
  `expiretime` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_id` (`order_id`),
  KEY `uid_expire` (`uid`,`expiretime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
