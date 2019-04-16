CREATE TABLE IF NOT EXISTS `#__jshopping_order_fiscalization_status` (
	`id` int(10) NOT NULL AUTO_INCREMENT,
	`order_id` int(10) NOT NULL,
	`order_number` varchar(25),
	`status` varchar(25),
	`description` TEXT,
	`datetime` VARCHAR(255),
	`event` VARCHAR(255),

	PRIMARY KEY (`id`),
	KEY `order_id` (`order_id`)
);
