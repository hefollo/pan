-- 1024：用户文件夹（虚拟目录），相关决定 DEC-20260928-001。
-- 文件夹只是数据库里的归属关系，物理文件仍按内容 hash 平铺在存储里，外链不变。
-- 独立新表 + IF NOT EXISTS，update.php 每次都跑，表被漏建时再点一次升级就能补回来。
-- 文件表的两个新字段在 update_1024_file.sql 里，由 update.php 先查表结构再决定跑不跑。

CREATE TABLE IF NOT EXISTS `pre_folder` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `uid` int(11) unsigned NOT NULL DEFAULT '0',
  `gkey` varchar(32) NOT NULL DEFAULT '' COMMENT '游客文件夹归属的会话标识，登录用户的文件夹为空',
  `parent_id` int(11) unsigned NOT NULL DEFAULT '0',
  `name` varchar(100) NOT NULL,
  `addtime` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `owner` (`uid`,`gkey`),
  KEY `addtime` (`addtime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户文件夹（虚拟目录）'
