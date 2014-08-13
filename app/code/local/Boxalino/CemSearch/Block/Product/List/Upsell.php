<?php
/**
 * Created by: Szymon Nosal <szymon.nosal@codete.com>
 * Created at: 17.06.14 11:31
 */

class Boxalino_CemSearch_Block_Product_List_Upsell extends Mage_Catalog_Block_Product_List_Upsell
{
    /**
     * Default MAP renderer type
     *
     * @var string
     */
    protected $_mapRenderer = 'msrp_noform';

    protected $_itemCollection;

    protected function _prepareData()
    {

        if(Mage::getStoreConfig('Boxalino_General/general/enabled', 0) == 0 || Mage::getStoreConfig('Boxalino_Recommendation/upsell/status', 0) == 0 ){
            return parent::_prepareData();
        }

        $product = Mage::registry('product');
        /* @var $product Mage_Catalog_Model_Product */

##################################################################################

        $_REQUEST['productId'] = $product->getId();

        Mage::helper('Boxalino_CemSearch')->__loadClass('P13nRecommendation');
        $p13nRecommendation = new P13nRecommendation();

        $response = $p13nRecommendation->getRecommendation('upsell');
        $entityIds = array();

        if($response === null){
            $this->_itemCollection = new Varien_Data_Collection();
            return $this;
        }

        foreach($response as $item){
            $entityIds[] = $item[Mage::getStoreConfig('Boxalino_General/search/entity_id')];
        }

###############################################################

        $this->_itemCollection = Mage::getResourceModel('catalog/product_collection')
            ->addFieldToFilter('entity_id', $entityIds)
            ->addAttributeToSelect('*');

        if (Mage::helper('catalog')->isModuleEnabled('Mage_Checkout')) {
            Mage::getResourceSingleton('checkout/cart')->addExcludeProductFilter($this->_itemCollection,
                Mage::getSingleton('checkout/session')->getQuoteId()
            );
            $this->_addProductAttributesAndPrices($this->_itemCollection);
        }

        Mage::getSingleton('catalog/product_visibility')->addVisibleInCatalogFilterToCollection($this->_itemCollection);

        if ($this->getItemLimit('upsell') > 0) {
            $this->_itemCollection->setPageSize($this->getItemLimit('upsell'));
        }

        $this->_itemCollection->load();

        /**
         * Updating collection with desired items
         */
        Mage::dispatchEvent('catalog_product_upsell', array(
            'product'       => $product,
            'collection'    => $this->_itemCollection,
            'limit'         => $this->getItemLimit()
        ));

        foreach ($this->_itemCollection as $product) {
            $product->setDoNotUseCategoryId(true);
        }

        return $this;
    }

}