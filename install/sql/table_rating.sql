CREATE TABLE `rating` (
    `rate_id` int(5) unsigned NOT NULL AUTO_INCREMENT,
    `page_id` int(8) NOT NULL,
    `user_id` int(5) NOT NULL,
    `comment` text NOT NULL,
    `rollback` int(1) NOT NULL,
    `admin_id` int(5) NOT NULL,
    `reason` text NOT NULL,
    `rating1` int(3) NOT NULL,
    `rating2` int(3) NOT NULL,
    `rating3` int(3) NOT NULL,
    `ip_address` varchar(50) NOT NULL,
    `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `rate_id` (`rate_id`)
) /*$wgDBTableOptions*/;
