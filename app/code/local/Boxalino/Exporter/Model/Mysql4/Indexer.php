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

    protected $_availableLanguages = array();

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
            $this->_availableLanguages = array();

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
                    $this->_availableLanguages[] = $this->_storeConfig['language'];
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
//        $attrs[] = 'type_id';
//        var_dump($attrs);

        $helper = Mage::helper('boxalinoexporter');

        foreach($products as $product) {

            $id = $product->getId();

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
                        'order_id' => $transaction->getIncrementId(),
                        'entity_id' => $product->getProductId(),
                        'customer_id' => $transaction->getCustomerId(),
                        'price' => $product->getOriginalPrice(),
                        'discounted_price' => $product->getPrice(),
                        'quantity' => $product->getQtyOrdered(),
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

        //Prepare attributes
        $csvFiles = array();
        mkdir("/tmp/boxalino");
        $csv = new Varien_File_Csv();
        $csv->setDelimiter('|');
        $csv->setEnclosure('"');

        //create csv
        //save attributes
        foreach($this->_attributesValuesByName as $attrName => $attrValues){
            $csvFiles[] = $this->createCsv($attrName, $attrValues, $csv);

        }

        //save categories
        if($categories != null){
            $csvFiles[] = $this->createCsv('categories', $categories, $csv);
        }

        //save tags
        if($tags != null){
            $csvFiles[] = $this->createCsv('tag', $tags, $csv);

            foreach( $this->_getProductTags() as $product_id => $tag_id){
                $csvdata[] = array('entity_id' => $product_id, 'tag_id' => $tag_id);
            }

            $csvFiles[] = $this->createCsv('product_tag', $csvdata, $csv);

        }

        //save transactions
        if($transactions != null){
            $csvFiles[] = $this->createCsv('transactions', $transactions, $csv);
        }

        //save customers
        if($customers != null){
            $csvFiles[] = $this->createCsv('customers', $customers, $csv);
        }

        //products
        $csvFiles[] = $this->createCsv('products', $products['products'], $csv);

        //products & attributes
        foreach($products['productsMtM'] as $key => $val){
            $csvFiles[] = $this->createCsv("product_$key", $val, $csv);
        }
        //csv done

        //Create name for file
        $exportFile = '/tmp/boxalino/' . 'magento_' . $website->getId() . '_' . md5(uniqid($website->getName()));

        //Create xml
        try{
            $this->createXML($exportFile . '.xml');
        } catch(Exception $e){
            var_dump($e->getMessage());
        }


        //Create zip

        try{
            $this->createZip($exportFile . '.zip', $csvFiles, $exportFile . '.xml');
        } catch(Exception $e){
            var_dump($e->getMessage());
        }


        return $exportFile;

        //

    }

    protected function createXML($name){

        $helper = Mage::helper('boxalinoexporter');

        $xml = new SimpleXMLElement('<root/>');

        $languages = $xml->addChild('languages');
        $containers = $xml->addChild('containers');

        //languages
        foreach($this->_availableLanguages as $lang){
            $language = $languages->addChild('language');
            $language->addAttribute('id', $lang);
        }

        //customers
        $customerString = <<<XML
        <container id="customers" type="customers">
            <sources>
                <source type="item_data_file" id="customer_vals">
                    <file value="customers.csv"/>
                    <itemIdColumn value="customer_id"/>
                    <format value="$helper->XML_FORMAT"/>
                    <encoding value="$helper->XML_ENCODE"/>
                    <delimiter value="$helper->XML_DELIMITER"/>
                    <enclosure value="$helper->XML_ENCLOSURE"/>
                    <escape value="$helper->XML_ESCAPE"/>
                    <lineSeparator value="$helper->XML_NEWLINE"/>
                </source>
            </sources>
            <properties>
                <property id="id" type="id">
                    <transform>
                        <logic source="customer_vals" type="direct">
                            <field column="customer_id"/>
                        </logic>
                    </transform>
                    <params/>
                </property>
                <property id="gender" type="string">
                    <transform>
                        <logic source="customer_vals" type="direct">
                            <field column="gender"/>
                        </logic>
                    </transform>
                    <params/>
                </property>
                <property id="dob" type="date">
                    <transform>
                        <logic source="customer_vals" type="direct">
                            <field column="dob"/>
                        </logic>
                    </transform>
                    <params/>
                </property>
                <property id="country" type="string">
                    <transform>
                        <logic source="customer_vals" type="direct">
                            <field column="country"/>
                        </logic>
                    </transform>
                    <params/>
                </property>
                <property id="zip" type="string">
                    <transform>
                        <logic source="customer_vals" type="direct">
                            <field column="zip"/>
                        </logic>
                    </transform>
                    <params/>
                </property>
            </properties>
        </container>
XML;

        //transaction
        $transactionString = <<<XML
        <container id="transactions" type="transactions">
            <sources>
                <source type="transactions" id="transactions">
                    <file value="transactions.csv"/>
                    <orderIdColumn value="order_id"/>
                    <customerIdColumn value="customer_id" customer_property_id="customer_id"/>
                    <productIdColumn value="entity_id" product_property_id="entity_id"/>
                    <productListPriceColumn value="price"/>
                    <productDiscountedPriceColumn value="discounted_price"/>
                    <totalOrderValueColumn value="total_order_value"/>
                    <shippingCostsColumn value="shipping_costs"/>
                    <orderReceptionDateColumn value="order_date"/>
                    <orderReceptionDateColumn value="confirmation_date"/>
                    <orderShippingDateColumn value="shipping_date"/>
                    <orderStatusColumn value="status"/>
                    <format value="$helper->XML_FORMAT"/>
                    <encoding value="$helper->XML_ENCODE"/>
                    <delimiter value="$helper->XML_DELIMITER"/>
                    <enclosure value="$helper->XML_ENCLOSURE"/>
                    <escape value="$helper->XML_ESCAPE"/>
                    <lineSeparator value="$helper->XML_NEWLINE"/>
                </source>
            </sources>
        </container>
XML;

        //product
        $products = $containers->addChild('container');
        $products->addAttribute('id', 'products');
        $products->addAttribute('type', 'products');

        $sources = $products->addChild('sources');
        $attr = array_keys($this->_attributesValuesByName);
        $attr[] = 'tag';
        foreach($attr as $attr){

            //attribute
            $source = $sources->addChild('source');
            $source->addAttribute('id', $attr);
            $source->addAttribute('type', 'resource');

            $source->addChild('file')->addAttribute('value', $attr . '.csv');
            $source->addChild('referenceIdColumn')->addAttribute('value', $attr . '_id');

            $labelColumns = $source->addChild('labelColumns');
            foreach($this->_availableLanguages as $lang) {
                $label = $labelColumns->addChild('language');
                $label->addAttribute('name', $lang);
                $label->addAttribute('value', 'value_'. $lang);
            }

            $this->sxml_append_options($source);

            unset($source);
            //product & attribute
            $source = $sources->addChild('source');
            $source->addAttribute('id', 'item_' . $attr);
            $source->addAttribute('type', 'item_data_file');

            $source->addChild('file')->addAttribute('value', 'product_' . $attr . '.csv');
            $source->addChild('itemIdColumn')->addAttribute('value', 'entity_id');

            $this->sxml_append_options($source);
        }

        #########################################################################
        //categories
        $sourceCategory = $sources->addChild('source');
        $sourceCategory->addAttribute('id', 'categories');
        $sourceCategory->addAttribute('type', 'hierarchical');

        $sourceCategory->addChild('file')->addAttribute('value', 'categories.csv');
        $sourceCategory->addChild('referenceIdColumn')->addAttribute('value', 'entity_id');
        $sourceCategory->addChild('parentIdColumn')->addAttribute('value', 'parent_id');

        $labelColumns = $sourceCategory->addChild('labelColumns');
        foreach($this->_availableLanguages as $lang) {
            $label = $labelColumns->addChild('language');
            $label->addAttribute('name', $lang);
            $label->addAttribute('value', 'value_'. $lang);
        }

        $this->sxml_append_options($sourceCategory);

        //categories & products
        $source = $sources->addChild('source');
        $source->addAttribute('id', 'item_categories');
        $source->addAttribute('type', 'item_data_file');

        $source->addChild('file')->addAttribute('value', 'product_categories.csv');
        $source->addChild('itemIdColumn')->addAttribute('value', 'entity_id');

        $this->sxml_append_options($source);
        #########################################################################

        //product source
        #########################################################################
        $source = $sources->addChild('source');
        $source->addAttribute('id', 'items');
        $source->addAttribute('type', 'item_data_file');

        $source->addChild('file')->addAttribute('value', 'products.csv');
        $source->addChild('itemIdColumn')->addAttribute('value', 'entity_id');

        $this->sxml_append_options($source);
        #########################################################################

        //property
        $properties = $products->addChild('properties');
        $props = $this->prepareProperties();

        foreach($props as $prop){

            $property = $properties->addChild('property');
            $property->addAttribute('id', $prop['id']);
            $property->addAttribute('type', $prop['ptype']);

            $transform = $property->addChild('transform');
            $logic = $transform->addChild('logic');
            $ls = $prop['name']==null?'items':'item_'.$prop['name'];
            $logic->addAttribute('source', $ls);
            $logic->addAttribute('type', $prop['type']);
            if($prop['has_lang'] == true){
                foreach($this->_availableLanguages as $lang) {
                    $field = $logic->addChild('field');
                    $field->addAttribute('column', $prop['field'] . '_' . $lang);
                    $field->addAttribute('language', $lang);
                }
            } else{
                $logic->addChild('field')->addAttribute('column', $prop['field']);
            }


            $params = $property->addChild('params');
            $params->addChild('referenceSource')->addAttribute('value', $prop['reference']);

        }


        if ($this->_storeConfig['export_customers']){
            $customer = simplexml_load_string($customerString);
            $this->sxml_append($containers, $customer);
        }

        if ($this->_storeConfig['export_transactions']){
            $transaction = simplexml_load_string($transactionString);
            $this->sxml_append($containers, $transaction);
        }

        $dom = new DOMDocument("1.0");
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xml->asXML());
        $dom->save($name);

    }

    /**
     * @param SimpleXMLElement $to
     * @param SimpleXMLElement $from
     */
    function sxml_append(SimpleXMLElement $to, SimpleXMLElement $from) {
        $toDom = dom_import_simplexml($to);
        $fromDom = dom_import_simplexml($from);
        $toDom->appendChild($toDom->ownerDocument->importNode($fromDom, true));
    }

    /**
     * @param SimpleXMLElement $xml
     */
    function sxml_append_options(SimpleXMLElement &$xml){

        $helper = Mage::helper('boxalinoexporter');

        $xml->addChild('format')->addAttribute('value', $helper->XML_FORMAT);
        $xml->addChild('encoding')->addAttribute('value',$helper->XML_ENCODE);
        $xml->addChild('delimiter')->addAttribute('value',$helper->XML_DELIMITER);
        $xml->addChild('enclosure')->addAttribute('value',$helper->XML_ENCLOSURE);
        $xml->addChild('escape')->addAttribute('value',$helper->XML_ESCAPE);
        $xml->addChild('lineSeparator')->addAttribute('value',$helper->XML_NEWLINE);
    }

    /**
     * @return array
     */
    function prepareProperties(){

        $properties = array();
        foreach($this->_listOfAttributes as $attr){

            $ptype = 'string';

            switch($attr){
                case 'name':
                    $ptype = 'title';
                    break;
                case 'description':
                    $ptype = 'body';
                    break;
                case 'price':
                    $ptype = 'price';
                    break;
                case 'special_price':
                    $ptype = 'discounted_price';
                    break;
                case 'entity_id':
                    $ptype = 'id';
                    break;
                case 'short_description':
                    $ptype = 'text';
                    break;
                case 'weight':
                case 'width':
                case 'height':
                case 'length':
                case 'parent_id':
                    $ptype = 'number';
                    break;

            }

            if(isset($this->_attributesValuesByName[$attr])){
                $properties[] = array(
                    'id' => $attr,
                    'name' => $attr,
                    'ptype' => 'text',
                    'type' => 'reference',
                    'field' => $attr . '_id',
                    'has_lang' => false,
                    'reference' => $attr
                );
            } elseif($attr == 'category_ids'){
                continue;
            } else{
                $ref = null;
                $type = 'direct';
                $field = $attr;
                switch($attr){
                    case 'description':
                    case 'short_description':
                    case 'name':
                        $lang = true;
                        break;
                    case 'category_ids':
                        continue;
                        break;
                    case 'parent':
                        $type = 'reference';
                        $reference = 'items';
                        $field = 'entity_id';
                        break;
                    default:
                        $lang = false;
                        break;
                }
                $properties[] = array(
                    'id' => $attr,
                    'name' => null,
                    'ptype' => $ptype,
                    'type' => $type,
                    'field' => $field,
                    'has_lang' => $lang,
                    'reference' => $ref
                );
            }
        }

        //tag
        $properties[] = array(
            'id' => 'tag',
            'name' => 'tag',
            'ptype' => 'text',
            'type' => 'reference',
            'field' => 'tag_id',
            'has_lang' => false,
            'reference' => 'tag'
        );

        //categories
        $properties[] = array(
            'id' => 'category',
            'name' => 'categories',
            'ptype' => 'hierarchical',
            'type' => 'hierarchical',
            'field' => 'category_id',
            'has_lang' => false,
            'reference' => 'categories'
        );

        return $properties;

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
     * @param $name
     * @param $csvFiles
     */
    protected function createZip($name, $csvFiles, $xml){

        $zip = new ZipArchive();
        if ($zip->open($name, ZIPARCHIVE::CREATE) ){

            foreach($csvFiles as $f){
                if(!$zip->addFile('/tmp/boxalino/' . $f, $f)){
                    throw new Exception('Synchronization failure. Please try again.');
                }
            }

            if(!$zip->addFile($xml, 'properties.xml')){
                throw new Exception('Synchronization failure. Please try again.');
            }

            if(!$zip->close()){
                throw new Exception('Synchronization failure. Please try again.');
            }

        } else{
            throw new Exception('Synchronization failure. Please try again.');
        }
    }

    /**
     * @param $zip
     */
    protected function sendFile($zip){

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