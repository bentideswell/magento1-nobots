<?php
/**
 * @category    Fishpig
 * @package    Fishpig_NoBots
 * @license      http://fishpig.co.uk/license.txt
 * @author       Ben Tideswell <ben@fishpig.co.uk>
 */

class Fishpig_NoBots_Controller_Router extends Mage_Core_Controller_Varien_Router_Abstract
{
	/**
	 * Initialize Controller Router
	 *
	 * @param Varien_Event_Observer $observer
	*/
	public function initControllerRouters(Varien_Event_Observer $observer)
	{
		$observer->getEvent()->getFront()->addRouter('nobots', $this);
	}

    /**
     * Validate and Match NoBots and modify request
     *
     * @param Zend_Controller_Request_Http $request
     * @return bool
     */
    public function match(Zend_Controller_Request_Http $request)
    {
    	$frontName = $this->_getFrontName();
    	$urlKey = trim($request->getPathInfo(), '/');
    	    	
    	if (strpos($urlKey, $frontName . '/') === 0) {
	    	$parts = explode('/', $urlKey);

			if (!in_array($parts[1], array('reject', 'index', 'post'))) {
				$parts[1] = 'noRoute';
			}

			$request->setModuleName($frontName)
				->setControllerName('verify')
				->setActionName($parts[1]);
    	}
		else {
	    	$uris = Mage::helper('nobots')->getHoneyPotUris();

			if (!in_array($urlKey, $uris)) {
				return false;
			}

			$request->setModuleName($frontName)
				->setControllerName('verify')
				->setActionName('reject');
		}
		
		$request->setAlias(
			Mage_Core_Model_Url_Rewrite::REWRITE_REQUEST_PATH_ALIAS,
			$urlKey
		);

		return true;
	}

	/**
	 * Retrieve the frontName used by the module
	 *
	 * @return string
	 */
	protected function _getFrontName()
	{
		return (string)Mage::getConfig()->getNode()->frontend->routers->nobots->args->frontName;
	}
}
