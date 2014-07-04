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

        if(Mage::getStoreConfig('Boxalino_CemSearch/backend/enabled', 0) == 0){
            return parent::_prepareData();
        }

        $product = Mage::registry('product');
        /* @var $product Mage_Catalog_Model_Product */

##################################################################################

        $_REQUEST['productId'] = $product->getId();

        Mage::helper('Boxalino_CemSearch')->__loadClass('P13nRecommendation');
        $p13nRecommendation = new P13nRecommendation();

        $response = $p13nRecommendation->getRecommendation('related');
        $entityIds = array();

        if($response === null){
            $this->_itemCollection = new Varien_Data_Collection();
            return $this;
        }

        foreach($response as $item){
            $entityIds[] = $item['entity_id'];
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
//        Mage::getSingleton('catalog/product_status')->addSaleableFilterToCollection($this->_itemCollection);
        Mage::getSingleton('catalog/product_visibility')->addVisibleInCatalogFilterToCollection($this->_itemCollection);

        $this->_itemCollection->load();

        foreach ($this->_itemCollection as $product) {
            $product->setDoNotUseCategoryId(true);
        }

        return $this;
    }

}