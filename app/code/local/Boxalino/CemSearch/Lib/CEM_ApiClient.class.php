<?php

/** @addtogroup cem
 *
 * @{
 */

/**
 * @internal
 *
 * Boxalino CEM client library in PHP.
 *
 * (C) 2009-2012 - Boxalino AG
 */


/**
 * Boxalino CEM API Client
 *
 * @author nitro@boxalino.com
 */
class CEM_ApiClient extends CEM_HttpClient {
	/**
	 * @internal Proxy headers to drop
	 */
	protected static $proxyHideHeaders = array(
		'authenticate',
		'connection',
		'content-encoding',
		'cookie',
		'keep-alive',
		'host',
		'proxy-authenticate',
		'proxy-authorization',
		'proxy-connection',
		'set-cookie',
		'set-cookie2',
		'te',
		'trailer',
		'transfer-encoding',
		'upgrade',
		'www-authenticate',
		'x-powered-by'
	);


	/**
	 * @internal API base URL
	 */
	protected $url;

	/**
	 * @internal API charset (display)
	 */
	protected $displayCharset = 'UTF-8';


	/**
	 * Constructor
	 *
	 * @param $url tracker url
	 */
	public function __construct($url) {
		parent::__construct(FALSE, FALSE, 1000, 15000, 5);
		$this->url = $url;
	}


	/**
	 * Get display charset
	 *
	 * @return display charset
	 */
	public function getDisplayCharset() {
		return $this->displayCharset;
	}

	/**
	 * Set display charset
	 *
	 * @param $charset charset
	 */
	public function setDisplayCharset($charset) {
		$this->displayCharset = $charset;
	}


	/**
	 * Get response code
	 *
	 * @return response code
	 */
	public function getCode() {
		$code = parent::getCode();
		return ($code > 0 ? $code : 500);
	}

	/**
	 * Get response status
	 *
	 * @return response status
	 */
	public function getStatus() {
		$status = parent::getStatus();
		return ($status ? $status : 'HTTP/1.0 500 Internal Server Error');
	}


	/**
	 * Proxy current request to remote server
	 *
	 * @param $uri destination uri
	 * @return TRUE on success
	 */
	public function proxy($uri) {
		// check that headers are not sent
		if (function_exists('header_remove')) {
			header_remove();
		}
		if (headers_sent()) {
			return FALSE;
		}

		// prepare http headers
		list($requestHeaders, $requestContentType, $requestContentLength) = $this->extractHeaders();

		$urlParts = parse_url($this->url.$uri);
		$requestHeaders[] = array('Host', $urlParts['host']);
		$requestHeaders[] = array('Via', '1.1 (Proxy)');

		// prepare http cookies
		$this->extractCookies();

		// forward http request
		switch (isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET') {
		case 'GET':
			$this->get($this->url.$uri, $_GET, FALSE, $requestHeaders);
			break;

		case 'POST':
			if (!$requestContentType) {
				throw new Exception("Invalid content-type");
			}
			if (sizeof($_POST) > 0 || sizeof($_FILES) > 0) {
				$fields = array();
				foreach (CEM_HttpClient::expandKVList($_POST) as $k => $v) {
					if (is_array($v) || strpos($v, '@') !== 0) {
						$fields[$k] = $v;
					}
				}
				foreach ($_FILES as $k => $v) {
					$fields[$k] = '@'.$v['tmp_name'].';type='.$v['type'].';filename='.$v['name'];
				}
				$this->post(CEM_HttpClient::buildUrl($this->url.$uri, $_GET), 'multipart/form-data', $fields, FALSE, $requestHeaders);
				break;
			}
			$this->post(CEM_HttpClient::buildUrl($this->url.$uri, $_GET), $requestContentType, file_get_contents("php://input"), FALSE, $requestHeaders);
			break;

		case 'PUT':
			if (!$requestContentType) {
				throw new Exception("Invalid content-type");
			}
			if ($requestContentLength) {
				$requestHeaders[] = array('Content-Length', $requestContentLength);
			}
			$this->put(CEM_HttpClient::buildUrl($this->url.$uri, $_GET), $requestContentType, file_get_contents("php://input"), FALSE, $requestHeaders);
			break;

		default:
			return FALSE;
		}

		// forward response headers with rewrite
		$this->forwardHeaders();

		// forward cookies
		$this->forwardCookies();

		// forward response body
		echo($this->getBody());
		return TRUE;
	}


