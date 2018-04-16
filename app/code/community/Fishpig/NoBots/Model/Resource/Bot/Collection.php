<?php
/**
 * @category    Fishpig
 * @package    Fishpig_NoBots
 * @license      http://fishpig.co.uk/license.txt
 * @author       Ben Tideswell <ben@fishpig.co.uk>
 */

	$file = Mage::getBaseDir() . DS . implode(DS, array('app', 'code', 'core', 'Mage', 'Core', 'Model', 'Resource', 'Db', 'Abstract.php'));
	
	if (is_file($file)) {
		abstract class Fishpig_NoBots_Model_Resource_Bot_Collection_Hack extends Mage_Core_Model_Resource_Db_Collection_Abstract {}
	}
	else {
		abstract class Fishpig_NoBots_Model_Resource_Bot_Collection_Hack extends Mage_Core_Model_Mysql4_Collection_Abstract {}
	}

class Fishpig_NoBots_Model_Resource_Bot_Collection extends Fishpig_NoBots_Model_Resource_Bot_Collection_Hack
{
	/**
	 * Init the entity type
	 *
	 */
	public function _construct()
	{
		$this->_init('nobots/bot');
	}
	
	public function addFirstActivityToSelect()
	{
		$this->getSelect()
			->join(
				array('_activity' => $this->getResource()->getActivityTable()),
				'_activity.bot_id = main_table.bot_id',
				'created_at'
				
			);
		
		return $this;
	}
}
