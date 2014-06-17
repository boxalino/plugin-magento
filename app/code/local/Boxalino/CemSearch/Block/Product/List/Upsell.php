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

    protected $_columnCount = 4;

    protected $_items;

    protected $_itemCollection;

    protected $_itemLimits = array();

    protected function _prepareData()
    {

        $product = Mage::registry('product');
        /* @var $product Mage_Catalog_Model_Product */
//        $this->_itemCollection = $product->getUpSellProductCollection()
//            ->setPositionOrder()
//            ->addStoreFilter()
//        ;

        ###############################################################
        $_REQUEST['productId'] = $product->getId();

        Mage::helper('Boxalino_CemSearch')->__loadClass('P13nRecommendation');
        $p13nRecommendation = new P13nRecommendation();

        $response = $p13nRecommendation->getRecommendation('upsell', 'product', 'en');
        $productIds = array();

        foreach($response as $item){
            $productIds[] = $item['entity_id'];
        }

//        var_dump($productIds);
        ###############################################################

        if(count($productIds) == 0){
            $this->_itemCollection = Mage::getResourceModel('catalog/product_collection')
                ->addFieldToFilter('entity_id', array('-1'))
                ->addAttributeToSelect('*');
        } else{
            $this->_itemCollection = Mage::getResourceModel('catalog/product_collection')
                ->addFieldToFilter('entity_id', $productIds)
                ->addAttributeToSelect('*');
        }

        if (Mage::helper('catalog')->isModuleEnabled('Mage_Checkout')) {
            Mage::getResourceSingleton('checkout/cart')->addExcludeProductFilter($this->_itemCollection,
                Mage::getSingleton('checkout/session')->getQuoteId()
            );

            $this->_addProductAttributesAndPrices($this->_itemCollection);
        }
//        Mage::getSingleton('catalog/product_status')->addSaleableFilterToCollection($this->_itemCollection);
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