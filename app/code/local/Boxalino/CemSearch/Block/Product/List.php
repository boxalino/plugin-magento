<?php

class Boxalino_CemSearch_Block_Product_List extends Mage_Catalog_Block_Product_List
{
    /**
     * Retrieve loaded category collection
     *
     * @return Mage_Eav_Model_Entity_Collection_Abstract
     */
    protected function _getProductCollection()
    {
        if (Mage::getStoreConfig('Boxalino_General/general/enabled', 0) == 0) {
        	return parent::_getProductCollection();
        }

        $entity_ids = Mage::helper('Boxalino_CemSearch')->getSearchAdapter()->getEntitiesIds();

        $this->_productCollection = Mage::getResourceModel('catalog/product_collection')
             ->addFieldToFilter('entity_id', $entity_ids)
             ->addAttributeToSelect('*');

        if (Mage::helper('catalog')->isModuleEnabled('Mage_Checkout')) {
            Mage::getResourceSingleton('checkout/cart')->addExcludeProductFilter($this->_productCollection,
                Mage::getSingleton('checkout/session')->getQuoteId()
            );
            $this->_addProductAttributesAndPrices($this->_productCollection);
        }
        Mage::getSingleton('catalog/product_visibility')->addVisibleInCatalogFilterToCollection($this->_productCollection);

        $this->_productCollection->load();

        foreach ($this->_productCollection as $product) {
            $product->setDoNotUseCategoryId(true);
        }

        return $this->_productCollection;
    }
}
