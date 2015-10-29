<?php
/**
 * Collection Fulltext
 *
 * @category    Boxalino
 * @package     Boxalino_CemSearch
 * @author      Simon Rupf <simon.rupf@boxalino.com>
 */
class Boxalino_CemSearch_Model_Resource_Fulltext_Collection extends Mage_CatalogSearch_Model_Resource_Fulltext_Collection
{
    /**
     * Retrieve collection all items count
     *
     * @return int
     */
    public function getSize()
    {
        return Mage::helper('Boxalino_CemSearch')->getSearchAdapter()->getTotalHitCount();
    }

    /**
     * Render sql select limit
     *
     * @return  Varien_Data_Collection_Db
     */
    protected function _renderLimit()
    {
        // ignore limit
        $this->_select->limit();
        return $this;
    }
}