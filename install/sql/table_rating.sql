CREATE TABLE /*_*/rating (
  `rate_id` int(5) UNSIGNED NOT NULL,
  `page_id` int(8) NOT NULL DEFAULT '0',
  `user_id` int(5) NOT NULL DEFAULT '0',
  `comment` text NOT NULL,
  `rollback` int(1) NOT NULL DEFAULT '0',
  `admin_id` int(5) NOT NULL DEFAULT '0',
  `reason` text NOT NULL,
  `rating1` int(3) NOT NULL DEFAULT '0',
  `rating2` int(3) NOT NULL DEFAULT '0',
  `rating3` int(3) NOT NULL DEFAULT '0',
  `ip_address` varchar(50) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) /*$wgDBTableOptions*/;

ALTER TABLE /*_*/rating
  ADD UNIQUE KEY `rate_id` (`rate_id`);

ALTER TABLE /*_*/rating
  MODIFY `rate_id` int(5) UNSIGNED NOT NULL AUTO_INCREMENT;