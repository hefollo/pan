-- 1034：外观体系重构（主题包 + 卡片布局），相关决定 DEC-20261025-003。
-- pre_theme：站长保存过设置的主题、以及上传安装的主题，一个主题一行；内置主题没改过设置时可以没有这行。
-- pre_layout：某个主题某个页面的卡片布局，draft 是后台编辑器里还没发布的草稿，published 是访客看到的；
--   没有这行（站长没在后台改过）就用主题包自带的默认布局，所以升级前后页面完全不变。
-- 建表语句本身可重复执行。

CREATE TABLE IF NOT EXISTS `pre_theme` (
  `id` varchar(40) NOT NULL COMMENT '主题 id，即目录名',
  `source` varchar(10) NOT NULL DEFAULT 'builtin' COMMENT 'builtin 内置 / upload 站长上传',
  `version` varchar(20) NOT NULL DEFAULT '' COMMENT '安装时 theme.json 里的版本号',
  `settings` mediumtext COMMENT '主题设置（JSON），按 theme.json 的 settings 解释',
  `installed_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='主题与主题设置';

CREATE TABLE IF NOT EXISTS `pre_layout` (
  `theme` varchar(40) NOT NULL COMMENT '主题 id',
  `page` varchar(60) NOT NULL COMMENT '页面：index、upload……，后台页面以 admin/ 开头，公共外壳是 _shell',
  `draft` mediumtext COMMENT '草稿布局（JSON），只在后台预览里生效',
  `published` mediumtext COMMENT '已发布布局（JSON），访客看到的',
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`theme`,`page`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='页面卡片布局';
