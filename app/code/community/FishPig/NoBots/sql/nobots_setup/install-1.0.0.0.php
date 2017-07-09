<?php
/**
 * @category    Fishpig
 * @package    Fishpig_NoBots
 * @license      http://fishpig.co.uk/license.txt
 * @author       Ben Tideswell <ben@fishpig.co.uk>
 */

	$this->startSetup();
	
	$this->run("

		CREATE TABLE IF NOT EXISTS {$this->getTable('nobots_bot')} (
			`bot_id` int(11) unsigned NOT NULL auto_increment,
			`ip` varchar(32) NOT NULL default '',
			PRIMARY KEY (`bot_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='NoBots: Bot';
		
		CREATE TABLE IF NOT EXISTS {$this->getTable('nobots_bot_activity')} (
			`activity_id` int(11) unsigned NOT NULL auto_increment,
			`bot_id` int(11) unsigned NOT NULL default 0,
			`user_agent` varchar(255) NOT NULL default '',
			`created_at` TIMESTAMP NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY(`activity_id`),
			KEY `FK_BOT_ID_NOTBOTS_BOT_BOT_ID` (`bot_id`),
			CONSTRAINT `FK_BOT_ID_NOTBOTS_BOT_BOT_ID` FOREIGN KEY (`bot_id`) REFERENCES `{$this->getTable('nobots_bot')}` (`bot_id`) ON DELETE CASCADE ON UPDATE CASCADE
		)  ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='NoBots: Bot Activity';

	");

	$this->endSetup();
	