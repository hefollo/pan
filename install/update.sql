REPLACE INTO `pre_config` VALUES ('type_image', 'png|jpg|jpeg|gif|bmp|webp|ico|svg|svgz|tif|tiff|heic|exif');
REPLACE INTO `pre_config` VALUES ('type_audio', 'mp3|wav|ogg|m4a|flac|aac');
REPLACE INTO `pre_config` VALUES ('type_video', 'mp4|webm|flv|f4v|mov|3gp|3gpp|avi|mpg|mpeg|wmv|mkv|ts|dat|asf|mts|m2ts|m3u8|m4v');
REPLACE INTO `pre_config` VALUES ('filesearch', '1');
INSERT IGNORE INTO `pre_config` VALUES ('site_theme', 'cloud');

ALTER TABLE `pre_file`
ADD COLUMN `uid` int(11) unsigned NOT NULL DEFAULT '0';

ALTER TABLE `pre_file`
ADD INDEX `uid` (`uid`);

CREATE TABLE IF EXISTS `pre_user` (
  `uid` int(11) NOT NULL AUTO_INCREMENT,
  `type` varchar(20) NOT NULL,
  `openid` varchar(150) NOT NULL,
  `nickname` varchar(255) NOT NULL,
  `faceimg` varchar(255) DEFAULT NULL,
  `enable` tinyint(1) NOT NULL DEFAULT '1',
  `regip` varchar(20) DEFAULT NULL,
  `loginip` varchar(20) DEFAULT NULL,
  `level` tinyint(4) NOT NULL DEFAULT '0',
  `upload_size` int(11) NOT NULL DEFAULT '-1',
  `upload_limit` int(11) NOT NULL DEFAULT '-1',
  `expiretime` datetime DEFAULT NULL,
  `addtime` datetime NOT NULL,
  `lasttime` datetime NOT NULL,
  PRIMARY KEY (`uid`),
  KEY `openid` (`openid`,`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1000;

ALTER TABLE `pre_user`
ADD COLUMN `upload_size` int(11) NOT NULL DEFAULT '-1' AFTER `level`;

ALTER TABLE `pre_user`
ADD COLUMN `upload_limit` int(11) NOT NULL DEFAULT '-1' AFTER `upload_size`;

ALTER TABLE `pre_user`
ADD COLUMN `expiretime` datetime DEFAULT NULL AFTER `upload_limit`;
