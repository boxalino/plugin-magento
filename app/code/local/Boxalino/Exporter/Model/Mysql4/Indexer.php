<?php

abstract class Boxalino_Exporter_Model_Mysql4_Indexer extends Mage_Core_Model_Mysql4_Abstract
{
    /** @var array Configuration for each Store View */
    protected $_storeConfig = array();

    /** @var array Values of attributes where array('storeId' => array('attrName' => array('id' => 'value'))) */
    protected $_attributesValues = array();

    /** @var array Number of stockQty for all products. Example: array('productId' => 'qty') */
    protected $productsStockQty = array();

    /** @var array List of attributes which module export to our server */
    protected $_listOfAttributes = array();

    /** @var array All tags of products*/
    protected $_allProductTags = array();

    protected $_transformedCategories = array();

    protected $_transformedTags = array();

    protected $_transformedProducts = array();

    protected $_countries = null;

    protected $_attributesValuesByName = array();

    /** @var int Actually used storeId */
    protected $_storeId = 0;

    abstract protected function _getIndexType();

    abstract protected function _getStoreProducts();

    abstract protected function _getCustomers();

    abstract protected function _getTransactions();

    abstract protected function _getCategories();

    /**
     * @description Start of apocalypse
     */
    public function reindexAll()
    {
        $t1 = new DateTime();
        $this->_websiteExport();
        $t2 = new DateTime();
        $this->_closeExport();
        $t3 = new DateTime();
        echo "\n\n\nStart: " . $t1->format("Y-m-d H:i:s") . " <br>";
        echo "Export: " . $t2->format("Y-m-d H:i:s") . " <br>";
        echo "End: " . $t3->format("Y-m-d H:i:s") . " <br>";
        die();
    }

    /**
     * @description Declare what code have to do for Website scope
     * @return void
     */
    protected function _websiteExport()
    {
        foreach (Mage::app()->getWebsites() as $website) {
            $data = $this->_storeExport($website);
//            var_dump($data['transactions']);

            $this->prepareFiles($data['products'], $data['categories'], $data['customers'], $data['tags'], $data['transactions']);
            $this->_transformedCategories = array();
            $this->_transformedTags = array();
            $this->_transformedProducts = array();
            die();
        }
    }

    /**
     * @description Declare what code have to do for Website scope
     * @param $website object Object of currently working Website
     * @return array Data prepared for save to file
     */
    protected function _storeExport($website)
    {
        $products = array();
        $categories = array();
        $customers = array();
        $tags = array();
        $transactions = array();
        foreach ($website->getGroups() as $group) {
            foreach ($group->getStores() as $store) {
                $this->_prepareStoreConfig($store->getId());
                if ($this->_isEnabled()) {
                    $products = $this->_exportProducts();
                    $categories = $this->_exportCategories();
//                    $customers[$this->_storeConfig['language']] = $this->_exportCustomers();
                    $tags = $this->_exportTags();
//                    $transactions[$this->_storeConfig['language']] = $this->_exportTransactions();
                }
            }
            if ($this->_isEnabled()) {
                $customers = $this->_exportCustomers();
                $transactions = $this->_exportTransactions();
            }
        }

//        $this->_getAllAttributesValues();

        return array(
            'products' => $products,
            'categories' => $categories,
            'customers' => $customers,
            'tags' => $tags,
            'transactions' => $transactions
        );
    }

    /**
     * @description Get configs for store by storeId
     * @param int $storeId
     * @return void
     */
    protected function _prepareStoreConfig($storeId)
    {
        $this->_storeId = $storeId;
        $this->_storeConfig = Mage::app()->getStore($this->_storeId)->getConfig('boxalinoexporter/export_data');
        if (!Mage::helper('boxalinoexporter')->isAvailableLanguages($this->_storeConfig['language'])) {
            Mage::throwException(Mage::helper('boxalinoexporter')->__('Language "' . $this->_storeConfig['language'] . '" is not available.'));
        }
        $this->_mergeAllAttributes();
        $this->_getAllAttributesValues();
    }

    /**
     * @description Merge default attributes with attributes added by user
     * @return void
     */
    protected function _mergeAllAttributes()
    {
        $this->_listOfAttributes = Mage::helper('boxalinoexporter')->defaultAttributes();
        if (isset($this->_storeConfig['additional_attributes']) && $this->_storeConfig['additional_attributes'] != '') {
            $fields = explode(',', $this->_storeConfig['additional_attributes']);
            foreach ($fields as $field) {
                $this->_listOfAttributes[] = $field;
            }
        }
    }

