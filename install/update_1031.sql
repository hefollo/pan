-- 1031：移植自 OpenList 的存储驱动（DEC-20261023-001）。
-- 很多网盘按文件 ID 而不是路径寻址，本站的存储名 → 远端文件 ID 记在新表 pre_stor_map 里，查不到时驱动自己列目录兜底。
-- 驱动参数存在 pre_config 的 stor_<类型> / stor_<类型>_state 两个键里，不需要改表结构。

CREATE TABLE IF NOT EXISTS `pre_stor_map` (
  `storage` varchar(20) NOT NULL COMMENT '存储类型键',
  `name` varchar(150) NOT NULL COMMENT '本站存储名（pre_file.hash）',
  `fid` varchar(255) NOT NULL COMMENT '远端文件 ID',
  `addtime` datetime DEFAULT NULL,
  PRIMARY KEY (`storage`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='按文件 ID 寻址的存储：本站文件名对应的远端 ID';
