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
	
			$link = str_replace(array("\n", "\t"), '', sprintf('<div style="display:none;">
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

	/*
	 *
	 * @param Varien_Event_Observer $observer
	 *
	 * @return void
	 */
	public function checkForSecretFormFieldObserver(Varien_Event_Observer $observer)
	{
		return $this;
		// If it's not frontend, we don't want anything to do with it
		if ('frontend' !== Mage::getDesign()->getArea()) {
			return $this;
		}

		// Form protection is disabled so return
		if (!Mage::getStoreConfigFlag('nobots/form_protection/enabled')) {
			return $this;	
		}
		
		if (!Mage::getStoreConfigFlag('nobots/form_protection/form_secret_field')) {
			return $this;	
		}
		
		// Ajax request so bail
		if ($this->isAjax()) {
			return $this;
		}
		
		// Get some useful variables
		$request = Mage::app()->getRequest();
		$method  = strtoupper($request->getMethod());
		$route	 = strtolower($request->getModuleName() . '/' . $request->getControllerName() . '/' . $request->getActionName());
		
		// We're on the checkout so bail!
		if (strpos($route, 'checkout') !== false) {
			return $this;
		}
		
		// Check for get and post and check validity
		$isInvalidGetRequest  = 'GET'  === $method && 1 !== (int)$request->getParam($this->getSecretFormField()) && count($_GET) > 0;
		$isInvalidPostRequest = 'POST' === $method && 1 !==  (int)$request->getPost($this->getSecretFormField());

		// Check whether the request is good
		if ($isInvalidGetRequest || $isInvalidPostRequest) {
			// Log the IP address
			Mage::log(Mage::helper('core/http')->getRemoteAddr(true), null, 'nobots-form-secret-field-protection.log', true);

			// Add an error message
			Mage::getSingleton('core/session')->addError(
				Mage::helper('nobots')->__('The form submission was invalid. If this error persists, please let us know.')
			);

			// Redirect to the homepage
			header('Location: ' . Mage::getBaseUrl());
			exit;
		}
	}

	/*
	 *
	 * @param  string $html
	 * @return void
	 */
	protected function _applyFormSecretFieldProtection(&$html)
	{
		$script = '<script type="text/javascript">(function(){var fs=document.getElementsByTagName(\'form\');for(var i=0;i<fs.length;i++){
var e=document.createElement(\'input\');e.name=\'' . $this->getSecretFormField() . '\';e.value=1;e.type=\'hidden\';fs[i].appendChild(e);	
}})();</script>';

		$html = str_replace('</body>', $script . "</body>", $html);
	}
	
	/*
	 *
	 *
	 * @return string
	 */
	public function getSecretFormField()
	{
		return substr(md5(Mage::getBaseUrl() . '-FishPig-NoBots'), 0, 16);
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
		
		$formIds = array_unique($formIds);
		
		foreach($formIds as $key => $formId) {
			$formIds[$key] = trim($formId);
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
		
		// Block URLs in specific fields
		if ($blockedUrlFields = trim(Mage::getStoreConfig('nobots/form_protection/blocked_url_fields'))) {
			$blockedUrlFields = explode("\n", $blockedUrlFields);
			
			foreach($blockedUrlFields as $key => $value) {
				$blockedUrlFields[$key] = trim($value);
				
				if (empty($blockedUrlFields[$key])) {
					unset($blockedUrlFields[$key]);
				}
			}
			
			if (count($blockedUrlFields) > 0) {
				foreach($sources as $key => $source) {
					if ($this->_arrayContainsUrl($source, $blockedUrlFields)) {
						// URL found in field so redirect
						header('Location: ' . Mage::getUrl());
						exit;
					}
				}
			}
		}

		return $this;
	}
	
	protected function _arrayContainsUrl($source, $fields)
	{
		foreach($source as $key => $value) {
			if (is_array($value)) {
				if ($this->_arrayContainsUrl($value, $fields)) {
					return true;
				}
			}
			else if (in_array($key, $fields)) {
				$value = strtolower($value);
				
				if (strpos($value, 'http:') !== false || strpos($value, 'https:') !== false || strpos($value, 'www.') !== false) {
					return true;
				}
			}
		}
		
		return false;
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
	
	/*
	 * Determine whether the current request is an ajax request
	 *
	 * @return bool
	 */
	public function isAjax()
	{
		return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
	}
}
