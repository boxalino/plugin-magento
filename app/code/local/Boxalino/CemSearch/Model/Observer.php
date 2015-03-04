<?php

/**
 * Boxalino CemExport event observer
 *
 * @author nitro@boxalino.com
 */
class Boxalino_CemSearch_Model_Observer
{
    public function onProductAddedToCart(Varien_Event_Observer $event)
    {
        try {
            $session = Mage::getSingleton('Boxalino_CemSearch_Model_Session');
            $script = Mage::helper('Boxalino_CemSearch')->reportAddToBasket(
                $event->getProduct()->getId(),
                $event->getQuoteItem()->getQty(),
                $event->getProduct()->getSpecialPrice() > 0 ? $event->getProduct()->getSpecialPrice() : $event->getProduct()->getPrice(),
                Mage::app()->getStore()->getCurrentCurrencyCode()
            );
            $session->addScript($script);
        } catch (Exception $e) {
            if (Mage::helper('Boxalino_CemSearch')->isDebugEnabled()) {
                echo($e);
                exit;
            }
        }
    }

    public function onOrderSuccessPageView(Varien_Event_Observer $event)
    {
        try {
            $orders = Mage::getModel('sales/order')->getCollection()
                ->setOrder('entity_id', 'DESC')
                ->setPageSize(1)
                ->setCurPage(1);
            $order = $orders->getFirstItem();
            $orderData = $order->getData();
            $transactionId = $orderData['entity_id'];
            $products = array();
            $fullPrice = 0;
            foreach ($order->getAllItems() as $item) {
                if ($item->getPrice() > 0) {
                    $products[] = array(
                        'product' => $item->getProduct()->getId(),
                        'quantity' => $item->getData('qty_ordered'),
                        'price' => $item->getPrice()
                    );
                    $fullPrice = $fullPrice + $item->getPrice();
                }
            }
            $script = Mage::helper('Boxalino_CemSearch')->reportPurchase($products, $transactionId, $fullPrice, Mage::app()->getStore()->getCurrentCurrencyCode());

            $session = Mage::getSingleton('Boxalino_CemSearch_Model_Session');
            $session->addScript($script);
        } catch (Exception $e) {
            if (Mage::helper('Boxalino_CemSearch')->isDebugEnabled()) {
                echo($e);
                exit;
            }
        }
    }

    public function onProductPageView(Varien_Event_Observer $event)
    {
        try {
            $productId = $event['product']->getId();
            $script = Mage::helper('Boxalino_CemSearch')->reportProductView($productId);

            $session = Mage::getSingleton('Boxalino_CemSearch_Model_Session');
            $session->addScript($script);
        } catch (Exception $e) {
            if (Mage::helper('Boxalino_CemSearch')->isDebugEnabled()) {
                echo($e);
                exit;
            }
        }
    }

    public function onCategoryPageView(Varien_Event_Observer $event)
    {

        try {
            $categoryId = $event['category']['entity_id'];
            $script = Mage::helper('Boxalino_CemSearch')->reportCategoryView($categoryId);

            $session = Mage::getSingleton('Boxalino_CemSearch_Model_Session');
            $session->addScript($script);
        } catch (Exception $e) {
            if (Mage::helper('Boxalino_CemSearch')->isDebugEnabled()) {
                echo($e);
                exit;
            }
        }
    }

    public function onLogin(Varien_Event_Observer $event)
    {
        try {
            $userId = $event['customer']['entity_id'];
            $script = Mage::helper('Boxalino_CemSearch')->reportLogin($userId);

            $session = Mage::getSingleton('Boxalino_CemSearch_Model_Session');
            $session->addScript($script);
        } catch (Exception $e) {
            if (Mage::helper('Boxalino_CemSearch')->isDebugEnabled()) {
                echo($e);
                exit;
            }
        }
    }
}
