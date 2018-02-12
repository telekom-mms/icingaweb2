CREATE TABLE IF NOT EXISTS `icingaweb_settings`(
  `filename` varchar(254) COLLATE utf8_unicode_ci NOT NULL,
  `data`  TEXT COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`filename`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
