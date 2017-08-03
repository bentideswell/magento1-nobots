<?php
/**
 * @category    Fishpig
 * @package    Fishpig_NoBots
 * @license      http://fishpig.co.uk/license.txt
 * @author       Ben Tideswell <ben@fishpig.co.uk>
 */

class Fishpig_NoBots_Model_Observer extends Varien_Object
{
	/**
	 * Load bot and check whether it's banned
	 * If so, respond accordingly!
	 *
	 * @param Varien_Event_Observer $observer
	 * @return $this
	 */
	public function rejectNaughtyBotsObserver(Varien_Event_Observer $observer)
	{
		if (Mage::app()->getRequest()->getRouteName() !== 'nobots') {
			if (($bot = Mage::helper('nobots')->getBot(false)) !== false) {
				if ($bot->isBanned()) {
					Mage::dispatchEvent('nobots_verification_redirect', array('bot' => $bot));

					Mage::app()->getResponse()->setRedirect(
						Mage::helper('nobots')->getVerificationUrl(true)
					)->sendResponse();
				}
			}
		}
		
		return $this;
	}
	
	/**
	 * Inject the link into the sourcecode before returning to the user
	 *
	 * @param Varien_Event_Observer $observer
	 * @return void
	 */
	public function injectNobotsLinksObserver(Varien_Event_Observer $observer)
	{
		$front = $observer->getEvent()->getFront();

		$html = $front->getResponse()->getBody();
		
		if (($position = strpos($html, '</body>')) === false) {
			return $this;
		}

		if (Mage::getStoreConfigFlag('nobots/settings/enabled')) {
			$modules = Mage::getStoreConfig('nobots/settings/modules');

			if (!Mage::getStoreConfigFlag('nobots/settings/enable_global') || !in_array($modules, array('', '*'))) {
				if (!in_array(Mage::app()->getRequest()->getModuleName(), (array)explode(',', trim($modules, ',')))) {
					return $this; // Module not allowed bot protection via config
				}
			}
	
			$name = Mage::getStoreConfig('general/store_information/name');
			$url = Mage::helper('nobots')->getHoneyPotUrl();
	
			$link = str_replace(array("\n", "\t"), '', sprintf('<div class="no-display">
				<form method="post" action="%s" id="%s">
					<fieldset>
						<legend>Post your comment</legend>
						<label><input name="name"/> Name</label>
						<label><input name="email"/> Email</label>
						<label><textarea name="comment" cols="20" rows="8"></textarea> Comment</label>
						<p><button type="submit"><span><span>Submit</span></span></button></p>
						<p>%s</p>
					</fieldset>
				</form>
			</div>', $url, $this->getNoBotsFormId(), $name));
	
			$html = substr($html, 0, $position) . $link . substr($html, $position);
		}
	
		// Apply form spam protection		
		$this->_applyFormSpamProtection($html);
		
		$front->getResponse()->setBody($html);
	}
	
	/**
	 * Apply form spam protection trick
	 *
	 * @param string &$html
	 * @return $this
	 */
	protected function _applyFormSpamProtection(&$html)
	{
		if (!Mage::getStoreConfigFlag('nobots/form_protection/enabled')) {
			return $this;
		}
		
		$modules = Mage::getStoreConfig('nobots/form_protection/modules');

		if (!Mage::getStoreConfigFlag('nobots/form_protection/enable_global') || !in_array($modules, array('', '*'))) {
			if (!in_array(Mage::app()->getRequest()->getModuleName(), (array)explode(',', trim($modules, ',')))) {
				return $this; // Module not allowed bot protection via config
			}
		}

		$formIds = explode("\n", trim(Mage::getStoreConfig('nobots/form_protection/form_ids')));
		
		if (count($formIds) === 0) {
			return $this;
		}
		
		if (preg_match_all('/(<form.*>)/Uis', $html, $matches)) {
			$scripts = array();

			foreach($matches[1] as $formHtml) {
				$origFormHtml = $formHtml;

				if (!preg_match('/id="(.*)"/iU', $formHtml, $ids)) {
					continue;
				}
				
				$formId = $ids[1];
				
				if ($formId === $this->getNoBotsFormId()) {
					continue;
				}
				
				if (array_search($formId, $formIds) === false) {
					continue;
				}

				if (!preg_match('/action="(.*)"/iU', $formHtml, $actions)) {
					continue;
				}
				
				$action = $actions[1];

				$formHtml = str_replace($action, '#', $formHtml);
				$scripts[] = sprintf("document.getElementById('%s').setAttribute('action','%s');", $formId, $action);
				$html = str_replace($origFormHtml, $formHtml, $html);
			}

			if (count($scripts) > 0) {
				$script = sprintf('<script type="text/javascript">%s</script>', implode("", $scripts));				
				$html = str_replace('</body>', $script . "</body>", $html);
			}
		}
		
		return $this;
	}
	
	/*
	 *
	 *
	 */
	public function blockBadEmailDomainObserver()
	{
		$request = Mage::app()->getRequest();

		// Only apply to POST requests
		if ('POST' !== strtoupper($request->getMethod())) {
			return $this;
		}
		
		// Don't apply to checkout
		if ($request->getModuleName() === 'checkout' && $request->getControllerName() === 'onepage') {
			return $this;
		}
		
		// Get and parse into an array the blocked email domains
		if ('' === ($blockedEmailDomains = trim(Mage::getStoreConfig('nobots/form_protection/blocked_email_domains')))) {
			$blockedEmailDomains = '@qq.com';
		}
		else {
			$blockedEmailDomains .= "\n@qq.com";
		}
		
		$blockedEmailDomains = array_unique(explode("\n", $blockedEmailDomains));

		foreach($blockedEmailDomains as $key => $blockedEmailDomain) {
			$blockedEmailDomain = trim($blockedEmailDomain);
			
			if (!$blockedEmailDomain) {
				unset($blockedEmailDomains[$key]);
				continue;
			}
			
			$blockedEmailDomains[$key] = '@' . ltrim($blockedEmailDomain, '@');
		}

		$sources = array(
			'POST' => $request->getPost(),
			'GET'  => $request->getParams(),
		);
		
		foreach($sources as $key => $source) {
			$encodedSource = json_encode($source);
			
			if ($encodedSource !== str_replace($blockedEmailDomains, '', $encodedSource)) {
				if (true === $this->_checkForBlockedEmailDomains($source, $blockedEmailDomains)) {
					// Banned domain found so redirect
					header('Location: ' . Mage::getUrl());
					exit;
				}
			}
		}
		
		return $this;
	}
	
	/*
	 *
	 *
	 */
	protected function _checkForBlockedEmailDomains($data, $blockedEmailDomains)
	{
		if (is_array($data)) {
			foreach($data as $key => $value) {
				if (true === $this->_checkForBlockedEmailDomains($value, $blockedEmailDomains)) {
					return true;
				}
			}
		}	
		else if ($data) {
			foreach($blockedEmailDomains as $blockedEmailDomain) {
				if ((int)strpos($data, $blockedEmailDomain) === (int)(strlen($data) - strlen($blockedEmailDomain))) {
					return true;
				}
			}
		}

		return false;
	}
	
	/**
	 * Get the ID of the honeypot form
	 *
	 * @return string
	 */
	public function getNoBotsFormId()
	{
		if (!$this->hasNoBotsFormId()) {
			$this->setNoBotsFormId(chr(rand(ord('a'), ord('z'))) . substr(md5(microtime()), 0, 8));
		}
		
		return $this->_getData('no_bots_form_id');
	}
}
