<?php

class Boxalino_Cem_Helper_Data extends Mage_Core_Helper_Data {
	protected $client = null;

	protected $seoBase = '/';

	protected $seoSuffix = '';

	protected $pagesEnabled = null;

	protected $pagesCache = array();

	protected $lastUri = null;


	public function __construct() {
		spl_autoload_register(array('Boxalino_Cem_Helper_Data', '__loadClass'), TRUE, TRUE);
	}

	public function isEnabled() {
		return (Mage::getStoreConfig('boxalinocem/frontend/enabled') == 1);
	}

	public function isAnalyticsEnabled() {
		return (
			Mage::getStoreConfig('boxalinocem/frontend/enabled') == 1 &&
			Mage::getStoreConfig('boxalinocem/frontend/analytics') == 1
		);
	}

	public function isSalesTrackingEnabled() {
		return (
			Mage::getStoreConfig('boxalinocem/frontend/enabled') == 1 &&
			Mage::getStoreConfig('boxalinocem/frontend/track_sales') == 1
		);
	}

	public function isLogEnabled() {
		return FALSE;
	}

	public function isDebugEnabled() {
		return (Mage::getStoreConfig('boxalinocem/frontend/debug') == 1);
	}


	public function getLanguage() {
		return Mage::getStoreConfig('boxalinocem/service/language');
	}


	public function isProductFacilitated($id) {
		// TODO: track faciliated sales
//		$facilitatedProducts = $this->getSession()->getVar('bxcemFacilitatedProducts');
//		return ($facilitatedProducts && isset($facilitatedProducts[$id]) ? $facilitatedProducts[$id] : NULL);
		return FALSE;
	}

	public function trackFacilitatedProduct($id) {
		if ($this->isSalesTrackingEnabled() && isset($_REQUEST['widget'])) {
			// TODO: track faciliated sales
/*			$products = $this->getSession()->getVar('bxcemFacilitatedProducts');
			if ($products === null) {
				$products = array();
			}
			$products[$id] = $_REQUEST['widget'];
			$this->getSession()->setVar('bxcemFacilitatedProducts', $products);*/
		}
	}


	public function getBasketAmount() {
		$checkout = Mage::getSingleton('checkout/session');
		$quote = $checkout->getQuote();
		$amount = 0;
		if ($quote) {
			foreach ($quote->getAllVisibleItems() as $item) {
				$amount += $item->getQty() * $item->getPrice();
			}
		}
		return $amount;
	}

	public function getBasketItems() {
		$items = array();
		$checkout = Mage::getSingleton('checkout/session');
		$quote = $checkout->getQuote();
		if ($quote) {
			foreach ($quote->getAllVisibleItems() as $item) {
				$items[] = $item->product_id;
			}
		}
		return $items;
	}

	public function getBasketContent() {
		$checkout = Mage::getSingleton('checkout/session');
		$quote = $checkout->getQuote();
		$items = array();
		if ($quote) {
			foreach ($quote->getAllVisibleItems() as $item) {
				$items[] = array(
					'id' => $item->product_id,
					'name' => $item->getProduct()->getName(),
					'quantity' => $item->getQty(),
					'price' => $item->getPrice(),
					'widget' => $this->isProductFacilitated($item->product_id)
				);
			}
		}
		return @json_encode($items);
	}


	public function setSeoBase($value) {
		$this->seoBase = $value;
	}

	public function setSeoSuffix($value) {
		$this->seoSuffix = $value;
	}


	public function getApiClient() {
		if (!$this->client) {
			$this->client = new CEM_MagentoApiClient(Mage::app()->getStore());
		}
		return $this->client;
	}


	public function isPageEnabled($uri) {
		if ($this->pagesEnabled == null) {
			$this->pagesEnabled = array();
			if ($this->isEnabled()) {
				foreach (explode(',', Mage::getStoreConfig('boxalinocem/frontend/pageapi')) as $entry) {
					$entry = trim($entry);
					if (strlen($entry) > 0) {
						$this->pagesEnabled[$entry] = TRUE;
					}
				}
			}
		}
		return isset($this->pagesEnabled[$uri]);
	}

