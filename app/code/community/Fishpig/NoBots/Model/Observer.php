<?php
/**
 * @category   Fishpig
 * @package    Fishpig_NoBots
 * @license    http://fishpig.co.uk/license.txt
 * @author     Ben Tideswell <ben@fishpig.co.uk>
 */

class Fishpig_NoBots_Model_Observer extends Varien_Object
{
	/**
	 * Inject the link into the sourcecode before returning to the user
	 *
	 * @param Varien_Event_Observer $observer
	 * @return void
	 */
	public function injectBotProtectionObserver(Varien_Event_Observer $observer)
	{
		$front = $observer->getEvent()->getFront();
		$html  = $front->getResponse()->getBody();
		
		if (($position = strpos($html, '</body>')) === false) {
			return $this;
		}

		if (Mage::getStoreConfigFlag('nobots/settings/enabled')) {
			$modules = Mage::getStoreConfig('nobots/settings/modules');
	
			if (!in_array(Mage::app()->getRequest()->getModuleName(), (array)explode(',', trim($modules, ',')))) {
				return $this; // Module not allowed bot protection via config
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
		
		$front->getResponse()->setBody($html);
	}

	/**
	 * Inject the form protection code
	 *
	 * @param Varien_Event_Observer $observer
	 * @return void
	 */
	public function injectFormProtectionObserver(Varien_Event_Observer $observer)
	{
		$front = $observer->getEvent()->getFront();
		$html  = $front->getResponse()->getBody();
		
		if (($position = strpos($html, '</body>')) === false) {
			return $this;
		}

		if (!Mage::getStoreConfigFlag('nobots/form_protection/enabled')) {
			return $this;
		}
		
		$modules = Mage::getStoreConfig('nobots/form_protection/modules');

		if (!in_array(Mage::app()->getRequest()->getModuleName(), (array)explode(',', trim($modules, ',')))) {
			return $this; // Module not allowed bot protection via config
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
		
		$front->getResponse()->setBody($html);
	}

	/*
	 *
	 *
	 */
	public function blockBadEmailDomainObserver(Varien_Event_Observer $observer)
	{
		$request = Mage::app()->getRequest();

		// Only apply to POST requests
		if ('POST' !== strtoupper($request->getMethod())) {
			return $this;
		}
		
		$modules = Mage::getStoreConfig('nobots/form_protection/modules');

		if (!in_array(Mage::app()->getRequest()->getModuleName(), (array)explode(',', trim($modules, ',')))) {
			return $this; // Module not allowed bot protection via config
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
		
		$blockedEmailDomains[] = 'arachni@email.gr';

		$sources = array(
			'POST' => $request->getPost(),
			'GET'  => $request->getParams(),
		);
		
		foreach($sources as $key => $source) {
			$encodedSource = json_encode($source);
			
			if ($encodedSource !== str_replace($blockedEmailDomains, '', $encodedSource)) {
				if (true === $this->_checkForBlockedEmailDomains($source, $blockedEmailDomains)) {
					$this->blockUser();
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
						$this->blockUser();
					}
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
	
	/*
	 * Determine whether the current request is an ajax request
	 *
	 * @return bool
	 */
	public function isAjax()
	{
		return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
	}
	
	/*
	 *
	 *
	 */
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
	protected function blockUser()
	{
		if (($bot = Mage::helper('nobots')->getBot()) !== false) {
			$bot->hold();
		}

		// Banned domain found so redirect
		header('Location: ' . Mage::getUrl());
		exit;
	}
}
