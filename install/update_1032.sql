-- 1032：会员等级加「每月上传流量」，相关决定 DEC-20261025-001。
-- 原来上传只按建成的文件记录限次数，没传完、校验失败的上传不受任何限制，可以无限往站里灌数据。
-- 等级加 upload_traffic（MB/月）：-1 同普通用户、0 不限；游客、普通用户必须是具体值，升级时写 0（不限），升级前后谁都不受影响。
-- 新表 pre_upload_traffic：每个上传者每个月本站实际收到了多少字节，ukey 是 u:<uid>（登录用户）或 ip:<ipkey>（游客）。
-- 各条语句分开写：某一条因为表或字段已存在而报重复结构时，update.php 会跳过它，其余照常执行。

ALTER TABLE `pre_level` ADD COLUMN `upload_traffic` int(11) NOT NULL DEFAULT '-1' COMMENT '每月上传流量MB：-1同普通用户（游客、普通用户须填具体值） 0不限' AFTER `traffic`;

UPDATE `pre_level` SET `upload_traffic`=0 WHERE `type` IN (1,2,3) AND `upload_traffic`<0;

CREATE TABLE IF NOT EXISTS `pre_upload_traffic` (
  `ukey` varchar(80) NOT NULL COMMENT 'u:uid 是登录用户，ip:ipkey 是游客',
  `month` char(6) NOT NULL COMMENT '年月，如202610',
  `bytes` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT '这个月本站收到的上传字节数（含没传完、校验失败的）',
  PRIMARY KEY (`ukey`,`month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='上传流量用量';
