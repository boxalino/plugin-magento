<?php

	/**
	 * Boxalino CemExport event observer
	 *
	 * @author nitro@boxalino.com
	 */
	class Boxalino_CemSearch_Model_Observer{
		public function onProductAddedToCart(Varien_Event_Observer $event){
			try{
				$session = Mage::getSingleton('Boxalino_CemSearch_Model_Session');
				$script = Mage::helper('Boxalino_CemSearch')->reportAddToBasket(
					$event->getProduct()->getId(),
					$event->getQuoteItem()->getQty(),
					$event->getProduct()->getPrice(),
					Mage::app()->getStore()->getCurrentCurrencyCode()
				);
				$session->addScript($script);
			}catch(Exception $e){
				if(Mage::helper('Boxalino_CemSearch')->isDebugEnabled()){
					echo($e);
					exit;
				}
			}
		}

		public function onOrderSuccessPageView(Varien_Event_Observer $event){
			try{
				$quoteId = Mage::getSingleton('Boxalino_CemSearch_Model_Session')->getLastQuoteId();
				$quote = Mage::getModel('sales/quote')->load($quoteId);

				$products = array();
				$price = 0;
				foreach($quote->getAllItems() as $item){
					if($item->getPrice() > 0){
						$products[] = array(
							'product' => $item->getProduct()->getId(),
							'quantity' => $item->getQty(),
							'price' => $item->getPrice()
						);
					}
				}
				$script = Mage::helper('Boxalino_CemSearch')->reportPurchase($products, $quoteId, $price, Mage::app()->getStore()->getCurrentCurrencyCode());

				$session = Mage::getSingleton('Boxalino_CemSearch_Model_Session');
				$session->addScript($script);
			}catch(Exception $e){
				if(Mage::helper('Boxalino_CemSearch')->isDebugEnabled()){
					echo($e);
					exit;
				}
			}
		}
		public function onProductPageView(Varien_Event_Observer $event){
			try{
				$productId = $event['product']->getId();
				$script = Mage::helper('Boxalino_CemSearch')->reportProductView($productId);

				$session = Mage::getSingleton('Boxalino_CemSearch_Model_Session');
				$session->addScript($script);
			}catch(Exception $e){
				if(Mage::helper('Boxalino_CemSearch')->isDebugEnabled()){
					echo($e);
					exit;
				}
			}
		}
		public function onCategoryPageView(Varien_Event_Observer $event){

			try{
				$categoryId = $event['category']['entity_id'];
				$script = Mage::helper('Boxalino_CemSearch')->reportCategoryView($categoryId);

				$session = Mage::getSingleton('Boxalino_CemSearch_Model_Session');
				$session->addScript($script);
			}catch(Exception $e){
				if(Mage::helper('Boxalino_CemSearch')->isDebugEnabled()){
					echo($e);
					exit;
				}
			}
		}
		public function onLogin(Varien_Event_Observer $event){
			try{
				$userId = $event['customer']['entity_id'];
				$script = Mage::helper('Boxalino_CemSearch')->reportLogin($userId);

				$session = Mage::getSingleton('Boxalino_CemSearch_Model_Session');
				$session->addScript($script);
			}catch(Exception $e){
				if(Mage::helper('Boxalino_CemSearch')->isDebugEnabled()){
					echo($e);
					exit;
				}
			}
		}
	}
