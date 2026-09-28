-- 1024：文件表加所在文件夹 folder_id（0 为根目录）和复制标记 copied。
-- copied=1 的记录是用户在文件夹里复制出来的，不计入每日上传数和每分钟上传频率。
-- 三条语句分开写：某一条因为字段或索引已存在而报 1060/1061 时，update.php 会跳过它，其余照常执行。

ALTER TABLE `pre_file` ADD COLUMN `folder_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '所在的用户文件夹，0 表示根目录';

ALTER TABLE `pre_file` ADD COLUMN `copied` tinyint(1) NOT NULL DEFAULT '0' COMMENT '用户复制出来的记录，不计入每日上传数';

ALTER TABLE `pre_file` ADD INDEX `folder_id` (`folder_id`)
