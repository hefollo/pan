-- 1022：文件表记下每条记录的物理文件存在哪个存储里。
-- 有了这个字段，站长换全站存储之后，之前上传的文件仍然回原存储去取，不会集体失效。
-- 重复执行会报「Duplicate column name」，属正常，update.php 会跳过继续往下走。

ALTER TABLE `pre_file` ADD COLUMN `storage` varchar(20) NOT NULL DEFAULT '' COMMENT '物理文件存在哪个存储里，空表示按当前存储处理';

-- 升级这一刻，库里已有的文件全都在当前那个存储里，所以照 pre_config 的 storage 一次性回填。
-- 取不到就填 local（install.sql 里 storage 的默认值就是 local）。
-- 条件是 storage 为空，所以重复执行不会覆盖已经回填好的记录。
UPDATE `pre_file` SET `storage` = COALESCE((SELECT `v` FROM `pre_config` WHERE `k`='storage' LIMIT 1), 'local') WHERE `storage`=''
