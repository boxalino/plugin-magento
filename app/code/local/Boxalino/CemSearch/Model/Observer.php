<?php

	/**
	 * Boxalino Cem event observer
	 *
	 * @author nitro@boxalino.com
	 */
	class Boxalino_CemSearch_Model_Observer{
		public function onProductAddedToCart(Varien_Event_Observer $event){
			try{
				Mage::helper('Boxalino_CemSearch')->trackAddToBasket(
					$event->getProduct()->getId(),
					$event->getProduct()->getName(),
					$event->getQuoteItem()->getQty(),
					$event->getProduct()->getPrice()
				);
				$session = Mage::getSingleton('Boxalino_CemSearch_Model_Session');
				$script = Mage::helper('Boxalino_CemSearch')->reportAddToBasket(
					$event->getProduct()->getName(),
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
				if($quote){
					Mage::helper('Boxalino_CemSearch')->trackPurchase(TRUE, $quote);
				}

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
				$product = $event['product']->getName();
				$script = Mage::helper('Boxalino_CemSearch')->reportProductView($product);

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
