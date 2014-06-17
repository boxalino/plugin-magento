<?php
/**
 * Created by: Szymon Nosal <szymon.nosal@codete.com>
 * Created at: 16.06.14 12:29
 */

/**
 * Catalog product related items block
 *
 * @category   Mage
 * @package    Mage_Catalog
 * @author      Magento Core Team <core@magentocommerce.com>
 */
class  Boxalino_CemSearch_Block_Product_List_Related extends Mage_Catalog_Block_Product_List_Related
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
        $product = Mage::registry('product');
        /* @var $product Mage_Catalog_Model_Product */
##################################################################################
//        echo $product->getId() . '  ' . $product->getPrice();
//        Mage::app()->getRequest()->setParam('productId', $product->getId());

        $_REQUEST['productId'] = $product->getId();

        Mage::helper('Boxalino_CemSearch')->__loadClass('P13nRecommendation');
        $p13nRecommendation = new P13nRecommendation();

        $response = $p13nRecommendation->getRecommendation('related', 'product', 'en');
        $productIds = array();

        foreach($response as $item){
            $productIds[] = $item['entity_id'];
        }

        var_dump($productIds);

//        var_dump($_REQUEST['productId']);
###############################################################
//        $this->_itemCollection = $product->getRelatedProductCollection()
//            ->addAttributeToSelect('required_options')
//            ->setPositionOrder()
//            ->addStoreFilter()
//        ;

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
        Mage::getSingleton('catalog/product_status')->addSaleableFilterToCollection($this->_itemCollection);
        Mage::getSingleton('catalog/product_visibility')->addVisibleInCatalogFilterToCollection($this->_itemCollection);

        $this->_itemCollection->load();

        foreach ($this->_itemCollection as $product) {
            $product->setDoNotUseCategoryId(true);
        }

        return $this;
    }

}