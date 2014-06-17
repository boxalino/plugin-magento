<?php

	/**
	 * Boxalino Cem event observer
	 *
	 * @author nitro@boxalino.com
	 */
	class Boxalino_Cem_Model_Observer{
		public function onProductAddedToCart(Varien_Event_Observer $event){
			try{
				Mage::helper('boxalinocem')->trackAddToBasket(
					$event->getProduct()->getId(),
					$event->getProduct()->getName(),
					$event->getQuoteItem()->getQty(),
					$event->getProduct()->getPrice()
				);
				$session = Mage::getSingleton('boxalinocem/session');
				$script = Mage::helper('boxalinocem')->reportAddToBasket(
					$event->getProduct()->getName(),
					$event->getQuoteItem()->getQty(),
					$event->getProduct()->getPrice(),
					Mage::app()->getStore()->getCurrentCurrencyCode()
				);
				$session->addScript($script);

			}catch(Exception $e){
				if(Mage::helper('boxalinocem')->isDebugEnabled()){
					echo($e);
					exit;
				}
			}
		}

		public function onOrderSuccessPageView(Varien_Event_Observer $event){
			try{
				$quoteId = Mage::getSingleton('checkout/session')->getLastQuoteId();
				$quote = Mage::getModel('sales/quote')->load($quoteId);
				if($quote){
					Mage::helper('boxalinocem')->trackPurchase(TRUE, $quote);
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
				$script = Mage::helper('boxalinocem')->reportPurchase($products, $quoteId, $price, Mage::app()->getStore()->getCurrentCurrencyCode());

				$session = Mage::getSingleton('boxalinocem/session');
				$session->addScript($script);

			}catch(Exception $e){
				if(Mage::helper('boxalinocem')->isDebugEnabled()){
					echo($e);
					exit;
				}
			}
		}
		public function onProductPageView(Varien_Event_Observer $event){
			try{
				$product = $event['product']->getName();
				$script = Mage::helper('boxalinocem')->reportProductView($product);

				$session = Mage::getSingleton('boxalinocem/session');
				$session->addScript($script);

			}catch(Exception $e){
				if(Mage::helper('boxalinocem')->isDebugEnabled()){
					echo($e);
					exit;
				}
			}
		}
	}
