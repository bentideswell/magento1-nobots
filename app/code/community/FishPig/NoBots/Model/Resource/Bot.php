<?php
/**
 * @category    Fishpig
 * @package    Fishpig_NoBots
 * @license      http://fishpig.co.uk/license.txt
 * @author       Ben Tideswell <ben@fishpig.co.uk>
 */

	$file = Mage::getBaseDir() . DS . implode(DS, array('app', 'code', 'core', 'Mage', 'Core', 'Model', 'Resource', 'Db', 'Abstract.php'));
	
	if (is_file($file)) {
		abstract class Fishpig_NoBots_Model_Resource_Bot_Hack extends Mage_Core_Model_Resource_Db_Abstract {}
	}
	else {
		abstract class Fishpig_NoBots_Model_Resource_Bot_Hack extends Mage_Core_Model_Mysql4_Abstract {}
	}
	
class Fishpig_NoBots_Model_Resource_Bot extends Fishpig_NoBots_Model_Resource_Bot_Hack
{
	/**
	 * Init the entity type
	 *
	 * @return void
	 */
	public function _construct()
	{
		$this->_init('nobots/bot', 'bot_id');
	}
	
	/**
	 * Record bot activity
	 *
	 * @param Fishpig_NoBots_Model_Bot $bot
	 * @return Fishpig_NoBots_Model_Bot
	 */
	public function hold(Fishpig_NoBots_Model_Bot $bot)
	{
		if (!$bot->getId()) {
			return $bot;
		}
		
		$userAgent = Mage::helper('core/http')->getHttpUserAgent(true);
		
		$data = array(
			'bot_id' => $bot->getId(),
			'user_agent' => Mage::helper('core/http')->getHttpUserAgent(true),
			'created_at' => now(),
		);

		$this->_getWriteAdapter()->insert(
			$this->getActivityTable(), $data
		);
		
		return $bot;
	}
	
	/**
	 * Check whether bot has activity
	 *
	 * @param Fishpig_NoBots_Model_Bot $bot
	 * @return Fishpig_NoBots_Model_Bot
	 */	
	public function hasActivity(Fishpig_NoBots_Model_Bot $bot)
	{
		$select = $this->_getReadAdapter()
			->select()
			->from($this->getTable('nobots/bot_activity'), 'activity_id')
			->limit(1);
			
		return count($this->_getReadAdapter()->fetchAll($select)) > 0;
	}

	/**
	 * Retrieve the first activity
	 *
	 * @param Fishpig_NoBots_Model_Bot $bot
	 * @return array
	 */
	public function getFirstActivity(Fishpig_NoBots_Model_Bot $bot)
	{
		$select = $this->_getReadAdapter()
			->select()
			->from($this->getTable('nobots/bot_activity'), '*')
			->where('bot_id=?', $bot->getId())
			->order('created_at DESC')
			->limit(1);
			
		return $this->_getReadAdapter()->fetchRow($select);
	}
	
	/**
	 * Retrieve the acitivity table name
	 *
	 * @return string
	 */
	public function getActivityTable()
	{
		return $this->getTable('nobots/bot_activity');
	}
}
