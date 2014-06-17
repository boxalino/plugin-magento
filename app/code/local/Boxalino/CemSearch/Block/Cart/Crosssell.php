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
        $items = $this->getData('items');
//        if (is_null($items)) {
//            $items = array();
//            $ninProductIds = $this->_getCartProductIds();
//            if ($ninProductIds) {
//                $lastAdded = (int) $this->_getLastAddedProductId();
//                if ($lastAdded) {
//                    $collection = $this->_getCollection()
//                        ->addProductFilter($lastAdded);
//                    if (!empty($ninProductIds)) {
//                        $collection->addExcludeProductFilter($ninProductIds);
//                    }
//                    $collection->setPositionOrder()->load();
//
//                    foreach ($collection as $item) {
//                        $ninProductIds[] = $item->getId();
//                        $items[] = $item;
//                    }
//                }
//
//                if (count($items) < $this->_maxItemCount) {
//
//                    $filterProductIds = array_merge($this->_getCartProductIds(), $this->_getCartProductIdsRel());
////                    var_dump($filterProductIds);
//                    $collection = $this->_getCollection()
//                        ->addProductFilter($filterProductIds)
//                        ->addExcludeProductFilter($ninProductIds)
//                        ->setPageSize($this->_maxItemCount-count($items))
//                        ->setGroupBy()
//                        ->setPositionOrder()
//                        ->load();
//
//                    $items[] = Mage::getModel('catalog/product')->load('406');
//                    $items[] = Mage::getModel('catalog/product')->load('422');
//                    $items[] = Mage::getModel('catalog/product')->load('394');
//                    foreach ($collection as $item) {
////                        echo "xxx  ";
//                        $items[] = $item;
//                    }
////                    $items[] = $_newProduct;
////                    var_dump($items);
//                }
//
//            }
//
//            $this->setData('items', $items);
//        }

//        if(is_null($items)){
            $items = array();

            #####################################################################################

            $cartItems = array();
            foreach ($this->getQuote()->getAllItems() as $item) {
                $productName = $item->getProduct()->getName();
                $productPrice = $item->getProduct()->getPrice();
                $productId = $item->getProductId();

                if($item->getProductType() === 'configurable'){
                    continue;
                }

                $cartItems[] = array('id' => $productId, 'price' => $productPrice);

//                var_dump(array($productId, $productPrice, $productName, $item->getProductType()));
            }

            $_REQUEST['basketContent'] = json_encode($cartItems);
            $lang = substr(Mage::app()->getLocale()->getLocaleCode(),0,2);

            Mage::helper('Boxalino_CemSearch')->__loadClass('P13nRecommendation');
            $p13nRecommendation = new P13nRecommendation();

            $prods = $p13nRecommendation->getRecommendation('cart', 'basket', $lang);

            $entityIds = array();

            foreach($prods as $prod){
//                var_dump($prod);
//                $item = Mage::getModel('catalog/product')->load($prod['entity_id']);
                $entityIds[] = $prod['entity_id'];
//                $stock = 0;
//
//                if($item->isConfigurable()){
//
////                    foreach($item->getAssociatedProducts() as $assoc){
////                        $stock += Mage::getModel('cataloginventory/stock_item')->loadByProduct($assoc)->getQty();
////                        var_dump($stock);
////                    }
//                    $childProducts = Mage::getModel('catalog/product_type_configurable')
//                        ->getUsedProducts(null,$item);
//                    foreach($childProducts as $child) {
//                        $stock += Mage::getModel('cataloginventory/stock_item')->loadByProduct($child)->getQty();
////                        var_dump($child->getStockItem()->getQty());
//                    }
//
//                }
//                $stock += Mage::getModel('cataloginventory/stock_item')->loadByProduct($item)->getQty();
//
//                var_dump(array($item->getName(), $stock, $item->getIsInStock()));

//                if($item->getIsInStock()){
//                if($stock > 0){
//                    $items[] = $item;
//                }
//
            }

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

            #########################################################################################3

//            $this->setData('items', $items);
//        }

        return $items;
    }

}
