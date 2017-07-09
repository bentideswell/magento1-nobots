<?php
/**
 * @category    Fishpig
 * @package    Fishpig_AttributeSplashPro
 * @license      http://fishpig.co.uk/license.txt
 * @author       Ben Tideswell <ben@fishpig.co.uk>
 */

class Fishpig_NoBots_Adminhtml_NobotsController extends Mage_Adminhtml_Controller_Action
{
	/**
	 * Display the list of all splash pages
	 *
	 * @return void
	 */
	public function indexAction()
	{
		$this->loadLayout();
		$this->_title('NoBots');
		$this->renderLayout();
	}

	/**
	 * Delete a bot
	 *
	 * @return void
	 */
	public function deleteAction()
	{
		if ($botId = $this->getRequest()->getParam('id')) {
			$bot = Mage::getModel('nobots/bot')->load($botId);
			
			if ($bot->getId()) {
				try {
					$bot->delete();
				}
				catch (Exception $e) {
					Mage::logException($e);
					Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
				}
			}
		}
		
		$this->_redirect('*/*');
	}
}