<?php
/**
 * @category    Fishpig
 * @package    Fishpig_NoBots
 * @license      http://fishpig.co.uk/license.txt
 * @author       Ben Tideswell <ben@fishpig.co.uk>
 */

class Fishpig_NoBots_VerifyController extends Mage_Core_Controller_Front_Action
{
	/**
	 * This is the Trap action
	 * Record the acitivity and move to the verification page
	 *
	 * @return void
	 */
	public function rejectAction()
	{
		if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($bot = Mage::helper('nobots')->getBot()) !== false) {
			$bot->hold();

			$this->_redirect('*/*');
		}
		else {
			$this->_forward('noRoute');
		}
	}
	
	/**
	 * Display the verification page
	 *
	 * @return void
	 */
	public function indexAction()
	{
		$bot = Mage::helper('nobots')->getBot(false);
		
		if ($bot !== false && $bot->isBanned()) {
			$this->loadLayout();
			$this->renderLayout();
		}
		else {
			$this->_redirectUrl(Mage::getBaseUrl());
		}
	}
	
	/**
	 * Post the captcha and attemp to validate
	 *
	 * @return void
	 */
	public function postAction()
	{
		if (($bot = Mage::helper('nobots')->getBot(false)) !== false) {
			try {
				// Validate the captcha details
				Mage::helper('nobots/recaptcha')->validateCaptcha();
				
				// Captcha passed so unmark bot as spam
				$bot->release();
			}
			catch (Exception $e) {
				Mage::getSingleton('core/session')->addError($e->getMessage());
			}
		}

		// Redirect to referrer, if possible
		$this->_redirectReferer();		
	}
	
	public function __call($method, $args)
	{
		echo $method;exit;
	}
}