	/**
	 * Load page content from remote server
	 *
	 * @param $uri page uri
	 * @param $args page parameters
	 * @return CEM_ApiPage
	 */
	public function loadPage($uri, $args = array()) {
		// prepare http cookies
		$this->extractCookies();

		// execute request
		$parameters = $this->extractParameters();
		$parameters['uri'] = $uri;
		foreach ($args as $k => $v) {
			if (!isset($parameters[$k])) {
				$parameters[$k] = $v;
			}
		}
		$this->postFields($this->url.'/api/xml/page', $parameters);

		// forward cookies
		$this->forwardCookies();

		// parse response
		$page = new CEM_ApiPage($this->getBody());
		$page->setTransport($this->getCode(), $this->getError(), $this->getTime(), $this->getBody());

		// convert response charset
		if ($this->displayCharset != 'UTF-8') {
			mb_convert_variables($this->displayCharset, 'UTF-8', $page);
		}
		return $page;
	}


	/**
	 * This method is called to track an event "categoryView" with Boxalino Analytics.
	 *
	 * @param $categoryId category identifier
	 * @param $categoryName optional category name
	 * @return TRUE on success
	 */
	public function trackCategoryView($categoryId, $categoryName = '') {
		$description = array();
		$description['id'] = $categoryId;
		if (strlen($categoryName) > 0) {
			$description['name'] = $categoryName;
		}
		if (isset($_REQUEST['widget']) && strlen($_REQUEST['widget']) > 0) {
			$description['widget'] = $_REQUEST['widget'];
		}
		return $this->trackEvent('categoryView', $description);
	}

	/**
	 * This method is called to track an event "productView" with Boxalino Analytics.
	 *
	 * @param $itemId item identifier
	 * @param $itemName optional item name
	 * @return TRUE on success
	 */
	public function trackProductView($itemId, $itemName = '') {
		$description = array();
		$description['id'] = $itemId;
		if (strlen($itemName) > 0) {
			$description['name'] = $itemName;
		}
		if (isset($_REQUEST['widget']) && strlen($_REQUEST['widget']) > 0) {
			$description['widget'] = $_REQUEST['widget'];
		}
		return $this->trackEvent('productView', $description);
	}

	/**
	 * This method is called to track an event "addToBasket" with Boxalino Analytics.
	 *
	 * @param $item transaction item (see CEM_ApiTransactionItem class)
	 * @return TRUE on success
	 */
	public function trackAddToBasket($item) {
		$description = array();
		$description['item'] = @json_encode($item);
		if (isset($_REQUEST['widget']) && strlen($_REQUEST['widget']) > 0) {
			$description['widget'] = $_REQUEST['widget'];
		}
		return $this->trackEvent('addToBasket', $description);
	}

	/**
	 * This method is called to track an event "purchaseDone" with Boxalino Analytics.
	 *
	 * @param $status transaction status (TRUE = success/confirmed, FALSE = failed)
	 * @param $amount total transaction amount
	 * @param $items products in the transaction (array of CEM_ApiTransactionItem)
	 * @return TRUE on success
	 */
	public function trackPurchase($status, $amount, $items = array()) {
		$description = array();
		$description['status'] = $status ? '1' : '0';
		$description['amount'] = floatval($amount);
		$description['items'] = @json_encode($items);
		return $this->trackEvent('purchaseDone', $description);
	}


	/**
	 * Track an event with Boxalino Analytics.
	 *
	 * @param $name event name
	 * @param $description event parameters or description (optional)
	 * @return TRUE on success
	 */
	public function trackEvent($name, $description = array()) {
		// wrap description
		if (is_array($description)) {
			$parameters = array();
			foreach ($description as $k => $v) {
				if (strlen($v) > 0) {
					$parameters[] = urlencode($k).':'.urlencode($v);
				}
			}
			$description = implode(' ', $parameters);
		}

		// prepare http cookies
		$this->extractCookies();

		// send request
		$parameters = $this->extractParameters();
		$parameters['eventName'] = $name;
		$parameters['eventDescription'] = $description;
		if ($this->postFields($this->url.'/analytics', $parameters) == 200) {
			$this->forwardCookies();
			return TRUE;
		}
		return FALSE;
	}


