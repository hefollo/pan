ALTER TABLE `pre_mailcode` ADD COLUMN `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0已发送 1已验证 2发送失败 3已作废' AFTER `trycount`;
ALTER TABLE `pre_mailcode` ADD COLUMN `sender` varchar(20) NOT NULL DEFAULT '' COMMENT '实际发出去的通道' AFTER `status`;
ALTER TABLE `pre_mailcode` ADD COLUMN `errmsg` varchar(255) NOT NULL DEFAULT '' COMMENT '发送失败的原因' AFTER `sender`;
UPDATE `pre_mailcode` SET `status`=1 WHERE `used`=1;