	public function loadPage($uri, $parameters = array()) {
		if (!$this->isEnabled()) {
			return new CEM_ApiPage();
		}
		if (strlen($uri) > 0) {
			$uri .= $this->seoSuffix;
			$this->lastUri = $uri;
		} else {
			$uri = $this->lastUri;
		}
		if (!array_key_exists($uri, $this->pagesCache)) {
			$parameters['baseUri'] = $this->_getUrl('boxalinocem/search');
			if (strlen($parameters['baseUri']) > 0 && strrpos($parameters['baseUri'], '/') == strlen($parameters['baseUri']) - 1) {
				$parameters['baseUri'] = substr($parameters['baseUri'], 0, strlen($parameters['baseUri']) - 1);
			}
			$parameters['baseUri'] .= $this->seoBase;

			try {
				$this->pagesCache[$uri] = $this->getApiClient()->loadPage($uri, $parameters);
			} catch (Exception $e) {
				if ($this->isDebugEnabled()) {
					echo($e);
					exit;
				}
			}
		}
		return $this->pagesCache[$uri];
	}

	public function isPageLoaded($uri = '') {
		if (strlen($uri) == 0) {
			$uri = $this->lastUri;
		}
		if (isset($this->pagesCache[$uri])) {
			return $this->pagesCache[$uri]->getStatus();
		}
		return false;
	}

	public function hasPageBlock($block, $uri = '') {
		if (strlen($uri) == 0) {
			$uri = $this->lastUri;
		}
		if (!array_key_exists($uri, $this->pagesCache)) {
			return ($this->isEnabled() ? ($uri.':'.$block) : '');
		}
		return $this->pagesCache[$uri]->hasBlock($block);
	}

	public function getPageBlock($block, $uri = '') {
		if (strlen($uri) == 0) {
			$uri = $this->lastUri;
		}
		if (!array_key_exists($uri, $this->pagesCache)) {
			return ($this->isEnabled() ? ($uri.':'.$block) : '');
		}
		return $this->pagesCache[$uri]->getBlock($block);
	}

	public function getPageQuery($uri = '') {
		if (strlen($uri) == 0) {
			$uri = $this->lastUri;
		}
		if (!array_key_exists($uri, $this->pagesCache)) {
			return '';
		}
		return $this->pagesCache[$uri]->getQuery();
	}


	public function suggest() {
		if ($this->isEnabled()) {
			try {
				$this->getApiClient()->proxy('/ajax/suggest');
			} catch (Exception $e) {
				if ($this->isDebugEnabled()) {
					echo($e);
					exit;
				}
			}
			return TRUE;
		}
		return FALSE;
	}

	public function getSuggestUrl() {
		$baseUrl = $this->getApiClient()->getRemoteUrl();
		if ($baseUrl) {
			return ($baseUrl.'/ajax/suggest');
		}
		return $this->_getUrl('boxalinocem/search/suggest');
	}

	public function getSuggestParameters() {
		return "{}";
	}


	public function trackAddToBasket($id, $name, $quantity = 1, $price = 0) {
		if ($this->isAnalyticsEnabled()) {
			try {
				$this->getApiClient()->trackAddToBasket(
					new CEM_ApiTransactionItem($id, $price, $quantity, $name, $this->isProductFacilitated($id))
				);
			} catch (Exception $e) {
				if ($this->isDebugEnabled()) {
					echo($e);
					exit;
				}
			}
		}
	}


	public function trackPurchase($status, $quote) {
		$quote->collectTotals();

		try {
			if ($this->isSalesTrackingEnabled()) {
				foreach ($quote->getAllItems() as $item) {
					if ($this->isProductFacilitated($item->getProduct()->getId()) != null) {
						// TODO: track faciliated sales
/*						$row = array(
							$quote->getId(),
							$item->getProduct()->getId(),
							$item->getQty(),
							$item->getPrice()
						);
						oxDb::getDb()->Execute('INSERT DELAYED IGNORE INTO `bxsales` VALUES ( ?, NOW(), ?, ?, ?, ? )', $row);*/
					}
				}
			}
			if ($this->isAnalyticsEnabled()) {
				$items = array();
				foreach ($quote->getAllItems() as $item) {
					$items[] = new CEM_ApiTransactionItem(
						$item->getProduct()->getId(),
						$item->getPrice(),
						$item->getQty(),
						$item->getProduct()->getName(),
						$this->isProductFacilitated($item->getProduct()->getId())
					);
				}

				$this->getApiClient()->trackPurchase($status, $quote->getGrandTotal(), $items);
			}
		} catch (Exception $e) {
			if ($this->isDebugEnabled()) {
				echo($e);
			}
		}
	}


	public static function __loadClass($name) {
		if (strpos($name, 'CEM_') === 0) {
			include_once(Mage::getModuleDir('', 'Boxalino_Cem').'/Lib/'.$name.'.class.php');
		}
	}
}
