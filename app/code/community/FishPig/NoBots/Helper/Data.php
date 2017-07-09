<?php
/**
 * @category    Fishpig
 * @package    Fishpig_NoBots
 * @license      http://fishpig.co.uk/license.txt
 * @author       Ben Tideswell <ben@fishpig.co.uk>
 */

class Fishpig_NoBots_Helper_Data extends Mage_Core_Helper_Abstract
{
	/**
	 * Retrieve the Bot model
	 *
	 * @return Fishpig_NoBots_Model_Bot|false
	 */
	public function getBot($createIfNotExists = true)
	{
		$remoteAddr = $this->getRemoteAddr();

		if (in_array($remoteAddr, $this->getWhitelistedIps())) {
			return false;
		}

		$bot = Mage::getModel('nobots/bot')->load($remoteAddr, 'ip');
		
		if ($createIfNotExists && !$bot->getId()) {
			$bot->setIp($remoteAddr);
			
			try {
				$bot->save();
			}
			catch (Exception $e) {
				Mage::logException($e);
			}
		}
		
		return $bot->getId() ? $bot : false;
	}
	
	/**
	 * Get an array of whitelisted IPs
	 *
	 * @return array
	 */
	public function getWhitelistedIps()
	{
		$whitelist = trim(Mage::getStoreConfig('nobots/settings/whitelist'));
		
		if (!$whitelist) {
			return array();
		}
		
		$whitelist = explode("\n", $whitelist);
		
		foreach($whitelist as $k => $v) {
			if (trim($v) === '') {
				unset($whitelist[$k]);
			}
		}
		
		return $whitelist;
	}
	
	/**
	 * Retrieve the remote address
	 *
	 * @return string
	 */
	public function getRemoteAddr()
	{
		if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && $_SERVER['HTTP_X_FORWARDED_FOR']) {
			return $_SERVER['HTTP_X_FORWARDED_FOR'];
		}

		return Mage::helper('core/http')->getRemoteAddr(false);
	}

	/**
	 * Retrieve the honey pot URL
	 *
	 * @return string
	 */
	public function getHoneyPotUrl()
	{
		return rtrim(Mage::getUrl('', array(
			'_direct' => $this->getHoneyPotUris(0),
			'_secure' => Mage::app()->getRequest()->isSecure())
		), '/') . '/';
	}	

	/**
	 * Retrieve the existing valid honey opt URI's
	 *
	 * @param int $key = null
	 * @return false|string|array
	 */
	public function getHoneyPotUris($key = null)
	{
		$uris = array();

		for($it = 0; $it <= 2; $it++) {
			$uris[] = substr(md5(date('W', $it ? strtotime('-'.$it .' week') : time()) . dirname(__FILE__)), 0, 16);
		}
		
		if (is_null($key)) {
			return $uris;
		}
		
		if (isset($uris[$key])) {
			return $uris[$key];
		}
		
		return false;
	}
	
	/**
	 * Retrieve the verification URL
	 *
	 * @return string
	 */
	public function getVerificationUrl($includeRef = true)
	{
		return Mage::getUrl('human/verify', array(
			'_query' => array(
				Mage_Core_Controller_Varien_Action::PARAM_NAME_URL_ENCODED => Mage::helper('core')->urlEncode(Mage::getUrl('*/*/*', array('_current' => true, '_use_rewrite' => true))),
			)
		));
	}
}
