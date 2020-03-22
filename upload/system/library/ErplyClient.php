<?php

/**
 * API helper provided by Erply.
 */
class ErplyClient
{
	const VERIFY_USER_FAILURE = 2001;
	const CURL_ERROR = 2002;
	const PHP_SESSION_NOT_STARTED = 2003;
	const MISSING_PARAMETERS = 2004;

	private $url;
	private $clientCode;
	private $username;
	private $password;
	private $sslCACertPath;

	public function __construct($url = null, $clientCode = null, $username = null, $password = null, $sslCACertPath = null)
	{
		$this->url = $url;
		$this->clientCode = $clientCode;
		$this->username = $username;
		$this->password = $password;
		$this->sslCACertPath = $sslCACertPath;
	}

	public function get_categories($excludeDisabled = 0)
	{
		// TODO: add support for multiple languages
		$options = array(
			'displayedInWebshop' => $excludeDisabled
			//'getAllLanguages' => ($all_languages ? 1 : 0)
		);

		$categories_response = json_decode($this->sendRequest('getProductGroups', $options), true);

		if ($categories_response['status']['responseStatus'] == 'error') {
			throw new Exception("Error in Erply response");
		}

		return $categories_response['records'];
	}

	public function get_category_ids()
	{
		$categories = $this->get_categories(1);
		return $this->collect_category_ids_recursive($categories);
	}

	private function collect_category_ids_recursive($categories, &$ids = array())
	{
		foreach ($categories as $category) {
			$ids[] = $category['productGroupID'];

			if (!empty($category['subGroups'])) {
				$this->collect_category_ids_recursive($category['subGroups'], $ids);
			}
		}

		return $ids;
	}

	public function get_products_simple($offset = 0, $limit = 1000, $product_ids = null)
	{
		return $this->get_products($offset, $limit, 0, 0, $product_ids, null);
	}

	public function get_products($offset = 0, $limit = 1000, $with_stock = 0, $with_prices = 0, $product_ids = null, $group_id = null)
	{
		$options = array(
			'recordsOnPage' => $limit,
			'recordOffset' => $offset,
			//'displayedInWebshop' => 1,
			'getStockInfo' => $with_stock,
			'active' => 1,
			'getPriceListPrices' => $with_prices,
			'type' => 'PRODUCT,BUNDLE,ASSEMBLY' // ensures no matrix product parents in response
			//'getAllLanguages' = 1
		);

		if ($group_id !== null) {
			$options['groupID'] = $group_id;
		}

		if ($product_ids !== null) {
			$options['productIDs'] = implode(",", $product_ids);
		}

		return json_decode($this->sendRequest('getProducts', $options), true);
	}

	public function sendRequest($request, $parameters = array())
	{
		if (session_status() == PHP_SESSION_NONE) {
			session_start();
		}

		//validate that all required parameters are set
		if (!$this->url or !$this->clientCode or !$this->username or !$this->password) {
			throw new Exception('Missing parameters', self::MISSING_PARAMETERS);
		}

		//add extra params
		$parameters['request'] = $request;
		$parameters['clientCode'] = $this->clientCode;
		$parameters['version'] = '1.0';
		if ($request != "verifyUser") $parameters['sessionKey'] = $this->getSessionKey();

		//create request
		$handle = curl_init($this->url);

		//set the payload
		curl_setopt($handle, CURLOPT_POST, true);
		curl_setopt($handle, CURLOPT_POSTFIELDS, $parameters);

		//return body only
		curl_setopt($handle, CURLOPT_HEADER, 0);
		curl_setopt($handle, CURLOPT_RETURNTRANSFER, 1);

		//create errors on timeout and on response code >= 300
		curl_setopt($handle, CURLOPT_TIMEOUT, 45);
		curl_setopt($handle, CURLOPT_FAILONERROR, true);
		curl_setopt($handle, CURLOPT_FOLLOWLOCATION, false);

		//set up host and cert verification
		curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, 2);
		curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);
		if ($this->sslCACertPath) {
			curl_setopt($handle, CURLOPT_CAINFO, $this->sslCACertPath);
			curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, true);
		}

		//run
		$response = curl_exec($handle);
		$error = curl_error($handle);
		$errorNumber = curl_errno($handle);
		curl_close($handle);
		if ($error) throw new Exception('CURL error: ' . $response . ':' . $error . ': ' . $errorNumber, self::CURL_ERROR);
		return $response;
	}

	protected function getSessionKey()
	{
		//test for session
		if (!isset($_SESSION)) throw new Exception('PHP session not started', self::PHP_SESSION_NOT_STARTED);

		//if no session key or key expired, then obtain it

		if (
			!isset($_SESSION['EAPISessionKey'][$this->clientCode][$this->username]) ||
			!isset($_SESSION['EAPISessionKeyExpires'][$this->clientCode][$this->username]) ||
			$_SESSION['EAPISessionKeyExpires'][$this->clientCode][$this->username] < time()
		) {
			//make request
			$result = $this->sendRequest("verifyUser", array("username" => $this->username, "password" => $this->password));
			$response = json_decode($result, true);

			//check failure
			if (!isset($response['records'][0]['sessionKey'])) {
				unset($_SESSION['EAPISessionKey'][$this->clientCode][$this->username]);
				unset($_SESSION['EAPISessionKeyExpires'][$this->clientCode][$this->username]);

				$e = new Exception('Verify user failure', self::VERIFY_USER_FAILURE);
				$e->response = $response;
				throw $e;
			}

			//cache the key in PHP session
			$_SESSION['EAPISessionKey'][$this->clientCode][$this->username] = $response['records'][0]['sessionKey'];
			$_SESSION['EAPISessionKeyExpires'][$this->clientCode][$this->username] = time() + $response['records'][0]['sessionLength'] - 30;
		}

		//return cached key
		return $_SESSION['EAPISessionKey'][$this->clientCode][$this->username];
	}
}
