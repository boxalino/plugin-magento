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
        if (Mage::getStoreConfig('Boxalino_General/general/enabled') == 0) {
            return parent::_getProductCollection();
        }

        if (is_null($this->_productCollection)) {
            $entity_ids = Mage::helper('Boxalino_CemSearch')->getSearchAdapter()->getEntitiesIds();

            $this->_productCollection = Mage::getResourceModel('catalog/product_collection');

            // Added check if there are any entity ids
            if(count($entity_ids) > 0){
                $this->_productCollection->addFieldToFilter('entity_id', $entity_ids)
                    ->addAttributeToSelect('*');
            }

            if (Mage::helper('catalog')->isModuleEnabled('Mage_Checkout')) {
                Mage::getResourceSingleton('checkout/cart')->addExcludeProductFilter($this->_productCollection,
                    Mage::getSingleton('checkout/session')->getQuoteId()
                );
                $this->_addProductAttributesAndPrices($this->_productCollection);
            }
            Mage::getSingleton('catalog/product_visibility')->addVisibleInCatalogFilterToCollection($this->_productCollection);
        }

        return $this->_productCollection;
    }
}
