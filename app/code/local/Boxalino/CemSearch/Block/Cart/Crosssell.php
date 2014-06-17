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

        #####################################################################################

        $cartItems = array();
        foreach ($this->getQuote()->getAllItems() as $item) {
            $productPrice = $item->getProduct()->getPrice();
            $productId = $item->getProductId();

            if($item->getProductType() === 'configurable'){
                continue;
            }

            $cartItems[] = array('id' => $productId, 'price' => $productPrice);

        }

        $_REQUEST['basketContent'] = json_encode($cartItems);

        Mage::helper('Boxalino_CemSearch')->__loadClass('P13nRecommendation');
        $p13nRecommendation = new P13nRecommendation();

        $response = $p13nRecommendation->getRecommendation('cart');
        $entityIds = array();

        if($response === null){
            return null;
        }

        foreach($response as $item){
            $entityIds[] = $item['entity_id'];
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
        Mage::getSingleton('catalog/product_status')->addSaleableFilterToCollection($itemCollection);
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
