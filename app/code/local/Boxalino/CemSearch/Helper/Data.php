<?php

	class Boxalino_CemSearch_Helper_Data extends Mage_Core_Helper_Data {
		protected $client = null;

		protected $lastUri = null;

		public function __construct() {
			spl_autoload_register(array('Boxalino_CemSearch_Helper_Data', '__loadClass'), TRUE, TRUE);
		}

		public static function __loadClass($name, $isCem = false, $ext = '.class') {
			$files = array('P13nAdapter','P13nConfig','P13nSort', 'P13nRecommendation');
			if(in_array($name, $files)){
				include_once(Mage::getModuleDir('', 'Boxalino_CemSearch').'/Helper/'.$name.'.class.php');

			}elseif (strpos($name, 'CEM_') !== false  || strpos($name, 'P13n') !== false || $isCem) {
				include_once(Mage::getModuleDir('', 'Boxalino_CemSearch').'/Lib/'.$name.$ext.'.php');
			}
		}

		//===========================================================================================

		public function isEnabled(){
			return (Mage::getStoreConfig('Boxalino_CemSearch/frontend/enabled') == 1);
		}

		public function isAnalyticsEnabled(){
			return (
				Mage::getStoreConfig('Boxalino_CemSearch/frontend/enabled') == 1 &&
				Mage::getStoreConfig('Boxalino_CemSearch/frontend/analytics') == 1
			);
		}

		public function isSalesTrackingEnabled(){
			return (
				Mage::getStoreConfig('Boxalino_CemSearch/frontend/enabled') == 1 &&
				Mage::getStoreConfig('Boxalino_CemSearch/frontend/track_sales') == 1
			);
		}

		public function isLogEnabled(){
			return FALSE;
		}

		public function isDebugEnabled(){
			return (Mage::getStoreConfig('Boxalino_CemSearch/frontend/debug') == 1);
		}

		public function getLanguage(){
			return Mage::getStoreConfig('Boxalino_CemSearch/service/language');
		}

		public function isProductFacilitated($id){
			// TODO: track faciliated sales
			//		$facilitatedProducts = $this->getSession()->getVar('bxcemFacilitatedProducts');
			//		return ($facilitatedProducts && isset($facilitatedProducts[$id]) ? $facilitatedProducts[$id] : NULL);
			return FALSE;
		}

		public function trackFacilitatedProduct($id){
			if($this->isSalesTrackingEnabled() && isset($_REQUEST['widget'])){
				// TODO: track faciliated sales
				/*			$products = $this->getSession()->getVar('bxcemFacilitatedProducts');
							if ($products === null) {
								$products = array();
							}
							$products[$id] = $_REQUEST['widget'];
							$this->getSession()->setVar('bxcemFacilitatedProducts', $products);*/
			}
		}

		public function getBasketAmount(){
			$checkout = Mage::getSingleton('checkout/session');
			$quote = $checkout->getQuote();
			$amount = 0;
			if($quote){
				foreach($quote->getAllVisibleItems() as $item){
					$amount += $item->getQty() * $item->getPrice();
				}
			}
			return $amount;
		}

		public function getBasketItems(){
			$items = array();
			$checkout = Mage::getSingleton('checkout/session');
			$quote = $checkout->getQuote();
			if($quote){
				foreach($quote->getAllVisibleItems() as $item){
					$items[] = $item->product_id;
				}
			}
			return $items;
		}

		public function getBasketContent(){
			$checkout = Mage::getSingleton('checkout/session');
			$quote = $checkout->getQuote();
			$items = array();
			if($quote){
				foreach($quote->getAllVisibleItems() as $item){
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

		public function setSeoBase($value){
			$this->seoBase = $value;
		}

		public function setSeoSuffix($value){
			$this->seoSuffix = $value;
		}

		public function getApiClient(){
			if(!$this->client){
				$this->client = new CEM_MagentoApiClient(Mage::app()->getStore());
			}
			return $this->client;
		}

		public function isPageEnabled($uri){
			if($this->pagesEnabled == null){
				$this->pagesEnabled = array();
				if($this->isEnabled()){
					foreach(explode(',', Mage::getStoreConfig('Boxalino_CemSearch/frontend/pageapi')) as $entry){
						$entry = trim($entry);
						if(strlen($entry) > 0){
							$this->pagesEnabled[$entry] = TRUE;
						}
					}
				}
			}
			return isset($this->pagesEnabled[$uri]);
		}


		public function trackAddToBasket($id, $name, $quantity = 1, $price = 0){
			if($this->isAnalyticsEnabled()){
				try{
					$this->getApiClient()->trackAddToBasket(
						new CEM_ApiTransactionItem($id, $price, $quantity, $name, $this->isProductFacilitated($id))
					);
				}catch(Exception $e){
					if($this->isDebugEnabled()){
						echo($e);
						exit;
					}
				}
			}
		}

		public function trackPurchase($status, $quote){
			$quote->collectTotals();

			try{
				if($this->isSalesTrackingEnabled()){
					foreach($quote->getAllItems() as $item){
						if($this->isProductFacilitated($item->getProduct()->getId()) != null){
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
				if($this->isAnalyticsEnabled()){
					$items = array();
					foreach($quote->getAllItems() as $item){
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
			}catch(Exception $e){
				if($this->isDebugEnabled()){
					echo($e);
				}
			}
		}


		public function buildScript($pushes){
			return
				<<<SCRIPT
				            <script type="text/javascript">
                var _bxq = _bxq || [];
                $pushes

            </script>
SCRIPT;
		}

		public function reportPageView(){
			$account = Mage::getStoreConfig('Boxalino_CemSearch/service/account');
			$script = <<<SCRIPT
                _bxq.push(['setAccount', '$account']);
                _bxq.push(['trackPageView']);
SCRIPT;
			return $this->buildScript($script);
		}

		public function reportSearch($term){
			$logTerm = addslashes($term);
			$account = Mage::getStoreConfig('Boxalino_CemSearch/service/account');

			$script = <<<SCRIPT
                _bxq.push(['setAccount', '$account']);
                _bxq.push(['trackSearch', '$logTerm']);
SCRIPT;
			return $this->buildScript($script);
		}

		public function reportProductView($product){
			$account = Mage::getStoreConfig('Boxalino_CemSearch/service/account');

			$script = <<<SCRIPT
                _bxq.push(['setAccount', '$account']);
                _bxq.push(['trackProductView', '$product']);
SCRIPT;
			return $this->buildScript($script);
		}

		public function reportAddToBasket($product, $count, $price, $currency){
			$account = Mage::getStoreConfig('Boxalino_CemSearch/service/account');

			$script = <<<SCRIPT
                _bxq.push(['setAccount', '$account']);
                _bxq.push(['trackAddToBasket', '$product ', $count, $price, '$currency']);
SCRIPT;
			return $this->buildScript($script);
		}

		/**
		 * @param $products array example:
		 *      <code>
		 *          array(
		 *              array('product' => 'PRODUCTID1', 'quantity' => 1, 'price' => 59.90),
		 *              array('product' => 'PRODUCTID2', 'quantity' => 2, 'price' => 10.0)
		 *          )
		 *      </code>
		 * @param $orderId string
		 * @param $price number
		 * @param $currency string
		 */
		public function reportPurchase($products, $orderId, $price, $currency){
			$account = Mage::getStoreConfig('Boxalino_CemSearch/service/account');

			$productsJson = json_encode($products);
			$script = <<<SCRIPT
                _bxq.push(['setAccount', '$account']);
                _bxq.push([
                    'trackPurchase',
                    $price,
                    '$currency',
                    $productsJson,
                    $orderId
                 ]);

SCRIPT;
			return $this->buildScript($script);
		}
	}
