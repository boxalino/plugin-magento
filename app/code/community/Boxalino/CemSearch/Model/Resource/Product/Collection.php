<?php
/**
 * Collection
 *
 * @category    Boxalino
 * @package     Boxalino_CemSearch
 * @author      Simon Rupf <simon.rupf@boxalino.com>
 */
class Boxalino_CemSearch_Model_Resource_Product_Collection extends Mage_Catalog_Model_Resource_Product_Collection
{
    /**
     * Retrieve collection all items count
     *
     * @return int
     */
    public function getSize()
    {
        if(Mage::helper('Boxalino_CemSearch')->getSearchAdapter()->getChoiceResponse() == null) {
            return parent::getSize();
        }

        return Mage::helper('Boxalino_CemSearch')->getSearchAdapter()->getTotalHitCount();
    }

    /**
     * Render sql select limit
     *
     * @return  Varien_Data_Collection_Db
     */
    protected function _renderLimit()
    {
        if(Mage::helper('Boxalino_CemSearch')->getSearchAdapter()->getChoiceResponse() == null) {
            return parent::_renderLimit();
        }
        $this->_select->limit();
        return $this;
    }
}