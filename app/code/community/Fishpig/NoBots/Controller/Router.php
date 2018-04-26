<?php
/*
 * @category   Fishpig
 * @package    Fishpig_NoBots
 * @license    https://fishpig.co.uk/license.txt
 * @author     Ben Tideswell <ben@fishpig.co.uk>
 */
class Fishpig_NoBots_Controller_Router extends Mage_Core_Controller_Varien_Router_Abstract
{
	/*
	 * Initialize Controller Router
	 *
	 * @param Varien_Event_Observer $observer
	 */
	public function initControllerRouters(Varien_Event_Observer $observer)
	{
		$bot = Mage::helper('nobots')->getBot(false);

		if ($bot !== false && $bot->isBanned()) {
			$this->showBlockPage();
		}
		
		$observer->getEvent()->getFront()->addRouter('nobots', $this);
	}

  /*
   * Validate and Match NoBots and modify request
   *
   * @param Zend_Controller_Request_Http $request
   * @return bool
   */
  public function match(Zend_Controller_Request_Http $request)
  {
   	$urlKey = trim($request->getPathInfo(), '/');
    $uris   = Mage::helper('nobots')->getHoneyPotUris();

		if (in_array($urlKey, $uris)) {
			if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($bot = Mage::helper('nobots')->getBot()) !== false) {
				$bot->hold();

				$this->showBlockPage();
			}
		}

		return false;
	}
	
	/*
	 *
	 *
	 *
	 */
	protected function showBlockPage()
	{
		header('HTTP/1.0 403 Forbidden');
		echo Mage::getSingleton('core/layout')->createBlock('core/template')->setTemplate('nobots/blocked.phtml')->toHtml();
		exit;
	}
}