	/**
	 * Called to extract request headers
	 *
	 * @return request headers, content-type, content-length
	 */
	protected function extractHeaders() {
		$requestContentType = FALSE;
		$requestContentLength = FALSE;
		$requestHeaders = array();
		if (function_exists('apache_request_headers')) {
			foreach (apache_request_headers() as $name => $value) {
				$key = strtolower($name);
				switch ($key) {
				case 'content-type':
					$requestContentType = $value;
					break;

				case 'content-length':
					$requestContentLength = $value;
					break;

				default:
					if (!in_array($key, CEM_ApiClient::$proxyHideHeaders)) {
						$requestHeaders[] = array($name, $value);
					}
					break;
				}
			}
		}
		return array($requestHeaders, $requestContentType, $requestContentLength);
	}

	/**
	 * Called to extract request cookies
	 *
	 */
	protected function extractCookies() {
		// forward cookie(s)
		foreach ($_COOKIE as $key => $value) {
			if (strpos($key, 'cem') === 0) {
				$this->setCookie($key, $value);
			}
		}
	}

	/**
	 * Called to extract request parameters
	 *
	 * @return client environment parameters
	 */
	protected function extractParameters() {
		// extract environment parameters
		$parameters = array(
			'connection' => 'http',
			'clientAddress' => '',
			'clientAgent' => '',
			'clientReferer' => '',
			'serverAddress' => '',
			'serverHost' => '',
			'serverUri' => ''
		);
		if (isset($_SERVER['HTTPS'])) {
			$parameters['connection'] = ($_SERVER['HTTPS'] == 'on' ? 'https' : 'http');
		}
		if (isset($_REQUEST['clientAddress'])) {
			$parameters['clientAddress'] = $_REQUEST['clientAddress'];
		} else if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			$parameters['clientAddress'] = $_SERVER['HTTP_X_FORWARDED_FOR'];
		} else if (isset($_SERVER['REMOTE_ADDR'])) {
			$parameters['clientAddress'] = $_SERVER['REMOTE_ADDR'];
		}
		if (isset($_REQUEST['clientAgent'])) {
			$parameters['clientAgent'] = $_REQUEST['clientAgent'];
		} else if (isset($_SERVER['HTTP_USER_AGENT'])) {
			$parameters['clientAgent'] = $_SERVER['HTTP_USER_AGENT'];
		}
		if (isset($_REQUEST['clientReferer'])) {
			$parameters['clientReferer'] = $_REQUEST['clientReferer'];
		} else if (isset($_SERVER['HTTP_REFERER'])) {
			$parameters['clientReferer'] = $_SERVER['HTTP_REFERER'];
		}
		if (isset($_REQUEST['serverAddress'])) {
			$parameters['serverAddress'] = $_REQUEST['serverAddress'];
		} else if (isset($_SERVER['SERVER_ADDR'])) {
			$parameters['serverAddress'] = $_SERVER['SERVER_ADDR'];
		}
		if (isset($_REQUEST['serverHost'])) {
			$parameters['serverHost'] = $_REQUEST['serverHost'];
		} else if (isset($_SERVER['HTTP_HOST'])) {
			$parameters['serverHost'] = $_SERVER['HTTP_HOST'];
		}
		if (isset($_REQUEST['serverUri'])) {
			$parameters['serverUri'] = $_REQUEST['serverUri'];
		} else if (isset($_SERVER['REQUEST_URI'])) {
			$parameters['serverUri'] = $_SERVER['REQUEST_URI'];
		}

		// forward parameters
		foreach ($_REQUEST as $k => $v) {
			if (!isset($parameters[$k])) {
				$parameters[$k] = $v;
			}
		}
		return $parameters;
	}


	/**
	 * Called to forward headers
	 *
	 */
	protected function forwardHeaders() {
		header($this->getStatus(), TRUE, $this->getCode());
		foreach ($this->getHeaders() as $entry) {
			switch ($entry['key']) {
			case 'content-length':
				header($entry['name'].': '.$this->getSize(), FALSE);
				break;

			default:
				if (!in_array($entry['key'], CEM_ApiClient::$proxyHideHeaders)) {
					header($entry['name'].': '.$entry['value'], FALSE);
				}
				break;
			}
		}
	}

	/**
	 * Called to forward cookies
	 *
	 */
	protected function forwardCookies() {
		foreach ($this->getCookies() as $name => $cookie) {
			if (strpos($name, 'cem') === 0) {
				$header = urlencode($cookie['name']).'='.urlencode($cookie['value']);
				if (isset($cookie['expires'])) {
					$header .= '; expires='.$cookie['expires'];
				}
				$header .= '; path=/';
				header('Set-Cookie: '.$header, FALSE);
			}
		}
	}
}

/**
 * @}
 */

?>