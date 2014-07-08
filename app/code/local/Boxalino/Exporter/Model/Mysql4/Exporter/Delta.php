<?php
class Boxalino_Exporter_Model_Mysql4_Exporter_Delta extends Boxalino_Exporter_Model_Mysql4_Indexer
{
    /** @var date Date of last data sync */
    protected $_lastIndex = 0;

    const INDEX_TYPE = 'delta';

    /**
     * @description set when was last data sync
     */
    protected function _setLastIndex()
    {
        $dates = array();
        $indexes = Mage::getModel('index/indexer')->getProcessesCollection()->getData();
        foreach($indexes as $index) {
            if(($index['indexer_code'] == 'boxalinoexporter_indexer' || $index['indexer_code'] == 'boxalinoexporter_delta') && !empty($index['started_at'])) {
               $dates[] = DateTime::createFromFormat('Y-m-d H:i:s', $index['started_at']);
            }
        }
        if (count($dates) == 2) {
            if($dates[0] > $dates[1]) {
                $date = $dates[0]->format('Y-m-d H:i:s');
            } else {
                $date = $dates[1]->format('Y-m-d H:i:s');
            }
        } else {
            $date = $dates[0]->format('Y-m-d H:i:s');
        }

        $this->_lastIndex = $date;
    }

    /**
     * @description Get date of last data sync
     */
    protected function _getLastIndex()
    {
        if($this->_lastIndex == 0) {
            $this->_setLastIndex();
        }
        return $this->_lastIndex;
    }

    /**
     * @description Declare where Indexer should start
     * @return void
     */
    protected function _construct()
    {
        $this->_init('boxalinoexporter/delta', '');
        $this->_getLastIndex();
    }

    /**
    * @description Get all transactions from Magento
    * @return object All transactions for store
    */
    protected function _getTransactions()
    {
        $transaction = Mage::getModel('sales/order')->setStoreId($this->_storeId)->getCollection()->addAttributeToFilter('updated_at', array('from' => $this->_lastIndex, 'date' => true))->addAttributeToSelect('*');
        return $transaction;
    }

    /**
     * @description Get all customers from Magento
     * @return object All customers
     */
    protected function _getCustomers()
    {
        $customers = Mage::getModel('customer/customer')->getCollection()->addAttributeToFilter('updated_at', array('from' => $this->_lastIndex, 'date' => true))->addAttributeToSelect('*');
        return $customers;
    }

    /**
     * @description Get list of all categories
     * @return object All categories for Store
     */
    protected function _getCategories()
    {
        $categories = Mage::getModel('catalog/category')->setStoreId($this->_storeId)->getCollection()->addAttributeToFilter('updated_at', array('from' => $this->_lastIndex, 'date' => true))->addAttributeToSelect('*');
        return $categories;
    }

    /**
     * @description Get list of products with their tags
     * @return object List of products with their tags
     */
    protected function _getProductTags()
    {
        if(empty($this->_allProductTags)) {
            $tags = Mage::getResourceModel('tag/product_collection')->addAttributeToFilter('updated_at', array('from' => $this->_lastIndex, 'date' => true))->getData();
            foreach ($tags as $tag) {
                $this->_allProductTags[$tag['entity_id']] = $tag['tag_id'];
            }
        }
        return $this->_allProductTags;
    }

    /**
     * @description Get products for store by storeId
     * @return object All products of magento for store
     */
    protected function _getStoreProducts()
    {
        $products = Mage::getModel('catalog/product')->setStoreId($this->_storeId)->getCollection()->addAttributeToFilter('updated_at', array('from' => $this->_lastIndex, 'date' => true))->addAttributeToSelect(array($this->_listOfAttributes) );
        var_dump($products->getData());
        return $products;
    }

    /**
     * @return string Index type
     */
    protected function _getIndexType()
    {
        return self::INDEX_TYPE;
    }
}