    /**
     * @description Get labels for all Attributes where is optionsId = optionValue
     * @return void
     */
    protected function _getAllAttributesValues()
    {
        $attributesWithId = Mage::helper('boxalinoexporter')->attributesWithIds();
        foreach($this->_listOfAttributes as $attribute) {
            if(array_search($attribute, $attributesWithId) == true) {
                $options = Mage::getModel('eav/config')->getAttribute('catalog_product', $attribute)->setStoreId($this->_storeId)->getSource()->getAllOptions();
                foreach($options as $option) {
                    if(!empty($option['value'])) {
                        $this->_attributesValues[$this->_storeId][$attribute][$option['value']] = $option['label'];

                        $value = intval($option['value']);
                        $name = 'value_' . $this->_storeConfig['language'];

                        if(isset($this->_attributesValuesByName[$attribute])){

                            if(isset($this->_attributesValuesByName[$attribute][$value])){
                                $this->_attributesValuesByName[$attribute][$value][$name] = strtolower($option['label']);
                            } else{
                                $this->_attributesValuesByName[$attribute][$value] = array($attribute . '_id' => $value, $name => strtolower($option['label']));
                            }

                        } else{
                            $this->_attributesValuesByName[$attribute] = array($value => array($attribute . '_id' => $value, $name => strtolower($option['label'])));
                        }

                    }
                }
            }
        }
    }

    /**
     *
     */

    /**
     * @description check if export is enabled for website
     * @return bool
     */
    protected function _isEnabled()
    {
        if ($this->_storeConfig['enable_module']) {
            return true;
        }

        return false;
    }

    /**
     * @description Get stock of all products
     * @return array List of stocks qty for products
     */
    protected function _getProductsStockQty()
    {
        if(empty($this->_productsStockQty)) {
            $products = Mage::getModel('cataloginventory/stock_item')->getCollection();
            foreach ($products as $product) {
                $this->_productsStockQty[$product->getProductId()] = $product->getQty();
            }
        }

        return $this->_productsStockQty;
    }

    /**
     * @description Get list of products with their tags
     * @return object List of products with their tags array('product_id' => 'tag_id');
     */
    protected function _getProductTags()
    {
        if(empty($this->_allProductTags)) {
            $tags = Mage::getResourceModel('tag/product_collection')->getData();
            foreach ($tags as $tag) {
                $this->_allProductTags[$tag['entity_id']] = $tag['tag_id'];
            }
        }

        return $this->_allProductTags;
    }

    /**
     * @description Preparing products to export
     * @return array Products
     *
     * @TODO: Here you should preparing Products to send
     */
    protected function _exportProducts()
    {
        $products = $this->_getStoreProducts();
        $attrs = $this->_listOfAttributes;
        $attrs[] = 'type_id';
//        var_dump($attrs);

        $helper = Mage::helper('boxalinoexporter');

//        if(!isset($this->_transformedProducts['products'][0])){
//            $this->_transformedProducts['products'][0] = array();
//        }

        foreach($products as $product) {

            $id = $product->getId();

//            if($id != 231) continue;
//            $productParam = array('entity_id' => $id);
//
//            //Add attributes
//            foreach($attrs as $attr){
//                $productParam[$attr] = $product->$attr;
//            }
//
//            //Add categories
//            $productParam['categories'] = $product->getCategoryIds();
//
//            //Add parent
//            $productParam['parent_id'] = $helper->getParentId($id);
//
//            $return[$id] = $productParam;

//            if($product->getName() == 'Bowery Chino Pants')
//            {
////                var_dump($product->getData());
////                var_dump($product->getCategoryIds());
////                $parentIds = Mage::getResourceSingleton('catalog/product_type_configurable')
////                    ->getParentIdsByChild($product->getId());
////                var_dump($helper->getParentId($product->getId()));
////            echo "xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx\n";
//            }

            $productParam = array();
            $productParamMtM = array();

            foreach($attrs as $attr){
                if(isset($this->_attributesValuesByName[$attr])){
                    $productParamMtM[$attr] = $product->$attr;
                    continue;
                }


                switch($attr){
                    case 'description':
                    case 'short_description':
                    case 'name':
                        $productParam[$attr . '_' . $this->_storeConfig['language']] = trim($product->$attr);
//                        $this->_transformedProducts['products'][0][$attr . '_' . $this->_storeConfig['language']] = $attr . '_' . $this->_storeConfig['language'];
                        break;
                    case 'category_ids':
                        break;
                    default:
                        $productParam[$attr] = trim($product->$attr);
//                        $this->_transformedProducts['products'][0][$attr] = $attr;
                        break;
                }

            }

            //Add categories
            $productParamMtM['categories'] = $product->getCategoryIds();

            if(!isset($this->_transformedProducts['products'][$id])){
                $productParam['entity_id'] = $id;
                $productParam['parent_id'] = $helper->getParentId($id);
                $this->_transformedProducts['products'][0]['entity_id'] = 'entity_id';
                $this->_transformedProducts['products'][0]['parent_id'] = 'parent_id';
                $this->_transformedProducts['products'][$id] = $productParam;
                $this->_transformedProducts['productsMtM'][$id] = $productParamMtM;
            } else{
                $this->_transformedProducts['products'][$id] = array_merge($this->_transformedProducts['products'][$id], $productParam);
//                $this->_transformedProducts['productsMtM'][$id] = array_merge($this->_transformedProducts['productsMtM'][$id], $productParamMtM);
            }

            ksort($this->_transformedProducts['products'][$id]);

            if($id == 231){
                var_dump(array_unique($this->_transformedProducts['products'][0]));
                var_dump($this->_transformedProducts['productsMtM'][$id]);
            }

        }
//        die();

//        echo '<pre>';
//        print_r($this->_transformedProducts);
//        echo '</pre>';

//        ksort($this->_transformedProducts['products'][0]);

        return  $this->_transformedProducts;
    }

