<?php

//ini_set('memory_limit', '-1');

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

    /** @var array All tags of products */
    protected $_allProductTags = array();

    protected $_transformedCategories = array();

    protected $_transformedTags = array();

    protected $_transformedProducts = array();

    protected $_categoryParent = array();

    protected $_countries = null;

    protected $_availableLanguages = array();

    protected $_attributesValuesByName = array();

    protected $_tmp = array();

    protected $_files = array();

    protected $_count = 0;

    protected $_attrProdCount = array();

    /** @var int Actually used storeId */
    protected $_storeId = 0;

    protected $_dir = '/tmp/boxalino';

    protected $group = null;

    /**
     * @description Start of apocalypse
     */
    public function reindexAll()
    {

        $this->_websiteExport();
//        $this->_closeExport();
//        return $this;
    }

    /**
     * @description Declare what code have to do for Website scope
     * @return void
     */
    protected function _websiteExport()
    {
        foreach (Mage::app()->getWebsites() as $website) {

            $this->delTree($this->_dir);

            if (!$this->_isEnabled()) {
                continue;
            }

            $data = $this->_storeExport($website);


//            if ($this->_getIndexType() == 'delta' && count($data['products']) == 0 && count($data['customers']) == 0 && count($data['transactions']) == 0) {
//                continue;
//            }

            foreach($this->_listOfAttributes as $k => $attr){
                if(
                    !isset($this->_attributesValuesByName[$attr]) ||
                    (isset($this->_attrProdCount[$attr]) &&
                    $this->_attrProdCount[$attr])
                ){
                    continue;
                } else{
                    unset($this->_attributesValuesByName[$attr]);
                    unset($this->_listOfAttributes[$k]);
                }
            }

            $file = $this->prepareFiles($website, $data['products'], $data['categories'], /*$data['customers'],*/ $data['tags'] /*, $data['transactions']*/);

            $this->pushXML($file);
            $this->pushZip($file);

            $this->_transformedCategories = array();
            $this->_transformedTags = array();
            $this->_transformedProducts = array();
            $this->_categoryParent = array();
            $this->_availableLanguages = array();
            $this->_tmp = array();
            $this->_attrProdCount = array();
            $this->_count = 0;

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
//        $customers = array();
        $tags = array();
//        $transactions = array();
        foreach ($website->getGroups() as $group) {

            $this->group = $group;

            foreach ($group->getStores() as $store) {
                $this->_prepareStoreConfig($store->getId());
                if ($this->_isEnabled()) {
                    $categories = $this->_exportCategories();
                    $tags = $this->_exportTags();
                    $this->_availableLanguages[] = $this->_storeConfig['language'];
                }
            }
            if ($this->_isEnabled()) {
                $this->_exportCustomers();
                $this->_exportTransactions();
                $products = $this->_exportProducts();
            }
        }

        return array(
            'products' => $products,
            'categories' => $categories,
//            'customers' => $customers,
            'tags' => $tags,
//            'transactions' => $transactions
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
        $this->_storeConfig = array_merge(Mage::app()->getStore($this->_storeId)->getConfig('boxalinoexporter/export_data'), Mage::app()->getStore($this->_storeId)->getConfig('Boxalino_General/general'));

        $tmp = Mage::app()->getStore($this->_storeId)->getConfig('Boxalino_CemSearch/backend');
        $this->_storeConfig['username'] = $tmp['username'];
        $this->_storeConfig['password'] = $tmp['password'];
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

        $attributes = array();

        foreach(Mage::getResourceModel('catalog/product_attribute_collection')->getItems() as $at){
            $attributes[] = $at->getAttributeCode();
        }

        if (isset($this->_storeConfig['additional_attributes']) && $this->_storeConfig['additional_attributes'] != '') {
            $fields = explode(',', $this->_storeConfig['additional_attributes']);
            foreach ($fields as $field) {

                if(!in_array($field, $attributes)){
                    Mage::throwException("Attribute \"$field\" not exist!" );
                }

                if($field != null && strlen($field) > 0){
                    $this->_listOfAttributes[] = $field;
                }

            }
            unset($fields);
        }

    }

    /**
     * @description Get labels for all Attributes where is optionsId = optionValue
     * @return void
     */
    protected function _getAllAttributesValues()
    {
        $attributesWithId = Mage::helper('boxalinoexporter')->attributesWithIds();
        foreach ($this->_listOfAttributes as $attribute) {
            if (array_search($attribute, $attributesWithId) == true) {
                $options = Mage::getModel('eav/config')->getAttribute('catalog_product', $attribute)->setStoreId($this->_storeId)->getSource()->getAllOptions();
                foreach ($options as $option) {
                    if (!empty($option['value'])) {
                        $this->_attributesValues[$this->_storeId][$attribute][$option['value']] = $this->escapeString($option['label']);

                        $value = intval($option['value']);
                        $name = 'value_' . $this->_storeConfig['language'];

                        if (isset($this->_attributesValuesByName[$attribute])) {

                            if (isset($this->_attributesValuesByName[$attribute][$value])) {
                                $this->_attributesValuesByName[$attribute][$value][$name] = /*strtolower*/
                                    $this->escapeString($option['label']);
                            } else {
                                $this->_attributesValuesByName[$attribute][$value] = array($attribute . '_id' => $value, $name => /*strtolower*/
                                    $this->escapeString($option['label']));
                            }

                        } else {
                            $this->_attributesValuesByName[$attribute] = array($value => array($attribute . '_id' => $value, $name => /*strtolower*/
                                $this->escapeString($option['label'])));
                        }

                    }
                }
                unset($options);
            }
        }
        unset($attributesWithId);
    }

    /**
     * @description check if export is enabled for website
     * @return bool
     */
    protected function _isEnabled()
    {
        if ($this->_storeConfig['enabled']) {
            return true;
        } else if(!isset($this->_storeConfig['enabled']) && Mage::getStoreConfig('Boxalino_General/general/enabled')){
            return true;
        }

        return false;
    }

    /**
     * @description Preparing categories to export
     * @return array Cateroies
     */
    protected function _exportCategories()
    {
        if ($this->_storeConfig['export_categories']) {
            $categories = $this->_getCategories();
            foreach ($categories as $category) {

                if ($category->getParentId() == null) {
                    continue;
                }

                if (isset($this->_transformedCategories[$category->getId()])) {
                    $this->_transformedCategories[$category->getId()]['value_' . $this->_storeConfig['language']] = $this->escapeString($category->getName());
                } else {
                    $parentId = null;
                    if ($category->getParentId() != 0) {
                        $parentId = $category->getParentId();
                    }
                    $this->_transformedCategories[$category->getId()] = array('category_id' => $category->getId(), 'parent_id' => $parentId, 'value_' . $this->_storeConfig['language'] => $this->escapeString($category->getName()));
                }
            }
            unset($categories);
            return $this->_transformedCategories;
        }


        return null;

    }

    abstract protected function _getCategories();

    /**
     * @description Preparing tags to export
     * @return array Tags
     *
     */
    protected function _exportTags()
    {

        if ($this->_storeConfig['export_tags']) {

            $tags = Mage::helper('boxalinoexporter')->getAllTags();

            foreach ($tags as $id => $tag) {
                if (isset($this->_transformedTags[$id])) {
                    $this->_transformedTags[$id]['value_' . $this->_storeConfig['language']] = $tag;
                } else {
                    $this->_transformedTags[$id] = array('tag_id' => $id, 'value_' . $this->_storeConfig['language'] => $tag);
                }
            }

            unset($tags);
            return $this->_transformedTags;
        }

        return null;
    }

    /**
     * @description Preparing products to export
     * @return array Products
     */
    protected function _exportProducts()
    {
        $attrs = $this->_listOfAttributes;

        $helper = Mage::helper('boxalinoexporter');

        $countMax = $this->_storeConfig['maximum_population'];
        $localeCount = 0;

        $limit = $this->_storeConfig['export_chunk'];
        $count = $limit;
        $page = 1;
        $header = true;

        while($count >= $limit){

            $products_to_save = array();

            foreach ($this->group->getStores() as $store) {

                $lang = Mage::app()->getStore($store->getId())->getConfig('boxalinoexporter/export_data/language');

                $products = Mage::getModel('catalog/product')
                ->getCollection()
                ->setStore($store->getId())
                ->setPageSize($limit)
                ->setCurPage($page)
                ->addAttributeToSelect($attrs);

                foreach ($products as $product) {
                    if (count($product->getWebsiteIds()) == 0) {
                        continue;
                    }

                    $id = $product->getId();

                    $productParam = array();
                    $haveParent = false;

                    if ($helper->getParentId($id) != null && $product->getTypeId() == 'simple') {
                        $id = $helper->getParentId($id);
                        $haveParent = true;
                    } else if ($product->getVisibility() == 1 && $helper->getParentId($id) == null) {
                        unset($product);
                        continue;
                    }

                    foreach ($attrs as $attr) {

                        if (isset($this->_attributesValuesByName[$attr])) {

                            $val = $this->escapeString($product->$attr);

                            $attr = Mage::helper("Boxalino_CemSearch")->sanitizeFieldName($attr);

                            if ($val == null) {
                                continue;
                            }

                            $this->_attrProdCount[$attr] = true;

                            if (isset($this->_tmp[$attr][$id]) && in_array($val, $this->_tmp[$attr][$id])) {
                                continue;
                            }

                            if (isset($this->_transformedProducts['productsMtM'][$attr])) {
                                // If visibility is set everywhere (have value "4"), then we split it for value "2" and "3" (search and catalog separately)
                                if ($attr == 'visibility' && $val == '4') {
                                    $this->_transformedProducts['productsMtM'][$attr][] = array(/*'id' => count($this->_transformedProducts['productsMtM'][$attr])+1,*/
                                        'entity_id' => $id, $attr . '_id' => '2');
                                    $this->_transformedProducts['productsMtM'][$attr][] = array(/*'id' => count($this->_transformedProducts['productsMtM'][$attr])+1,*/
                                        'entity_id' => $id, $attr . '_id' => '3');
                                    $this->_tmp[$attr][$id][] = $val;
                                } else {
                                    $this->_transformedProducts['productsMtM'][$attr][] = array(/*'id' => count($this->_transformedProducts['productsMtM'][$attr])+1,*/
                                        'entity_id' => $id, $attr . '_id' => $val);
                                    $this->_tmp[$attr][$id][] = $val;
                                }
                            } else {
                                $this->_transformedProducts['productsMtM'][$attr] = array();
                                // If visibility is set everywhere (have value "4"), then we split it for value "2" and "3" (search and catalog separately)
                                if ($attr == 'visibility' && $val == '4') {
                                    $this->_transformedProducts['productsMtM'][$attr][] = array(/*'id' => 1,*/
                                        'entity_id' => $id, $attr . '_id' => '2');
                                    $this->_transformedProducts['productsMtM'][$attr][] = array(/*'id' => 2,*/
                                        'entity_id' => $id, $attr . '_id' => '3');
                                    $this->_tmp[$attr][$id] = array($val);
                                } else {
                                    $this->_transformedProducts['productsMtM'][$attr][] = array(/*'id' => 1,*/
                                        'entity_id' => $id, $attr . '_id' => $val);
                                    $this->_tmp[$attr][$id] = array($val);
                                }
                            }

                            continue;
                        }


                        switch ($attr) {
                            case 'description':
                            case 'short_description':
                            case 'name':
                                $productParam[$attr . '_' . $lang] = $this->escapeString($product->$attr);
                                break;
                            case 'category_ids':
                                break;
                            default:
                                $productParam[$attr] = $this->escapeString($product->$attr);
                                break;
                        }

                    }

                    if ($haveParent) {
                        continue;
                    }

                    if (!isset($this->_transformedProducts['products'][$id])) {
                        if ($countMax > 0 && $this->_count >= $countMax) {
                            break;
                        }
                        $productParam['entity_id'] = $id;
        //                $productParam['parent_id'] = $helper->getParentId($id);
                        $this->_transformedProducts['products'][$id] = $productParam;

                        //Add categories
                        foreach ($product->getCategoryIds() as $cat) {
                            while ($cat != null) {
                                $this->_transformedProducts['productsMtM']['categories'][] = array('entity_id' => $id, 'category_id' => $cat);
                                if (isset($this->_transformedCategories[$cat]['parent_id'])) {
                                    $cat = $this->_transformedCategories[$cat]['parent_id'];
                                } else {
                                    $cat = null;
                                }
                            }
                        }
                        $this->_count++;

                    } elseif (isset($this->_transformedProducts['products'][$id])) {
                        if ($countMax > 0 && $localeCount >= $countMax) {
                            break;
                        }
                        $this->_transformedProducts['products'][$id] = array_merge($this->_transformedProducts['products'][$id], $productParam);

                        $localeCount++;
                    }

                    ksort($this->_transformedProducts['products'][$id]);

                }
            }

            $data = $this->_transformedProducts['products'];
            $count = count($products);

            if($header){
                $data = array_merge(array(array_keys(end($data))), $data);
                $header = false;
            }

            $this->savePartToCsv('products.csv', $data);

            $this->_transformedProducts['products'] = array();



            $page++;

        }

        unset($products);
        return $this->_transformedProducts;
    }

    abstract protected function _getStoreProducts();

    /**
     * @description Preparing customers to export
     * @return array Customers
     *
     */
    protected function _exportCustomers()
    {

        if ($this->_storeConfig['export_customers']) {

            $collection = Mage::getModel('directory/country')->getCollection();

            if ($this->_countries == null) {
                foreach ($collection as $country) {
                    $this->_countries[$country->getId()] = $country->getName();
                }
            }

            $limit = $this->_storeConfig['export_chunk'];
            $count = $limit;
            $page = 1;
            $header = true;

            while($count >= $limit){

                $products_to_save = array();
                $customers = Mage::getModel('customer/customer')
                    ->getCollection()
                    ->setPageSize($limit)
                    ->setCurPage($page)
                    ->addAttributeToSelect('*');

                foreach ($customers as $customer) {

                    $billing = $customer->getPrimaryBillingAddress();
                    if(!empty($billing)) {
                        $countryCode = $billing->getCountry();
                    }

                    switch ($customer->getGender()) {
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

                    $products_to_save[] = array(
                        'customer_id' => $customer->getId(),
                        'gender' => $gender,
                        'dob' => $customer->getDob(),
                        'country' => !empty($countryCode) ? Mage::helper('boxalinoexporter')->getCountry($countryCode)->getName() : '',
                        'zip' => !empty($billing) ? $billing->getPostcode() : ''
                    );

                }

                $data = $products_to_save;

                if($header){
                    $data = array_merge(array(array_keys(end($products_to_save))), $products_to_save);
                    $header = false;
                }

                $this->savePartToCsv('customers.csv', $data);

                $count = count($products_to_save);
                $page++;

            }
            unset($customers);
        }

        return null;
    }

    abstract protected function _getCustomers();

    /**
     * @description Preparing transactions to export
     * @return array Transaction
     */
    protected function _exportTransactions()
    {

        if ($this->_storeConfig['export_transactions']) {

            $limit = $this->_storeConfig['export_chunk'];
            $count = $limit;
            $page = 1;
            $header = true;

            while($count >= $limit){

                $products_to_save = array();
                $transactions = Mage::getModel('sales/order')
//                    ->setStoreId($this->_storeId)
                    ->getCollection()
                    ->setPageSize($limit)
                    ->setCurPage($page)
                    ->addAttributeToSelect('*');

                foreach ($transactions as $transaction) {

                    $configurable = array();

                    $products = ($transaction->getAllItems());

                    foreach ($products as $product) {

                        //is configurable
                        if ($product->getParentItemId() == null && $product->getProductType() != 'simple') {
                            $configurable[$product->getId()] = $product;
                            continue;
                        }

                        //is configurable - simple product
                        if (intval($product->getPrice()) == 0) {
                            $pid = $configurable[$product->getParentItemId()];
                            $product->setOriginalPrice($pid->getOriginalPrice());
                            $product->setPrice($pid->getPrice());
                        }

                        $status = 0; // 0 - pending, 1 - confirmed, 2 - shipping

                        if ($transaction->getStatus() == 'canceled') {
                            continue;
                        }

                        if ($transaction->getUpdatedAt() != $transaction->getCreatedAt()) {

                            switch ($transaction->getStatus()) {
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
                        $products_to_save[] = array(
                            'order_id' => $transaction->getId(),
                            'entity_id' => $product->getProductId(),
                            'customer_id' => $transaction->getCustomerId(),
                            'price' => $product->getOriginalPrice(),
                            'discounted_price' => $product->getPrice(),
                            'quantity' => $product->getQtyOrdered(),
                            'total_order_value' => ($transaction->getBaseSubtotal() + $transaction->getShippingAmount()),
                            'shipping_costs' => $transaction->getShippingAmount(),
                            'order_date' => $transaction->getCreatedAt(),
                            'confirmation_date' => $status == 1 ? $transaction->getUpdatedAt() : null,
                            'shipping_date' => $status == 2 ? $transaction->getUpdatedAt() : null,
                            'status' => $transaction->getStatus()
                        );
                    }
                }

                $data = $products_to_save;

                if($header){
                    $data = array_merge(array(array_keys(end($products_to_save))), $products_to_save);
                    $header = false;
                }

                $this->savePartToCsv('transactions.csv', $data);

                $count = count($products_to_save);
                $page++;

            }
            unset($customers);
        }

        return null;
    }

    abstract protected function _getTransactions();

    abstract protected function _getIndexType();

    /**
     * @description Preparing files to send
     */
    protected function prepareFiles($website, $products, $categories = null, /*$customers = null,*/ $tags = null /*,$transactions = null*/)
    {

        //Prepare attributes
        $csvFiles = array();
        if (!file_exists("/tmp/boxalino")) {
            mkdir("/tmp/boxalino");
        }
        $csv = new Varien_File_Csv();

        $helper = Mage::helper('boxalinoexporter');
        $csv->setDelimiter($helper->XML_DELIMITER);
        $csv->setEnclosure(null);

        //create csv
        //save attributes
        foreach ($this->_attributesValuesByName as $attrName => $attrValues) {
            $csvFiles[] = $this->createCsv(Mage::helper("Boxalino_CemSearch")->sanitizeFieldName($attrName), $attrValues, $csv);
        }

        //save categories
        if ($categories != null) {
            $csvFiles[] = $this->createCsv('categories', $categories, $csv);
            unset($categories);
        }

        //save tags
        if ($tags != null) {
            $csvFiles[] = $this->createCsv('tag', $tags, $csv);

            $loop = 1;
            foreach ($this->_getProductTags() as $product_id => $tag_id) {
                $csvdata[] = array('id' => $loop++, 'entity_id' => $product_id, 'tag_id' => $tag_id);
            }

            $csvFiles[] = $this->createCsv('product_tag', $csvdata, $csv);

        }

//        //save transactions
//        if ($transactions != null && count($transactions) > 0) {
//            $csvFiles[] = $this->createCsv('transactions', $transactions, $csv);
//        }
//
//        //save customers
//        if ($customers != null) {
//            $csvFiles[] = $this->createCsv('customers', $customers, $csv);
//        }
//
//        //products
//        $csvFiles[] = $this->createCsv('products', $products['products'], $csv);

//        //products & attributes
        foreach ($products['productsMtM'] as $key => $val) {
            $csvFiles[] = $this->createCsv("product_" . Mage::helper("Boxalino_CemSearch")->sanitizeFieldName($key) , $val, $csv);
        }
//        csv done

        //Create name for file
        $exportFile = '/tmp/boxalino/' . $this->_storeConfig['di_username'];

        $csvFiles = array_filter($csvFiles);

        //Create xml
        $this->createXML($exportFile . '.xml');

        //Create zip

        $this->createZip($exportFile . '.zip', array_filter($this->_files), $exportFile . '.xml');

        return $exportFile;

        //

    }

    /**
     * @param $name
     * @param $data
     * @param $csv
     * @return string
     */
    protected function createCsv($name, $data, $csv)
    {
        $file = $name . '.csv';

        if(!is_array($data) || count($data) == 0){
            Mage::getModel('adminhtml/session')->addWarning("Data for $file have wrong format or is empty. " . "[" . gettype($data) . "]");
        }

        $csvdata = array_merge(array(array_keys(end($data))), $data);
        $csvdata[0][0] = Mage::helper("Boxalino_CemSearch")->sanitizeFieldName($csvdata[0][0]);
        $csv->saveData('/tmp/boxalino/' . $file, $csvdata);

        $this->_files[] = $file;

        return $file;


    }

    /**
     * @description Get list of products with their tags
     * @return object List of products with their tags array('product_id' => 'tag_id');
     */
    protected function _getProductTags()
    {
        if (empty($this->_allProductTags)) {
            $tags = Mage::getResourceModel('tag/product_collection')->getData();
            foreach ($tags as $tag) {
                $this->_allProductTags[$tag['entity_id']] = $tag['tag_id'];
            }
            unset($tags);
        }

        return $this->_allProductTags;
    }

    protected function createXML($name)
    {

        $helper = Mage::helper('boxalinoexporter');

        $xml = new SimpleXMLElement('<root/>');

        $languages = $xml->addChild('languages');
        $containers = $xml->addChild('containers');

        //languages
        foreach ($this->_availableLanguages as $lang) {
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
                    <enclosure value="$helper->XML_ENCLOSURE_TEXT"/>
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
                <property id="customer_id" type="string">
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
                    <productIdColumn value="entity_id" product_property_id="product_entity_id"/>
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
                    <enclosure value="$helper->XML_ENCLOSURE_TEXT"/>
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

        //product source
        #########################################################################
        $source = $sources->addChild('source');
        $source->addAttribute('id', 'item_vals');
        $source->addAttribute('type', 'item_data_file');

        $source->addChild('file')->addAttribute('value', 'products.csv');
        $source->addChild('itemIdColumn')->addAttribute('value', 'entity_id');

        $this->sxml_append_options($source);
        #########################################################################

        $attrs = array_keys($this->_attributesValuesByName);
        if ($this->_storeConfig['export_tags']) {
            $attrs[] = 'tag';

        }

        foreach ($attrs as $attr) {

//            if(!array_key_exists($attr . '.csv', $this->_files)){
//                continue;
//            }

            $attr = Mage::Helper("Boxalino_CemSearch")->sanitizeFieldName($attr);

            //attribute
            $source = $sources->addChild('source');
            $source->addAttribute('type', 'resource');
            $source->addAttribute('id', 'resource_' . $attr);

            $source->addChild('file')->addAttribute('value', $attr . '.csv');
            $source->addChild('referenceIdColumn')->addAttribute('value', $attr . '_id');
            $source->addChild('itemIdColumn')->addAttribute('value', $attr . '_id');

            $labelColumns = $source->addChild('labelColumns');
            foreach ($this->_availableLanguages as $lang) {
                $label = $labelColumns->addChild('language');
                $label->addAttribute('name', $lang);
                $label->addAttribute('value', 'value_' . $lang);
            }

            $this->sxml_append_options($source);

            //product & attribute
            $source = $sources->addChild('source');
            $source->addAttribute('type', 'item_data_file');
            $source->addAttribute('id', 'item_' . $attr);

            $source->addChild('file')->addAttribute('value', 'product_' . $attr . '.csv');
            $source->addChild('itemIdColumn')->addAttribute('value', 'entity_id');

            $this->sxml_append_options($source);

        }

       ########################################################################
        if ($this->_storeConfig['export_categories']) {
            //categories
            $sourceCategory = $sources->addChild('source');
            $sourceCategory->addAttribute('type', 'hierarchical');
            $sourceCategory->addAttribute('id', 'resource_categories');


            $sourceCategory->addChild('file')->addAttribute('value', 'categories.csv');
            $sourceCategory->addChild('referenceIdColumn')->addAttribute('value', 'category_id');
            $sourceCategory->addChild('parentIdColumn')->addAttribute('value', 'parent_id');

            $labelColumns = $sourceCategory->addChild('labelColumns');
            foreach ($this->_availableLanguages as $lang) {
                $label = $labelColumns->addChild('language');
                $label->addAttribute('name', $lang);
                $label->addAttribute('value', 'value_' . $lang);
            }

            $this->sxml_append_options($sourceCategory);

            //categories & products
            $source = $sources->addChild('source');
            $source->addAttribute('type', 'item_data_file');
            $source->addAttribute('id', 'item_categories');


            $source->addChild('file')->addAttribute('value', 'product_categories.csv');
            $source->addChild('itemIdColumn')->addAttribute('value', 'entity_id');

            $this->sxml_append_options($source);
        }
        #########################################################################

        //property
        $properties = $products->addChild('properties');
        $props = $this->prepareProperties();

        foreach ($props as $prop) {
            if ($prop['id'] == 'entity_id') {

            }

//            if($prop['type'] == 'reference' && !array_key_exists($prop['name'], $this->_files)){
//                continue;
//            }

            $property = $properties->addChild('property');
            $property->addAttribute('id', Mage::Helper("Boxalino_CemSearch")->sanitizeFieldName($prop['id']));
            $property->addAttribute('type', $prop['ptype']);

            $transform = $property->addChild('transform');
            $logic = $transform->addChild('logic');
            $ls = $prop['name'] == null ? 'item_vals' : 'item_' . $prop['name'];
            $logic->addAttribute('source', Mage::Helper("Boxalino_CemSearch")->sanitizeFieldName($ls));
            $logic->addAttribute('type', $prop['type']);
            if ($prop['has_lang'] == true) {
                foreach ($this->_availableLanguages as $lang) {
                    $field = $logic->addChild('field');
                    $field->addAttribute('column', Mage::Helper("Boxalino_CemSearch")->sanitizeFieldName($prop['field']) . '_' . $lang);
                    $field->addAttribute('language', $lang);
                }
            } else {
                $logic->addChild('field')->addAttribute('column', Mage::Helper("Boxalino_CemSearch")->sanitizeFieldName($prop['field']));
            }

            $params = $property->addChild('params');
            if ($prop['type'] != 'direct') {
                $params->addChild('referenceSource')->addAttribute('value', 'resource_' . Mage::Helper("Boxalino_CemSearch")->sanitizeFieldName($prop['reference']));
            }

        }
        ##################################

        ##################################

        if ($this->_storeConfig['export_customers']) {
            $customer = simplexml_load_string($customerString);
            $this->sxml_append($containers, $customer);
        }

        if ($this->_storeConfig['export_transactions']) {
            $transaction = simplexml_load_string($transactionString);
            $this->sxml_append($containers, $transaction);
        }

        $dom = new DOMDocument("1.0");
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xml->asXML());
        $saveXML = $dom->saveXML();
        file_put_contents($name, $saveXML);
//        $dom->save($name);


    }

    /**
     * desciption: add default xmlElements
     * @param SimpleXMLElement $xml
     */
    function sxml_append_options(SimpleXMLElement &$xml)
    {

        $helper = Mage::helper('boxalinoexporter');

        $xml->addChild('format')->addAttribute('value', $helper->XML_FORMAT);
        $xml->addChild('encoding')->addAttribute('value', $helper->XML_ENCODE);
        $xml->addChild('delimiter')->addAttribute('value', $helper->XML_DELIMITER);
        $xml->addChild('enclosure')->addAttribute('value', $helper->XML_ENCLOSURE);
        $xml->addChild('escape')->addAttribute('value', $helper->XML_ESCAPE);
        $xml->addChild('lineSeparator')->addAttribute('value', $helper->XML_NEWLINE);
    }

    /**
     * @return array
     */
    function prepareProperties()
    {

        $properties = array();

        $attrs = $this->_listOfAttributes;
//        $attrs[] = 'parent_id';

        foreach ($attrs as $attr) {

            $ptype = 'string';

            // set property type
            switch ($attr) {
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
                    $ptype = 'discounted';
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
                    $ptype = 'number';
                    break;

            }

            if (isset($this->_attributesValuesByName[$attr])) {
                $properties[] = array(
                    'id' => $attr,
                    'name' => $attr,
                    'ptype' => 'text',
                    'type' => 'reference',
                    'field' => $attr . '_id',
                    'has_lang' => false,
                    'reference' => $attr
                );
            } elseif ($attr == 'category_ids') {
                continue;
            } else {
                $ref = null;
                $type = 'direct';
                $field = $attr;
                switch ($attr) {
                    case 'description':
                    case 'short_description':
                    case 'name':
                        $lang = true;
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
        if ($this->_storeConfig['export_tags']) {
            $properties[] = array(
                'id' => 'tag',
                'name' => 'tag',
                'ptype' => 'text',
                'type' => 'reference',
                'field' => 'tag_id',
                'has_lang' => false,
                'reference' => 'tag'
            );
        }

        //categories
        if ($this->_storeConfig['export_categories']) {
            $properties[] = array(
                'id' => 'category',
                'name' => 'categories', //property id
                'ptype' => 'hierarchical', //property type
                'type' => 'reference',  //logic type
                'field' => 'category_id', //field colummn
                'has_lang' => false,
                'reference' => 'categories'
            );
        }
        $properties[] = array('id' => 'product_entity_id', 'name' => null, 'ptype' => 'string', 'type' => 'direct', 'field' => 'entity_id', 'has_lang' => false, 'reference' => null);

        return $properties;

    }

    /**
     *
     * description: add xmlElement to other xmlElement
     * @param SimpleXMLElement $to
     * @param SimpleXMLElement $from
     */
    function sxml_append(SimpleXMLElement $to, SimpleXMLElement $from)
    {
        $toDom = dom_import_simplexml($to);
        $fromDom = dom_import_simplexml($from);
        $toDom->appendChild($toDom->ownerDocument->importNode($fromDom, true));
    }

    /**
     * @param $name
     * @param $csvFiles
     */
    protected function createZip($name, $csvFiles, $xml)
    {
        if (file_exists($name)) {
            @unlink($name);
        }
 //
//        $this->_files[] = $name;

        $zip = new ZipArchive();
        if ($zip->open($name, ZIPARCHIVE::CREATE)) {

            foreach ($csvFiles as $f) {
                if (!$zip->addFile('/tmp/boxalino/' . $f, $f)) {
                    throw new Exception('Synchronization failure. Please try again.');
                }
            }

            if (!$zip->addFile($xml, 'properties.xml')) {
                throw new Exception('Synchronization failure. Please try again.');
            }

            if (!$zip->close()) {
                throw new Exception('Synchronization failure. Please try again.');
            }

        } else {
            throw new Exception('Synchronization failure. Please try again.');
        }
    }

    protected function pushXML($file)
    {

        $fields = array(
            "username" => $this->_storeConfig['di_username'],
            "password" => $this->_storeConfig['di_password'],
            "account" => $this->_storeConfig['di_account'],
            "template" => 'standard_source',
            "xml" => file_get_contents($file . '.xml')
        );

        $url = Mage::helper('boxalinoexporter')->getXMLSyncUrl($this->_storeConfig['account_dev']);

        return $this->pushFile($fields, $url, 'xml');

    }

    protected function pushFile($fields, $url, $type)
    {

        $s = curl_init();

        curl_setopt($s, CURLOPT_URL, $url);
        curl_setopt($s, CURLOPT_TIMEOUT, 35000);
        curl_setopt($s, CURLOPT_POST, true);
        curl_setopt($s, CURLOPT_ENCODING, "");
        curl_setopt($s, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($s, CURLOPT_POSTFIELDS, $fields);

        $responseBody = curl_exec($s);
        curl_close($s);
        if (strpos($responseBody, 'Internal Server Error') !== false) {
            Mage::throwException(Mage::helper('boxalinoexporter')->getError($responseBody));;
        }
        return $responseBody;
    }

    /**
     * @param $zip
     */
    protected function pushZip($file)
    {
        $fields = array(
            "username" => $this->_storeConfig['di_username'],
            "password" => $this->_storeConfig['di_password'],
            "account" => $this->_storeConfig['di_account'],
            "dev" => $this->_storeConfig['account_dev'] == 0 ? 'false' : 'true',
            "delta" => $this->_getIndexType() == 'delta' ? "true" : "false", // I know...
            "data" => '@' . $file . '.zip;type=application/zip'
        );

        $url = Mage::helper('boxalinoexporter')->getZIPSyncUrl($this->_storeConfig['account_dev']);

        return $this->pushFile($fields, $url, 'zip');
    }

    /**
     * @description Closing sync to export
     */
    protected function _closeExport()
    {
        $this->delTree($this->_dir);
    }

    function getmicrotime($mt, $string = true)
    {
        list($usec, $sec) = explode(" ", $mt);

        if ($string)
            return date('Y-m-d H:i:s', (float)$usec + (float)$sec) . '.' . $usec;
        else
            return ((float)$usec + (float)$sec);
    }

    /**
     * @description Get stock of all products
     * @return array List of stocks qty for products
     */
    protected function _getProductsStockQty()
    {
        if (empty($this->_productsStockQty)) {
            $products = Mage::getModel('cataloginventory/stock_item')->getCollection();
            foreach ($products as $product) {
                $this->_productsStockQty[$product->getProductId()] = $product->getQty();
            }
            unset($products);
        }

        return $this->_productsStockQty;
    }

    protected function savePartToCsv($file, $data){

        if (!file_exists("/tmp/boxalino")) {
            mkdir("/tmp/boxalino");
        }
        $csv = new Varien_File_Csv();

        $helper = Mage::helper('boxalinoexporter');

        //save

        if(!in_array($file, $this->_file)){
            $this->_files[] = $file;
        }

        $fh = fopen('/tmp/boxalino/' . $file, 'a');
        foreach ($data as $dataRow) {
            $csv->fputcsv($fh, $dataRow, $helper->XML_DELIMITER, null);
        }
        fclose($fh);

    }

    public static function delTree($dir) {
        $files = array_diff(scandir($dir), array('.','..'));
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? delTree("$dir/$file") : unlink("$dir/$file");
        }
        return rmdir($dir);
    }

    protected function escapeString($string){

        $helper = Mage::helper('boxalinoexporter');

        return $helper->XML_ENCLOSURE . htmlspecialchars(trim(preg_replace('/\s+/', ' ', $string))) . $helper->XML_ENCLOSURE;

    }

}