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
 * Boxalino CEM API Client (Magento)
 *
 * @author nitro@boxalino.com
 */
class CEM_MagentoApiClient extends CEM_ApiClient {
	/** Active store */
	protected $store;


	/**
	 * Constructor
	 *
	 * @param $store current store
	 */
	public function __construct($store) {
		parent::__construct(
			sprintf(
				'%s/%s/%s%s',
				$store->getConfig('boxalinocem/service/router_url'),
				$store->getConfig('boxalinocem/service/account'),
				$store->getConfig('boxalinocem/frontend/debug') == 1 ? '_/' : '',
				$store->getConfig('boxalinocem/service/language')
			)
		);
		$this->store = $store;
		$this->setRequestCluster($this->store->getConfig('boxalinocem/service/router_cluster'));
		$this->setConnectTimeout(intval($this->store->getConfig('boxalinocem/service/connect_timeout')));
		$this->setConnectMaxTries(intval($this->store->getConfig('boxalinocem/service/connect_tries')));
		$this->setReadTimeout(intval($this->store->getConfig('boxalinocem/service/read_timeout')));
		$this->setDisplayCharset($this->store->getConfig('boxalinocem/frontend/charset'));
	}


	/**
	 * Get service remote url
	 *
	 * @return service remote url or NULL if none
	 */
	public function getRemoteUrl() {
		if (strlen($this->store->getConfig('boxalinocem/service/resource_url')) > 0) {
			return sprintf(
				'%s/%s/%s%s',
				$this->store->getConfig('boxalinocem/service/resource_url'),
				$this->store->getConfig('boxalinocem/service/account'),
				$this->store->getConfig('boxalinocem/frontend/debug') == 1 ? '_/' : '',
				$this->store->getConfig('boxalinocem/service/language')
			);
		}
		return NULL;
	}


	/**
	 * Called to extract request parameters
	 *
	 * @return client environment parameters
	 */
	protected function extractParameters() {
		// extract environment parameters
		$parameters = parent::extractParameters();
		$parameters['rootUri'] = $this->store->getConfig('boxalinocem/service/resource_url');
		$parameters['shopId'] = $this->store->getId();
		$parameters['shopName'] = $this->store->getName();
		return $parameters;
	}


	/**
	 * Called to forward headers
	 *
	 */
	protected function forwardHeaders() {
		Mage::app()->getResponse()->setHttpResponseCode($this->getCode());
		foreach ($this->getHeaders() as $entry) {
			switch ($entry['key']) {
			case 'content-length':
				Mage::app()->getResponse()->setHeader($entry['name'], $this->getSize(), FALSE);
				break;

			default:
				if (!in_array($entry['key'], CEM_ApiClient::$proxyHideHeaders)) {
					Mage::app()->getResponse()->setHeader($entry['name'], $entry['value'], FALSE);
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
				Mage::getSingleton('core/cookie')->set(
					$cookie['name'],
					$cookie['value'],
					$cookie['expiresTime'] - time(),
					NULL,
					NULL,
					NULL,
					FALSE
				);
			}
		}
	}
}

/**
 * @}
 */

?>