    /**
     * @description Preparing categories to export
     * @return array Cateroies
     *
     * @TODO: Here you should preparing Categories to send
     */
    protected function _exportCategories()
    {
        if ($this->_storeConfig['export_categories']) {
            $categories = $this->_getCategories();
            foreach ($categories as $category) {
                if(isset($this->_transformedCategories[$category->getId()])) {
                    $this->_transformedCategories[$category->getId()]['name_'.$this->_storeConfig['language']] = $category->getName();
                } else {
                    $parentId = $category->getParentId() != 0 ? $category->getParentId() : null;
                    $this->_transformedCategories[$category->getId()] = array('category_id' => $category->getId(), 'parent_id' => $parentId, 'name_'.$this->_storeConfig['language'] => $category->getName());
                }
            }

            return $this->_transformedCategories;
        }


        return null;

    }

    /**
     * @description Preparing customers to export
     * @return array Customers
     *
     * @TODO: Here you should preparing Customers to send
     */
    protected function _exportCustomers()
    {

        if ($this->_storeConfig['export_customers']) {

            $return = array();
            $collection = Mage::getModel('directory/country')->getCollection();

            if($this->_countries == null){
                foreach ($collection as $country)
                {
                    $this->_countries[$country->getId()] = $country->getName();
                }
            }

            $customers = $this->_getCustomers();

            foreach($customers as $customer){

                $billing = $customer->getPrimaryBillingAddress();

                switch($customer->getGender()){
                    case 1:
                        $gender = 'male';
                        break;
                    case 2:
                        $gender = 'female';
                        break;
                    default:
                        $gender = null;
                        break;
                }

                $return[] = array(
                    'customer_id' => $customer->getId(),
                    'gender' => $gender,
                    'dob' => $customer->getDob(),
                    'country' => $this->_countries[$billing->getCountryId()],
                    'zip' => $billing->getPostcode()
                );

            }

            return $return;
        }

        return null;
    }

    /**
     * @description Preparing tags to export
     * @return array Tags
     *
     * @TODO: Here you should preparing Tags to send
     */
    protected function _exportTags()
    {

        if ($this->_storeConfig['export_tags']) {

            $product_tags = $this->_getProductTags();
            $tags = Mage::helper('boxalinoexporter')->getAllTags();
            foreach ($product_tags as $product_id => $tag_id) {

                if(isset($this->_transformedTags[$tag_id])){
                    $this->_transformedTags[$tag_id]['name_' . $this->_storeConfig['language']] = $tags[$tag_id];
                } else{
                    $this->_transformedTags[$tag_id] = array('tag_id' => $tag_id, 'name_' . $this->_storeConfig['language'] => $tags[$tag_id]);
                }
            }

            return $this->_transformedTags;
        }

        return null;
    }

