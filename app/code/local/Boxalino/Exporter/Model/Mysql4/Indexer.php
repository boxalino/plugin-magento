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

    protected $_categoryParent = array();

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
        $this->_websiteExport();
        $this->_closeExport();
        die();
    }

    function getmicrotime($mt, $string = true){
        list($usec, $sec) = explode(" ", $mt);

        if($string)
            return date('Y-m-d H:i:s', (float)$usec + (float)$sec) . '.' . $usec;
        else
            return ((float)$usec + (float)$sec);
    }

    /**
     * @description Declare what code have to do for Website scope
     * @return void
     */
    protected function _websiteExport()
    {
        foreach (Mage::app()->getWebsites() as $website) {
            $t1 = microtime();
            $data = $this->_storeExport($website);
            $t2 = microtime();
            $zip = $this->prepareFiles($website, $data['products'], $data['categories'], $data['customers'], $data['tags'], $data['transactions']);
            $t3 = microtime();
            $this->sendFile($zip);
            $t4 = microtime();
            $this->_transformedCategories = array();
            $this->_transformedTags = array();
            $this->_transformedProducts = array();
            $this->_categoryParent = array();

            echo "<br><br>Start: " . $this->getmicrotime($t1) . " <br><br>";
            echo "Export: " . $this->getmicrotime($t2) . " <br>";
            echo "Delta: " . ($this->getmicrotime($t2, false) - $this->getmicrotime($t1, false)) . " <br><br>";
            echo "Files: " . $this->getmicrotime($t3) . " <br>";
            echo "Delta: " . ($this->getmicrotime($t3, false) - $this->getmicrotime($t2, false)) . " <br><br>";
            echo "All:   " . ($this->getmicrotime($t3, false) - $this->getmicrotime($t1, false)) . " <br><br>";
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
                    $categories = $this->_exportCategories();
                    $tags = $this->_exportTags();
                    $products = $this->_exportProducts();
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
//            $productParamMtM = array();

            foreach($attrs as $attr){
                if(isset($this->_attributesValuesByName[$attr])){
//                    $productParamMtM[$attr] = $product->$attr;
                    $val = $product->$attr;

                    if($val == null){
                        continue;
                    }

                    if(isset($this->_transformedProducts['productsMtM'][$attr])){
                        $this->_transformedProducts['productsMtM'][$attr][] = array('entity_id' => $id, $attr . '_id' => $val);
                    } else{
                        $this->_transformedProducts['productsMtM'][$attr] = array();
                        $this->_transformedProducts['productsMtM'][$attr][] = array('entity_id' => $id, $attr . '_id' => $val);
                    }
//                    $this->_transformedProducts['productsMtM'][$id]
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

            if(!isset($this->_transformedProducts['products'][$id])){
                $productParam['entity_id'] = $id;
                $productParam['parent_id'] = $helper->getParentId($id);
//                $this->_transformedProducts['products'][0]['entity_id'] = 'entity_id';
//                $this->_transformedProducts['products'][0]['parent_id'] = 'parent_id';
                $this->_transformedProducts['products'][$id] = $productParam;
//                $this->_transformedProducts['productsMtM'][$id] = $productParamMtM;

                //Add categories
//                $productParamMtM['categories'] = $product->getCategoryIds();
                foreach($product->getCategoryIds() as $cat){
                    while($cat != null){
                        $this->_transformedProducts['productsMtM']['categories'][] = array('entity_id' => $id, 'category_id' => $cat);
                        $cat = $this->_transformedCategories[$cat]['parent_id'];
                    }
//                    var_dump(array($id, $cat, $this->_transformedCategories[$cat]['parent_id']));
                }
//                var_dump(array($product->getCategoryIds(), $product->getTypeId(), $product->getId()));

            } else{
                $this->_transformedProducts['products'][$id] = array_merge($this->_transformedProducts['products'][$id], $productParam);
//                $this->_transformedProducts['productsMtM'][$id] = array_merge($this->_transformedProducts['productsMtM'][$id], $productParamMtM);
            }

            ksort($this->_transformedProducts['products'][$id]);

        }

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
                    $this->_transformedCategories[$category->getId()]['value_'.$this->_storeConfig['language']] = $category->getName();
                } else {
                    $parentId = $category->getParentId() != 0 ? $category->getParentId() : null;
                    $this->_transformedCategories[$category->getId()] = array('category_id' => $category->getId(), 'parent_id' => $parentId, 'value_'.$this->_storeConfig['language'] => $category->getName());
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

            $tags = Mage::helper('boxalinoexporter')->getAllTags();

            foreach($tags as $id => $tag){
                if(isset($this->_transformedTags[$id])){
                    $this->_transformedTags[$id]['value_' . $this->_storeConfig['language']] = $tag;
                } else{
                    $this->_transformedTags[$id] = array('tag_id' => $id, 'value_' . $this->_storeConfig['language'] => $tag);
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
    protected function prepareFiles($website, $products, $categories = null, $customers = null, $tags = null, $transactions = null)
    {


//        var_dump($products);
//        die();

        //Prepare attributes
        $csvFiles = array();
        mkdir("/tmp/boxalino");
        $csv = new Varien_File_Csv();
        $csv->setDelimiter('|');
        $csv->setEnclosure('"');

        //create csv

        //save attributes
        foreach($this->_attributesValuesByName as $attrName => $attrValues){

//            $file = $attrName . '.csv';
//
//            $csvdata = array_merge(array(array_keys(end($attrValues))), $attrValues);
//
//            $csvFiles[] = $file;
//            $csv->saveData('/tmp/boxalino/' . $file, $csvdata);
            $csvFiles[] = $this->createCsv($attrName, $attrValues, $csv);

        }

        //save categories
        if($categories != null){
//            $file = 'categories.csv';
//            $csvdata = array_merge(array(array_keys(end($categories))), $categories);
//            $csvFiles[] = $file;
//            $csv->saveData('/tmp/boxalino/' . $file, $csvdata);
            $csvFiles[] = $this->createCsv('categories', $categories, $csv);
        }

        //save tags
        if($tags != null){
//            $file = 'tags.csv';
//            $csvdata = array_merge(array(array_keys(end($tags))), $tags);
//            $csvFiles[] = $file;
//            $csv->saveData('/tmp/boxalino/' . $file, $csvdata);
            $csvFiles[] = $this->createCsv('tags', $tags, $csv);

//            $file = 'product_tag.csv';
            foreach( $this->_getProductTags() as $product_id => $tag_id){
                $csvdata[] = array('entity_id' => $product_id, 'tag_id' => $tag_id);
            }
//            $csvFiles[] = $file;
//            $csv->saveData('/tmp/boxalino/' . $file, $csvdata);

            $csvFiles[] = $this->createCsv('product_tag', $csvdata, $csv);

        }

        //save transactions
        if($transactions != null){
//            $file = 'transactions.csv';
//            $csvdata = array_merge(array(array_keys(end($transactions))), $transactions);
//            $csvFiles[] = $file;
//            $csv->saveData('/tmp/boxalino/' . $file, $csvdata);
            $csvFiles[] = $this->createCsv('transactions', $transactions, $csv);
        }

        //save customers
        if($customers != null){
//            $file = 'customers.csv';
//            $csvdata = array_merge(array(array_keys(end($customers))), $customers);
//            $csvFiles[] = $file;
//            $csv->saveData('/tmp/boxalino/' . $file, $csvdata);
            $csvFiles[] = $this->createCsv('customers', $customers, $csv);
        }

        //products
//        $file = 'products.csv';
//        $csvdata = $products['products'];
//        $csvFiles[] = $file;
//        $csv->saveData('/tmp/boxalino/' . $file, $csvdata);
        $csvFiles[] = $this->createCsv('products', $products['products'], $csv);

        //products & attributes
        foreach($products['productsMtM'] as $key => $val){
//            $file = "product_$key.csv";
//
            $csvdata = array_merge(array(array('product_id', $key . '_id')), $val);
//
//            $csvFiles[] = $file;
//            $csv->saveData('/tmp/boxalino/' . $file, $csvdata);
            $csvFiles[] = $this->createCsv("product_$key", $val, $csv);
        }

        //Create zip file
        $exportFile = '/tmp/boxalino/' . 'magento_' . $website->getId() . '_' . md5(uniqid($website->getName()));
        $fileZip = $exportFile . '.zip';

        //Create zip
        $zip = new ZipArchive();
        if ($zip->open($fileZip, ZIPARCHIVE::CREATE) ){

            foreach($csvFiles as $f){
                if(!$zip->addFile('/tmp/boxalino/' . $f, $f)){
                    throw new Exception('Synchronization failure. Please try again.');
                }
            }

            if(!$zip->close()){
                throw new Exception('Synchronization failure. Please try again.');
            }

        } else{
            throw new Exception('Synchronization failure. Please try again.');
        }

        //Create xml
        $fileXML = $exportFile . '.xml';

        return $exportFile;

        //

    }

    protected function createXML(){

    }


    /**
     * @param $zip
     */
    protected function sendFile($zip){

    }

    /**
     * @param $name
     * @param $data
     * @param $csv
     * @return string
     */
    protected function createCsv($name, $data, $csv){
        $file = $name . '.csv';
        $csvdata = array_merge(array(array_keys(end($data))), $data);
        $csv->saveData('/tmp/boxalino/' . $file, $csvdata);

        return $file;
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