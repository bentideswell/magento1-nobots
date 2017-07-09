<?php
/**
 * @category    Fishpig
 * @package    Fishpig_NoBots
 * @license      http://fishpig.co.uk/license.txt
 * @author       Ben Tideswell <ben@fishpig.co.uk>
 */

class Fishpig_NoBots_Model_Bot extends Mage_Core_Model_Abstract
{
	/**
	 * Init the entity type
	 *
	 */
	public function _construct()
	{
		$this->_init('nobots/bot');
	}
	
	/**
	 * Record bot activity
	 *
	 * @return $this
	 */
	public function hold()
	{
		return $this->getResource()->hold($this);
	}
	
	/**
	 * Release the bot
	 *
	 * @return $this
	 */
	public function release()
	{
		return $this->delete();
	}
	
	/**
	 * Determine whether bot is banned
	 *
	 * @return bool
	 */
	public function isBanned()
	{
		return $this->getResource()->hasActivity($this);
	}
	
	/**
	 * Load the first activity data
	 *
	 * @return $this
	 */
	public function loadFirstActivity()
	{
		foreach((array)$this->getResource()->getFirstActivity($this) as $k => $v) {
			$this->setData('first_activity_' . $k, $v);
		}
		
		return $this;
	}
}