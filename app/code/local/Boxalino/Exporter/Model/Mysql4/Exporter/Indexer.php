<?php

class Boxalino_Exporter_Model_Mysql4_Exporter_Indexer extends Boxalino_Exporter_Model_Mysql4_Indexer
{
    const INDEX_TYPE = 'full';

    /**
     * @description Declare where Indexer should start
     * @return void
     */
    protected function _construct()
    {
        $this->_init('boxalinoexporter/indexer', '');
    }

    protected function _prepareFiles($website,&$categories = null, &$tags = null)
    {
    }

    /**
     * @description Get all transactions from Magento
     * @return object All transactions for store
     */
    protected function _getTransactions()
    {
//        $transaction = Mage::getModel('sales/order')->setStoreId($this->_storeId)->getCollection()->addAttributeToSelect('*');
//
//        return $transaction;
    }

    /**
     * @description Get all customers from Magento
     * @return object All customers
     */
    protected function _getCustomers()
    {
//        $customers = Mage::getModel('customer/customer')->getCollection()->addAttributeToSelect('*');
//
//        return $customers;
    }

    /**
     * @description Get list of all categories
     * @return object All categories for Store
     */
    protected function _getCategories()
    {
        $categories = Mage::getModel('catalog/category')->setStoreId($this->_storeId)->getCollection()->addAttributeToSelect('*');

        return $categories;
    }

    /**
     * @description Get products for store by storeId
     * @return object All products of magento for store
     */
    protected function _getStoreProducts()
    {
//        $products = Mage::getModel('catalog/product')->setStoreId($this->_storeId)->getCollection()->addAttributeToSelect($this->_listOfAttributes);
//        return $products;
    }

    /**
     * @return string Index type
     */
    protected function _getIndexType()
    {
        return self::INDEX_TYPE;
    }
}