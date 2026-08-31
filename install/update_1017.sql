ALTER TABLE `pre_file` MODIFY COLUMN `ip` varchar(45) NOT NULL;
ALTER TABLE `pre_file` ADD COLUMN `ipkey` varchar(45) NOT NULL DEFAULT '' COMMENT '限流维度：IPv4存完整地址，IPv6存/64前缀' AFTER `ip`;
ALTER TABLE `pre_file` ADD KEY `ipkey` (`ipkey`,`addtime`);
UPDATE `pre_file` SET `ipkey`=`ip` WHERE `ipkey`='' AND `ip` NOT LIKE '%:%';
