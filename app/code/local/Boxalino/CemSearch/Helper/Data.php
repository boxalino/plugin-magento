<?php

	class Boxalino_CemSearch_Helper_Data extends Mage_Core_Helper_Data {

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

		public function getApiClient(){
			if(!$this->client){
				$this->client = new CEM_MagentoApiClient(Mage::app()->getStore());
			}
			return $this->client;
		}

		public function isAnalyticsEnabled(){
			$trackSales = Mage::getStoreConfig('Boxalino_CemSearch/tracking/analytics');
			return ($trackSales == 1);
		}
		public function isSalesTrackingEnabled(){
			$trackSales = Mage::getStoreConfig('Boxalino_CemSearch/tracking/analytics');
			return ($trackSales == 1);
		}

		public function buildScript($pushes){
			$enabled = Mage::getStoreConfig('Boxalino_CemSearch/tracking/enabled');
			if($enabled == 1){
				$account = Mage::getStoreConfig('Boxalino_CemSearch/backend/account');

				$script =  '<script type="text/javascript">' . PHP_EOL;
				$script .= 'var _bxq = _bxq || [];'. PHP_EOL;
				$script .= "_bxq.push(['setAccount', '" . $account . "']);". PHP_EOL;
				if($loggedInUserId = $this->getLoggedInUserId()){
					$script .= "_bxq.push(['setUser', '".$loggedInUserId."']);". PHP_EOL;
				}
				$script .= $pushes . PHP_EOL;
				$script .= '</script>'. PHP_EOL;
				return $script;
			}else{
				return '';
			}
		}

		public function reportPageView(){
			if($this->isAnalyticsEnabled()){
				$script = "_bxq.push(['trackPageView']);". PHP_EOL;
				return $this->buildScript($script);
			}else{
				return '';
			}
		}

		public function reportSearch($term){
			if($this->isAnalyticsEnabled()){
				$logTerm = addslashes($term);
				$script = "_bxq.push(['trackSearch', '".$logTerm."']);". PHP_EOL;
				return $this->buildScript($script);
			}else{
				return '';
			}
		}

		public function reportProductView($product){
			if($this->isAnalyticsEnabled()){
				$script = "_bxq.push(['trackProductView', '".$product."'])" . PHP_EOL;
				return $this->buildScript($script);
			}else{
				return '';
			}
		}

		public function reportAddToBasket($product, $count, $price, $currency){
			if($this->isAnalyticsEnabled()){
				$script = "_bxq.push(['trackAddToBasket', '".$product."', ".$count.", ".$price.", '".$currency."']);". PHP_EOL;
				return $this->buildScript($script);
			}else{
				return '';
			}
		}

		public function reportCategoryView($categoryID){
			if($this->isAnalyticsEnabled()){
				$script = "_bxq.push(['trackCategoryView', '".$categoryID."'])" . PHP_EOL;
				return $this->buildScript($script);
			}else{
				return '';
			}
		}
		public function reportLogin($customerId){
			if($this->isAnalyticsEnabled()){
				$script = "_bxq.push(['trackLogin', '".$customerId."'])" . PHP_EOL;
				return $this->buildScript($script);
			}else{
				return '';
			}
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
			$trackSales = Mage::getStoreConfig('Boxalino_CemSearch/tracking/track_sales');

			$productsJson = json_encode($products);
			if($trackSales == 1){
                $script = "_bxq.push([". PHP_EOL;
                $script .= "'trackPurchase',". PHP_EOL;
                $script .= $price.",". PHP_EOL;
                $script .= "'".$currency."',". PHP_EOL;
                $script .= $productsJson."". PHP_EOL;
                $script .= "]);". PHP_EOL;
				return $this->buildScript($script);
			}else{
				return '';
			}
		}

		public function getLoggedInUserId(){
			if(Mage::getSingleton('customer/session')->isLoggedIn()) {
				$customerData = Mage::getSingleton('customer/session')->getCustomer();
				return $customerData->getId();
			}else{
				return  null;
			}
		}
	}
