<?php

/**
 * Boxalino Cem event observer
 *
 * @author nitro@boxalino.com
 */
class Boxalino_Cem_Model_Observer {
	public function onProductAddedToCart(Varien_Event_Observer $event) {
		try {
			Mage::helper('boxalinocem')->trackAddToBasket(
				$event->getProduct()->getId(),
				$event->getProduct()->getName(),
				$event->getQuoteItem()->getQty(),
				$event->getProduct()->getPrice()
			);
		} catch (Exception $e) {
			if (Mage::helper('boxalinocem')->isDebugEnabled()) {
				echo($e);
				exit;
			}
		}
	}

	public function onOrderSuccessPageView(Varien_Event_Observer $event) {
		try {
			$quoteId = Mage::getSingleton('checkout/session')->getLastQuoteId();
			$quote = Mage::getModel('sales/quote')->load($quoteId);
			if ($quote) {
				Mage::helper('boxalinocem')->trackPurchase(TRUE, $quote);
			}
		} catch (Exception $e) {
			if (Mage::helper('boxalinocem')->isDebugEnabled()) {
				echo($e);
				exit;
			}
		}
	}
}
