<?php

/**
 * Created by: Szymon Nosal <szymon.nosal@codete.com>
 * Created at: 13.06.14 12:25
 */
class Boxalino_CemSearch_Block_Cart_Crosssell extends Mage_Checkout_Block_Cart_Crosssell
{

    /**
     * Items quantity will be capped to this value
     *
     * @var int
     */
    protected $_maxItemCount = 4;

    /**
     * Get crosssell items
     *
     * @return array
     */
    public function getItems()
    {

        if (Mage::getStoreConfig('Boxalino_General/general/enabled', 0) == 0) {
            return parent::getItems();
        }
        $name = Mage::getStoreConfig('Boxalino_Recommendation/cart/widget');
        #####################################################################################

        $cartItems = array();
        foreach ($this->getQuote()->getAllItems() as $item) {
            $productPrice = $item->getProduct()->getPrice();
            $productId = $item->getProductId();

            if ($item->getProductType() === 'configurable') {
                continue;
            }

            $cartItems[] = array('id' => $productId, 'price' => $productPrice);

        }

        $_REQUEST['basketContent'] = json_encode($cartItems);

        $p13nRecommendation = Boxalino_CemSearch_Helper_P13n_Recommendation::Instance();

        $response = $p13nRecommendation->getRecommendation('basket', $name);
        $entityIds = array();

        if ($response === null) {
            return null;
        }

        foreach ($response as $item) {
            $entityIds[] = $item[Mage::getStoreConfig('Boxalino_General/search/entity_id')];
        }

        if (empty($entityIds)) {
            return parent::getItems();
        }

        #########################################################################################

        $itemCollection = Mage::getResourceModel('catalog/product_collection')
            ->addFieldToFilter('entity_id', $entityIds)
            ->addAttributeToSelect('*');

        if (Mage::helper('catalog')->isModuleEnabled('Mage_Checkout')) {
            Mage::getResourceSingleton('checkout/cart')->addExcludeProductFilter($itemCollection,
                Mage::getSingleton('checkout/session')->getQuoteId()
            );
            $this->_addProductAttributesAndPrices($itemCollection);
        }
        Mage::getSingleton('catalog/product_visibility')->addVisibleInCatalogFilterToCollection($itemCollection);

        $itemCollection->load();
        $items = array();
        foreach ($itemCollection as $product) {
            $product->setDoNotUseCategoryId(true);
            $items[] = $product;
        }


        return $items;
    }

}