    /**
     * @description Preparing transactions to export
     * @return array Transaction
     *
     * @TODO: Here you should preparing Transactions to send
     * @TODO: Add confirm/shipping date
     */
    protected function _exportTransactions()
    {

        if ($this->_storeConfig['export_transactions']) {

            $return = array();

            $transactions = $this->_getTransactions();

            foreach($transactions as $transaction){
//                if($transaction->getIncrementId() != '145000009') continue;

//                if($transaction->getIncrementId() == '145000008'){
//                    echo '<pre>';
//                    print_r($transaction->getData());
//                    foreach($transaction->getShipmentsCollection() as $shipment){
//                        /** @var $shipment Mage_Sales_Model_Order_Shipment */
//                        echo $shipment->getCreatedAt();
//                    }
//                    echo '</pre>';
//                    foreach($transaction->getAllItems() as $product){
////                        var_dump($product->getData());
//                    }
//                }

                $configurable = array();

                $products = ($transaction->getAllItems());

                foreach($products as $product){

                    //is configurable
                    if($product->getParentItemId() == null && $product->getProductType() != 'simple' ){
                        $configurable[$product->getId()] = $product;
                        continue;
                    }

                    //is configurable - simple product
                    if(intval($product->getPrice()) == 0){
                        $pid = $configurable[$product->getParentItemId()];
                        $product->setOriginalPrice($pid->getOriginalPrice());
                        $product->setPrice($pid->getPrice());
                    }

                    $status = 0; // 0 - pending, 1 - confirmed, 2 - shipping

                    if($transaction->getStatus() == 'canceled'){
                        continue;
                    }

                    if($transaction->getUpdatedAt() != $transaction->getCreatedAt()){

                        switch($transaction->getStatus()){
                            case 'canceled':
                                continue;
                                break;
                            case 'processing':
                                $status = 1;
                                break;
                            case 'complete':
                                $status = 2;
                                break;
                        }
                    }

                    $return[] = array(
//                        'order' => $transaction->getIncrementId(),
                        'product_id' => $product->getProductId(),
                        'customer_id' => $transaction->getCustomerId(),
                        'price' => $product->getOriginalPrice(),
                        'discounted_price' => $product->getPrice(),
                        'total_order_value' => ($transaction->getBaseSubtotal() + $transaction->getShippingAmount()),
                        'shipping_costs' => $transaction->getShippingAmount(),
                        'order_date' => $transaction->getCreatedAt(),
                        'confirmation_date' => $status==1?$transaction->getUpdatedAt():null,
                        'shipping_date' => $status==2?$transaction->getUpdatedAt():null,
                        'status' => $transaction->getStatus()
                    );

                }
            }

            return $return;
        }

        return null;
    }

    /**
     * @description Preparing files to send
     *
     * @TODO: Split it for preparing CSV and XML
     */
    protected function prepareFiles($products, $categories = null, $customers = null, $tags = null, $transactions = null)
    {


//        var_dump($products);
//        die();

        //Prepare attributes
        $csvFiles = array();
        mkdir("/tmp/boxalino");
        $csv = new Varien_File_Csv();
        $csv->setDelimiter('|');
        $csv->setEnclosure("");

        //save attributes
        foreach($this->_attributesValuesByName as $attrName => $attrValues){

            $file = $attrName . '.csv';

            $csvdata = array_merge(array(array_keys(end($attrValues))), $attrValues);

            $csvFiles[] = $file;
            $csv->saveData('/tmp/boxalino/' . $file, $csvdata);

        }

        //save categories
        $file = 'categories.csv';
        $csvdata = array_merge(array(array_keys(end($categories))), $categories);
        $csvFiles[] = $file;
        $csv->saveData('/tmp/boxalino/' . $file, $csvdata);

        //save tags
        $file = 'tags.csv';
        $csvdata = array_merge(array(array_keys(end($tags))), $tags);
        $csvFiles[] = $file;
        $csv->saveData('/tmp/boxalino/' . $file, $csvdata);

        //save transactions
        $file = 'transactions.csv';
        $csvdata = array_merge(array(array_keys(end($transactions))), $transactions);
        $csvFiles[] = $file;
        $csv->saveData('/tmp/boxalino/' . $file, $csvdata);

        //save customers
        $file = 'customers.csv';
        $csvdata = array_merge(array(array_keys(end($customers))), $customers);
        $csvFiles[] = $file;
        $csv->saveData('/tmp/boxalino/' . $file, $csvdata);

        //products
        $file = 'products.csv';

//        $productsTmp = array();
//        $fields = $products['products'][0];

//        $csvdata = array($fields);
//
//        unset($products['products'][0]);
//        foreach($products['products'] as $product){
//
//            $change = 0;
//
//            foreach($fields as $field){
//                if(!isset($product[$field])){
//                    echo $field . ' ';
//                    $product[$field] = "";
//                    $change = 1;
//                }
//            }
//
//            if($change){
//                ksort($product);
//            }
//
//            $csvdata[] = $product;
//        }

        $csvdata = $products['products'];
        $csvFiles[] = $file;
        $csv->saveData('/tmp/boxalino/' . $file, $csvdata);

        //delete tmp files
        foreach($csvFiles as $f){
            //@unlink('/tmp/' . $f);
        }

        //

    }

    /**
     * @description Closing sync to export
     * @TODO: merging everything and sending
     */
    protected function _closeExport()
    {
        /**
         * @TODO: I think that here should be sending to SOLR.
         */
        switch($this->_getIndexType()) {
            case 'full':
                $fullUrl = Mage::helper('boxalinoexporter')->getDataSyncUrl();
                break;
            case 'delta':
                $deltaUrl = Mage::helper('boxalinoexporter')->getDeltaSyncUrl();
                break;
            default:
                Mage::throwException(Mage::helper('boxalinoexporter')->__('Wrong Reindex. Please try with Full Data sync or Delta sync.'));
                break;
        }
    }
}