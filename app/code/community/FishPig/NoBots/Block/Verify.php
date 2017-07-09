<?php
/**
 * @category    Fishpig
 * @package    Fishpig_NoBots
 * @license      http://fishpig.co.uk/license.txt
 * @author       Ben Tideswell <ben@fishpig.co.uk>
 */

class Fishpig_NoBots_Block_Verify extends Mage_Core_Block_Template
{
	/**
	 * Retrieve the form action URL
	 *
	 * @return string
	 */
	public function getFormActionUrl()
	{
		return $this->getUrl('*/*/post');
	}
	
	/**
	 * Retrieve the URL to redirect to after a successful validation
	 *
	 * @return string
	 */
	public function getRedirectToUrl()
	{
		return preg_replace('/([^a-zA-Z0-9=]{1,})/', '', $this->getRequest()->getParam(
			$this->getRedirectToName()
		));
	}
	
	/**
	 * Get the variable name used to store the redirect URL
	 *
	 * @return string
	 */
	public function getRedirectToName()
	{
		return Mage_Core_Controller_Varien_Action::PARAM_NAME_URL_ENCODED;
	}
}
