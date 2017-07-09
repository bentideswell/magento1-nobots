<?php
/**
 * @category    Fishpig
 * @package    Fishpig_NoBots
 * @license      http://fishpig.co.uk/license.txt
 * @author       Ben Tideswell <ben@fishpig.co.uk>
 */

class Fishpig_NoBots_Helper_Recaptcha extends Mage_Core_Helper_Abstract
{
	/**
	 * ReCaptcha API URL endpoint
	 *
	 * @const string
	 **/
	const RECAPTCHA_VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';
	
	/**
	 * Retrieve the public key
	 *
	 * @return string
	 */
	public function getSiteKey()
	{
		return trim(Mage::getStoreConfig('nobots/recaptcha/public_key'));
	}
	
	/**
	 * Retrieve the private key
	 *
	 * @return string
	 */
	public function getSecretKey()
	{
		return trim(Mage::getStoreConfig('nobots/recaptcha/private_key'));
	}
	
	/**
	 * Get the HTML for displaying the site key
	 *
	 * @return string
	 */
	public function getSiteKeyHtml()
	{
		return sprintf('<div class="g-recaptcha" data-sitekey="%s"></div>', $this->getSiteKey());
	}
	
	/**
	 * Get the JS include html
	 *
	 * @return string
	 */
	public function getJsInclude()
	{
		return '<script type="text/javascript" src="https://www.google.com/recaptcha/api.js"></script>';
	}

	/**
	 * Validate the posted captcha details
	 *
	 * @return $this
	 */
	public function validateCaptcha()
	{
		if (!Mage::app()->getRequest()->getMethod() === 'POST') {
			throw new Exception('Invalid HTTP method for ReCaptcha.');
		}

		$apiVerificationUrl = self::RECAPTCHA_VERIFY_URL . '?' . http_build_query(array(
			'secret' => $this->getSecretKey(),
			'response' => Mage::app()->getRequest()->getPost('g-recaptcha-response'),
			'remoteip' => Mage::helper('core/http')->getRemoteAddr(),
		));

		$apiResponse = $this->_makeHttpGetRequest($apiVerificationUrl);
		
		if (!$apiResponse) {
			throw new Exception('Invalid API response. Assumed bot.');
		}
		
		$jsonResponse = json_decode($apiResponse, true);
		
		if (!isset($jsonResponse['success']) || (bool)$jsonResponse['success'] === false) {
			throw new Exception('Invalid ReCaptcha details. Please try again.');
		}

		return $this;
	}
	
	/**
	 * Make a HTTP GET request for $url
	 *
	 * @return string|bool
	 */
	protected function _makeHttpGetRequest($url)
	{
		$ch = curl_init();

		curl_setopt_array($ch, array(
			CURLOPT_URL => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT => 10,
			CURLOPT_USERAGENT =>"FishPig - NoBots",
		));
		
		$data = curl_exec($ch);
		
		curl_close($ch);

		return $data ? $data : false;
	}
}
