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

    protected $_helperExporter = null;
    protected $_helperSearch = null;

    private $_parentId = null;
    private $_simpleIds = null;
    private $_isLoad = false;
    private $logger = null;

    /**
     * @description Start of apocalypse
     */
    public function reindexAll()
    {
        Boxalino_CemSearch_Model_Logger::saveMemoryTracking('info', 'Indexer', array('memory_usage' => memory_get_usage(true), 'method' => __METHOD__, 'description' => 'Indexer init'));
        $this->_websiteExport();
        $this->_closeExport();
        return $this;
    }

    /**
     * @description Declare what code have to do for Website scope
     * @return void
     */
    protected function _websiteExport()
    {
        $this->_helperExporter = Mage::helper('boxalinoexporter');
        $this->_helperSearch = Mage::helper("Boxalino_CemSearch");
        Boxalino_CemSearch_Model_Logger::saveMemoryTracking('info', 'Indexer', array('memory_usage' => memory_get_usage(true), 'method' => __METHOD__, 'description' => 'Helpers init'));
        foreach (Mage::app()->getWebsites() as $website) {

            $this->delTree($this->_dir);
            Boxalino_CemSearch_Model_Logger::saveMemoryTracking('info', 'Indexer', array('memory_usage' => memory_get_usage(true), 'method' => __METHOD__, 'description' => 'After delTree'));

            if (!$this->_isEnabled()) {
                continue;
            }

            $data = $this->_storeExport($website);

//            if ($this->_getIndexType() == 'delta' && count($data['products']) == 0 && count($data['customers']) == 0 && count($data['transactions']) == 0) {
//                continue;
//            }
            Boxalino_CemSearch_Model_Logger::saveMemoryTracking('info', 'Indexer', array('memory_usage' => memory_get_usage(true), 'method' => __METHOD__, 'description' => 'something with attributes - before'));

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

            Boxalino_CemSearch_Model_Logger::saveMemoryTracking('info', 'Indexer', array('memory_usage' => memory_get_usage(true), 'method' => __METHOD__, 'description' => 'something with attributes - after'));
            $file = $this->prepareFiles($website, $data['categories'], $data['tags']);
            Boxalino_CemSearch_Model_Logger::saveMemoryTracking('info', 'Indexer', array('memory_usage' => memory_get_usage(true), 'method' => __METHOD__, 'description' => 'Push files'));

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
        Boxalino_CemSearch_Model_Logger::saveMemoryTracking('info', 'Indexer', array('memory_usage' => memory_get_usage(true), 'method' => __METHOD__, 'description' => 'Preparing data for website start'));
        foreach ($website->getGroups() as $group) {

            $this->group = $group;

            foreach ($group->getStores() as $store) {
                Boxalino_CemSearch_Model_Logger::saveMemoryTracking('info', 'Indexer', array('memory_usage' => memory_get_usage(true), 'method' => __METHOD__, 'description' => 'Start store:'.$store->getId()));
                $this->_prepareStoreConfig($store->getId());
                Boxalino_CemSearch_Model_Logger::saveMemoryTracking('info', 'Indexer', array('memory_usage' => memory_get_usage(true), 'method' => __METHOD__, 'description' => 'Configuration for store loaded'));
                if ($this->_isEnabled()) {
                    $categories = $this->_exportCategories();
                    $tags = $this->_exportTags();
                    Boxalino_CemSearch_Model_Logger::saveMemoryTracking('info', 'Indexer', array('memory_usage' => memory_get_usage(true), 'method' => __METHOD__, 'description' => 'Without available languages'));
                    $this->_availableLanguages[] = $this->_storeConfig['language'];
                    Boxalino_CemSearch_Model_Logger::saveMemoryTracking('info', 'Indexer', array('memory_usage' => memory_get_usage(true), 'method' => __METHOD__, 'description' => 'With available languages'));

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
            'tags' => $tags,
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
        if (!$this->_helperExporter->isAvailableLanguages($this->_storeConfig['language'])) {
            Mage::throwException($this->_helperExporter->__('Language "' . $this->_storeConfig['language'] . '" is not available.'));
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
        $this->_listOfAttributes = $this->_helperExporter->defaultAttributes();

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
        $attributesWithId = $this->_helperExporter->attributesWithIds();
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
        if (isset($this->_storeConfig['enabled']) && $this->_storeConfig['enabled']) {
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
            Boxalino_CemSearch_Model_Logger::saveMemoryTracking('info', 'Indexer', array('memory_usage' => memory_get_usage(true), 'method' => __METHOD__, 'description' => 'Categories are not loaded'));
            $categories = $this->_getCategories();
            Boxalino_CemSearch_Model_Logger::saveMemoryTracking('info', 'Indexer', array('memory_usage' => memory_get_usage(true), 'method' => __METHOD__, 'description' => 'Categories are loaded'));
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
            $categories = null;;
            Boxalino_CemSearch_Model_Logger::saveMemoryTracking('info', 'Indexer', array('memory_usage' => memory_get_usage(true), 'method' => __METHOD__, 'description' => 'Categories are returned for data saving'));
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
            Boxalino_CemSearch_Model_Logger::saveMemoryTracking('info', 'Indexer', array('memory_usage' => memory_get_usage(true), 'method' => __METHOD__, 'description' => 'Tags are not loaded'));
            $tags = $this->_helperExporter->getAllTags();
            Boxalino_CemSearch_Model_Logger::saveMemoryTracking('info', 'Indexer', array('memory_usage' => memory_get_usage(true), 'method' => __METHOD__, 'description' => 'Tags are loaded'));

            foreach ($tags as $id => $tag) {
                if (isset($this->_transformedTags[$id])) {
                    $this->_transformedTags[$id]['value_' . $this->_storeConfig['language']] = $tag;
                } else {
                    $this->_transformedTags[$id] = array('tag_id' => $id, 'value_' . $this->_storeConfig['language'] => $tag);
                }
            }

            $tags = null;
            Boxalino_CemSearch_Model_Logger::saveMemoryTracking('info', 'Indexer', array('memory_usage' => memory_get_usage(true), 'method' => __METHOD__, 'description' => 'Tags are returned for data saving'));
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

        Boxalino_CemSearch_Model_Logger::saveMemoryTracking('info', 'Indexer', array('memory_usage' => memory_get_usage(true), 'method' => __METHOD__, 'description' => 'Products - start of export'));
        $attrs = $this->_listOfAttributes;
        Boxalino_CemSearch_Model_Logger::saveMemoryTracking('info', 'Indexer', array('memory_usage' => memory_get_usage(true), 'method' => __METHOD__, 'description' => 'Products - get info about attributes - before'));
        $query = "
SELECT `main_table`.`attribute_id`, `main_table`.`entity_type_id`, `main_table`.`attribute_code`, `main_table`.`attribute_model`, `main_table`.`backend_model`, `main_table`.`backend_type`, `main_table`.`backend_table`, `main_table`.`frontend_input`, `main_table`.`source_model`, `additional_table`.`is_global`, `additional_table`.`is_html_allowed_on_front`, `additional_table`.`is_wysiwyg_enabled` FROM `eav_attribute` AS `main_table`
 INNER JOIN `catalog_eav_attribute` AS `additional_table` ON additional_table.attribute_id = main_table.attribute_id WHERE (main_table.entity_type_id = 4) AND (attribute_code IN('" .
            implode('\',\'', $this->_listOfAttributes) .
            "'))
";

        $config  = Mage::getConfig()->getResourceConnectionConfig("default_setup");

        $dbinfo = array('host' => $config->host,
            'user' => $config->username,
            'pass' => $config->password,
            'dbname' => $config->dbname
        );
        unset($config);

        $pdo = new PDO('mysql:host=' . $dbinfo['host'] .';dbname=' . $dbinfo['dbname'], $dbinfo['user'], $dbinfo['pass']);

        Boxalino_CemSearch_Model_Logger::saveMemoryTracking('info', 'Indexer', array('memory_usage' => memory_get_usage(true), 'method' => __METHOD__, 'description' => 'Products - connected to DB'));
        $readConnection = $pdo->prepare($query);
        $readConnection->execute();
        $results = $readConnection->fetchAll();
        Boxalino_CemSearch_Model_Logger::saveMemoryTracking('info', 'Indexer', array('memory_usage' => memory_get_usage(true), 'method' => __METHOD__, 'description' => 'Products - get info about attributes - after'));
        $attrFDB = array();
        $attrsFromDb = array(
            "int" => array(),
            "varchar" => array(),
            "text" => array(),
            "decimal" => array(),
        );
        Boxalino_CemSearch_Model_Logger::saveMemoryTracking('info', 'Indexer', array('memory_usage' => memory_get_usage(true), 'method' => __METHOD__, 'description' => 'Products - attributes preparing'));
        foreach($results as $r){
            $type = $r['backend_type'];
            if(isset($attrsFromDb[$type])){
                $attrsFromDb[$type][] = $r['attribute_id'];
                $attrFDB[$r['attribute_id']] = $r['attribute_code'];
            }
        }
        Boxalino_CemSearch_Model_Logger::saveMemoryTracking('info', 'Indexer', array('memory_usage' => memory_get_usage(true), 'method' => __METHOD__, 'description' => 'Products - attributes preparing done'));
        $countMax = $this->_storeConfig['maximum_population'];
        $localeCount = 0;

        $limit = $this->_storeConfig['export_chunk'];
        $count = $limit;
        $page = 1;
        $header = true;

        while($count >= $limit){


            if ($countMax > 0 && $this->_count >= $countMax) {
                break;
            }

            foreach ($this->group->getStores() as $store) {

                $storeId = $store->getId();

                $lang = Mage::app()->getStore($store->getId())->getConfig('boxalinoexporter/export_data/language');
                Boxalino_CemSearch_Model_Logger::saveMemoryTracking('info', 'Indexer', array('memory_usage' => memory_get_usage(true), 'method' => __METHOD__, 'description' => 'Products - fetch products - before'));
                $query = "SELECT `e`.* FROM `catalog_product_entity` AS `e` LIMIT $limit OFFSET " . (($page-1) * $limit);
                $readConnection = $pdo->prepare($query);
                $readConnection->execute();
                $results = $readConnection->fetchAll();
                Boxalino_CemSearch_Model_Logger::saveMemoryTracking('info', 'Indexer', array('memory_usage' => memory_get_usage(true), 'method' => __METHOD__, 'description' => 'Products - fetch products - after'));

                $products = array();
                $ids = "";
                foreach($results as $r){
                    $products[$r['entity_id']] = $r;
                    $ids .= $r['entity_id'] . ',';
                    $products[$r['entity_id']]['website'] = array();
                    $products[$r['entity_id']]['categories'] = array();
                }

                $count = count($results);

                $results = null;
                $readConnection = null;

                $ids = substr($ids, 0, -1);
                $query = "
SELECT `t_d`.`entity_id`, `t_d`.`attribute_id`, `t_d`.`value` AS `default_value`, `t_s`.`value` AS `store_value`, IF(t_s.value_id IS NULL, t_d.value, t_s.value) AS `value` FROM `catalog_product_entity_varchar` AS `t_d`
 LEFT JOIN `catalog_product_entity_varchar` AS `t_s` ON t_s.attribute_id = t_d.attribute_id AND t_s.entity_id = t_d.entity_id AND t_s.store_id = $storeId WHERE (t_d.entity_type_id = 4) AND (t_d.entity_id IN (" . $ids . ")) AND (t_d.attribute_id IN ('" . implode('\',\'', $attrsFromDb['varchar']) . "')) AND (t_d.store_id = 0) UNION ALL SELECT `t_d`.`entity_id`, `t_d`.`attribute_id`, `t_d`.`value` AS `default_value`, `t_s`.`value` AS `store_value`, IF(t_s.value_id IS NULL, t_d.value, t_s.value) AS `value` FROM `catalog_product_entity_text` AS `t_d`
 LEFT JOIN `catalog_product_entity_text` AS `t_s` ON t_s.attribute_id = t_d.attribute_id AND t_s.entity_id = t_d.entity_id AND t_s.store_id = $storeId WHERE (t_d.entity_type_id = 4) AND (t_d.entity_id IN (" . $ids . ")) AND (t_d.attribute_id IN ('" . implode('\',\'', $attrsFromDb['text']) . "')) AND (t_d.store_id = 0) UNION ALL SELECT `t_d`.`entity_id`, `t_d`.`attribute_id`, `t_d`.`value` AS `default_value`, `t_s`.`value` AS `store_value`, IF(t_s.value_id IS NULL, t_d.value, t_s.value) AS `value` FROM `catalog_product_entity_decimal` AS `t_d`
 LEFT JOIN `catalog_product_entity_decimal` AS `t_s` ON t_s.attribute_id = t_d.attribute_id AND t_s.entity_id = t_d.entity_id AND t_s.store_id = $storeId WHERE (t_d.entity_type_id = 4) AND (t_d.entity_id IN (" . $ids . ")) AND (t_d.attribute_id IN ('" . implode('\',\'', $attrsFromDb['decimal']) . "')) AND (t_d.store_id = 0) UNION ALL SELECT `t_d`.`entity_id`, `t_d`.`attribute_id`, `t_d`.`value` AS `default_value`, `t_s`.`value` AS `store_value`, IF(t_s.value_id IS NULL, t_d.value, t_s.value) AS `value` FROM `catalog_product_entity_int` AS `t_d`
 LEFT JOIN `catalog_product_entity_int` AS `t_s` ON t_s.attribute_id = t_d.attribute_id AND t_s.entity_id = t_d.entity_id AND t_s.store_id = $storeId WHERE (t_d.entity_type_id = 4) AND (t_d.entity_id IN (" . $ids . ")) AND (t_d.attribute_id IN ('" . implode('\',\'', $attrsFromDb['int']) . "')) AND (t_d.store_id = 0)
";
                Boxalino_CemSearch_Model_Logger::saveMemoryTracking('info', 'Indexer', array('memory_usage' => memory_get_usage(true), 'method' => __METHOD__, 'description' => 'Products - get attributes - before'));
                $readConnection = $pdo->prepare($query);
                $readConnection->execute();
                $results = $readConnection->fetchAll();

                Boxalino_CemSearch_Model_Logger::saveMemoryTracking('info', 'Indexer', array('memory_usage' => memory_get_usage(true), 'method' => __METHOD__, 'description' => 'Products - get attributes - after'));
                foreach($results as $r){

                    $products[$r['entity_id']][$attrFDB[$r['attribute_id']]] = $r['value'];

                }

                $results = null;
                $readConnection = null;

                Boxalino_CemSearch_Model_Logger::saveMemoryTracking('info', 'Indexer', array('memory_usage' => memory_get_usage(true), 'method' => __METHOD__, 'description' => 'Products - get stock  - before'));
                $query = "SELECT `cataloginventory_stock_status`.`product_id`, `cataloginventory_stock_status`.`stock_status` FROM `cataloginventory_stock_status` WHERE (product_id IN(" . $ids .")) AND (stock_id=1) AND (website_id=1)";

                $readConnection = $pdo->prepare($query);
                $readConnection->execute();
                $results = $readConnection->fetchAll();
                Boxalino_CemSearch_Model_Logger::saveMemoryTracking('info', 'Indexer', array('memory_usage' => memory_get_usage(true), 'method' => __METHOD__, 'description' => 'Products - get stock  - after'));


                foreach($results as $r){

                    $products[$r['product_id']]['stock_status'] = $r['stock_status'];

                }

                $results = null;
                $readConnection = null;

                Boxalino_CemSearch_Model_Logger::saveMemoryTracking('info', 'Indexer', array('memory_usage' => memory_get_usage(true), 'method' => __METHOD__, 'description' => 'Products - get products from website - before'));
                $query = "SELECT `catalog_product_website`.* FROM `catalog_product_website` WHERE (product_id IN (" . $ids ."))";

                $readConnection = $pdo->prepare($query);
                $readConnection->execute();
                $results = $readConnection->fetchAll();
                Boxalino_CemSearch_Model_Logger::saveMemoryTracking('info', 'Indexer', array('memory_usage' => memory_get_usage(true), 'method' => __METHOD__, 'description' => 'Products - get products from website - after'));

                foreach($results as $r){

                    $products[$r['product_id']]['website'][] = $r['website_id'];

                }

                $results = null;
                $readConnection = null;
                Boxalino_CemSearch_Model_Logger::saveMemoryTracking('info', 'Indexer', array('memory_usage' => memory_get_usage(true), 'method' => __METHOD__, 'description' => 'Products - get products connections - before'));

                $query = "select * from catalog_product_super_link WHERE (product_id IN (" . $ids ."))";
                $readConnection = $pdo->prepare($query);
                $readConnection->execute();
                $results = $readConnection->fetchAll();

                Boxalino_CemSearch_Model_Logger::saveMemoryTracking('info', 'Indexer', array('memory_usage' => memory_get_usage(true), 'method' => __METHOD__, 'description' => 'Products - get products connections - after'));
                foreach($results as $r){

                    $products[$r['product_id']]['parent_id'] = $r['parent_id'];

                }

                $results = null;
                $readConnection = null;
                Boxalino_CemSearch_Model_Logger::saveMemoryTracking('info', 'Indexer', array('memory_usage' => memory_get_usage(true), 'method' => __METHOD__, 'description' => 'Products - get categories - before'));

                $query = "select * from catalog_category_product WHERE (product_id IN (" . $ids ."))";
                $readConnection = $pdo->prepare($query);
                $readConnection->execute();
                $results = $readConnection->fetchAll();
                Boxalino_CemSearch_Model_Logger::saveMemoryTracking('info', 'Indexer', array('memory_usage' => memory_get_usage(true), 'method' => __METHOD__, 'description' => 'Products - get categories - after'));

                foreach($results as $r){

                    $products[$r['product_id']]['categories'][] = $r['category_id'];

                }

                foreach ($products as $pr) {
                    Boxalino_CemSearch_Model_Logger::saveMemoryTracking('info', 'Indexer', array('memory_usage' => memory_get_usage(true), 'method' => __METHOD__, 'description' => 'Products - start transform'));

                    $product = new stdClass();
                    foreach ($pr as $key => $value)
                    {
                        $product->$key = $value;
                    }

                    $pr = null;

                    if (count($product->website) == 0) {

                        $products = null;
                        continue;
                    }

                    $id = $product->entity_id;

                    $productParam = array();
                    $haveParent = false;

                    if ($this->getParentId($id) != null && $product->type_id == 'simple') {
                        $id = $this->getParentId($id);
                        $haveParent = true;
                    } else if ($product->visibility == 1 && $this->getParentId($id) == null) {
                        $product = null;
                        continue;
                    }

                    foreach ($attrs as $attr) {
                        Boxalino_CemSearch_Model_Logger::saveMemoryTracking('info', 'Indexer', array('memory_usage' => memory_get_usage(true), 'method' => __METHOD__, 'description' => 'Products - start attributes transform'));

                        if (isset($this->_attributesValuesByName[$attr])) {

                            $val = $this->escapeString($this->getFromClass($product, $attr));

                            $attr = $this->_helperSearch->sanitizeFieldName($attr);

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
                                        0 => $id, 1 => '2');
                                    $this->_transformedProducts['productsMtM'][$attr][] = array(/*'id' => count($this->_transformedProducts['productsMtM'][$attr])+1,*/
                                        0 => $id, 1 => '3');
                                    $this->_tmp[$attr][$id][] = $val;
                                } else {
                                    $this->_transformedProducts['productsMtM'][$attr][] = array(/*'id' => count($this->_transformedProducts['productsMtM'][$attr])+1,*/
                                        0 => $id, 1 => $val);
                                    $this->_tmp[$attr][$id][] = $val;
                                }
                            } else {
                                $this->_transformedProducts['productsMtM'][$attr] = array();
                                // If visibility is set everywhere (have value "4"), then we split it for value "2" and "3" (search and catalog separately)
                                if ($attr == 'visibility' && $val == '4') {
                                    $this->_transformedProducts['productsMtM'][$attr][] = array(/*'id' => 1,*/
                                        0 => $id, 1 => '2');
                                    $this->_transformedProducts['productsMtM'][$attr][] = array(/*'id' => 2,*/
                                        0 => $id, 1 => '3');
                                    $this->_tmp[$attr][$id] = array($val);
                                } else {
                                    $this->_transformedProducts['productsMtM'][$attr][] = array(/*'id' => 1,*/
                                        0 => $id, 1 => $val);
                                    $this->_tmp[$attr][$id] = array($val);
                                }
                            }

                            continue;
                        }


                        switch ($attr) {
                            case 'description':
                            case 'short_description':
                            case 'name':
                                $productParam[$attr . '_' . $lang] = $this->escapeString($this->getFromClass($product, $attr));
                                break;
                            case 'category_ids':
                                break;
                            default:
                                $productParam[$attr] = $this->escapeString($this->getFromClass($product, $attr));
                                break;
                        }
                        Boxalino_CemSearch_Model_Logger::saveMemoryTracking('info', 'Indexer', array('memory_usage' => memory_get_usage(true), 'method' => __METHOD__, 'description' => 'Products - end attributes transform'));

                    }

                    if ($haveParent) {
                        continue;
                    }

                    if (!isset($this->_transformedProducts['products'][$id])) {
                        if ($countMax > 0 && $this->_count >= $countMax) {
                            break;
                        }
                        $productParam['entity_id'] = $id;
                        $this->_transformedProducts['products'][$id] = $productParam;

                        //Add categories
                        if(isset($product->categories) && count($product->categories) > 0){
                            foreach ($product->categories as $cat) {

                                while ($cat != null) {
                                    $this->_transformedProducts['productsMtM']['categories'][] = array(0 => $id, 1 => $cat);
                                    if (isset($this->_transformedCategories[$cat]['parent_id'])) {
                                        $cat = $this->_transformedCategories[$cat]['parent_id'];
                                    } else {
                                        $cat = null;
                                    }
                                }
                            }
                        }
                        $this->_count++;
                        $localeCount++;

                    } elseif (isset($this->_transformedProducts['products'][$id])) {
                        $this->_transformedProducts['products'][$id] = array_merge($this->_transformedProducts['products'][$id], $productParam);

                    }
                    $productParam = null;
                    $product = null;

                    ksort($this->_transformedProducts['products'][$id]);
                    Boxalino_CemSearch_Model_Logger::saveMemoryTracking('info', 'Indexer', array('memory_usage' => memory_get_usage(true), 'method' => __METHOD__, 'description' => 'Products - end transform'));

                }
            }

            if(isset($this->_transformedProducts['products']) && count($this->_transformedProducts['products']) > 0){

                Boxalino_CemSearch_Model_Logger::saveMemoryTracking('info', 'Indexer', array('memory_usage' => memory_get_usage(true), 'method' => __METHOD__, 'description' => 'Products - validate names start'));

                $data = $this->_transformedProducts['products'];

                foreach ($this->_transformedProducts['productsMtM'] as $key => $val) {

                    $dataMtM = $val;

                    if($header){

                        if($key == 'categories'){
                            $dataMtM = array_merge(array(array("entity_id", "category_id")), $dataMtM);
                        } else{
                            $dataMtM = array_merge(array(array("entity_id", $key . "_id")), $dataMtM);
                        }
                        /** @TODO UNUSED! */
                        $csvFiles[] = "product_" . $this->_helperSearch->sanitizeFieldName($key);
                    }
                    Boxalino_CemSearch_Model_Logger::saveMemoryTracking('info', 'Indexer', array('memory_usage' => memory_get_usage(true), 'method' => __METHOD__, 'description' => 'Products - validate names end'));

                    Boxalino_CemSearch_Model_Logger::saveMemoryTracking('info', 'Indexer', array('memory_usage' => memory_get_usage(true), 'method' => __METHOD__, 'description' => 'Products - save to file with corrected name'));
                    $this->savePartToCsv( "product_" . $this->_helperSearch->sanitizeFieldName($key) . '.csv' , $dataMtM);
                    $this->_transformedProducts['productsMtM'][$key] = null;
                }

                if($header && count($data) > 0){
                    $data = array_merge(array(array_keys(end($data))), $data);
                    $header = false;
                }
                Boxalino_CemSearch_Model_Logger::saveMemoryTracking('info', 'Indexer', array('memory_usage' => memory_get_usage(true), 'method' => __METHOD__, 'description' => 'Products - save to file'));
                $this->savePartToCsv('products.csv', $data);

                $data = null;

                $this->_transformedProducts['products'] = array();
                $this->_transformedProducts['productsMtM'] = array();

            }

            $page++;

            $products = null;

        }

        $this->_transformedProducts = null;
        $this->_parentId = null;
        $this->_simpleIds = null;


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
            Boxalino_CemSearch_Model_Logger::saveMemoryTracking('info', 'Indexer', array('memory_usage' => memory_get_usage(true), 'method' => __METHOD__, 'description' => 'Customers - Countries are not loaded'));
            $collection = Mage::getModel('directory/country')->getCollection();
            Boxalino_CemSearch_Model_Logger::saveMemoryTracking('info', 'Indexer', array('memory_usage' => memory_get_usage(true), 'method' => __METHOD__, 'description' => 'Customers - Countries are loaded'));

            if ($this->_countries == null) {
                foreach ($collection as $country) {
                    $this->_countries[$country->getId()] = $country->getName();
                }
            }

            $limit = $this->_storeConfig['export_chunk'];
            $count = $limit;
            $page = 1;
            $header = true;

            do{
                Boxalino_CemSearch_Model_Logger::saveMemoryTracking('info', 'Indexer', array('memory_usage' => memory_get_usage(true), 'method' => __METHOD__, 'description' => 'Customers - load page '.$page));
                $customers_to_save = array();
                $customers = Mage::getModel('customer/customer')
                    ->getCollection()
                    ->setPageSize($limit)
                    ->setCurPage($page)
                    ->addAttributeToSelect('*');
                Boxalino_CemSearch_Model_Logger::saveMemoryTracking('info', 'Indexer', array('memory_usage' => memory_get_usage(true), 'method' => __METHOD__, 'description' => 'Customers - loaded page '.$page));

                foreach ($customers as $customer) {
                    Boxalino_CemSearch_Model_Logger::saveMemoryTracking('info', 'Indexer', array('memory_usage' => memory_get_usage(true), 'method' => __METHOD__, 'description' => 'Customers - Load billing address '));
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

                    $customers_to_save[] = array(
                        'customer_id' => $customer->getId(),
                        'gender' => $gender,
                        'dob' => $customer->getDob(),
                        'country' => !empty($countryCode) ? $this->_helperExporter->getCountry($countryCode)->getName() : '',
                        'zip' => !empty($billing) ? $billing->getPostcode() : ''
                    );

                }

                $data = $customers_to_save;

                if($header){
                    $data = array_merge(array(array_keys(end($customers_to_save))), $customers_to_save);
                    $header = false;
                }
                Boxalino_CemSearch_Model_Logger::saveMemoryTracking('info', 'Indexer', array('memory_usage' => memory_get_usage(true), 'method' => __METHOD__, 'description' => 'Customers - save to file'));
                $this->savePartToCsv('customers.csv', $data);

                $count = count($customers_to_save);
                $page++;

            } while($count >= $limit);
            $customers = null;
        }
        Boxalino_CemSearch_Model_Logger::saveMemoryTracking('info', 'Indexer', array('memory_usage' => memory_get_usage(true), 'method' => __METHOD__, 'description' => 'Customers - end of exporting '));
        return null;
    }

    abstract protected function _getCustomers();

    /**
     * @description Preparing transactions to export
     * @return array Transaction
     */
    protected function _exportTransactions()
    {
        Boxalino_CemSearch_Model_Logger::saveMemoryTracking('info', 'Indexer', array('memory_usage' => memory_get_usage(true), 'method' => __METHOD__, 'description' => 'Transactions - start of export'));
        if ($this->_storeConfig['export_transactions']) {

            $limit = $this->_storeConfig['export_chunk'];
            $count = $limit;
            $page = 1;
            $header = true;

            while($count >= $limit){
                Boxalino_CemSearch_Model_Logger::saveMemoryTracking('info', 'Indexer', array('memory_usage' => memory_get_usage(true), 'method' => __METHOD__, 'description' => 'Transactions - load page '.$page));

                $transactions_to_save = array();
                $transactions = Mage::getModel('sales/order')
//                    ->setStoreId($this->_storeId)
                    ->getCollection()
                    ->setPageSize($limit)
                    ->setCurPage($page)
                    ->addAttributeToSelect('*');
                Boxalino_CemSearch_Model_Logger::saveMemoryTracking('info', 'Indexer', array('memory_usage' => memory_get_usage(true), 'method' => __METHOD__, 'description' => 'Transactions - loaded page '.$page));

                foreach ($transactions as $transaction) {

                    if ($transaction->getStatus() == 'canceled') {
                        continue;
                    }

                    $configurable = array();

                    Boxalino_CemSearch_Model_Logger::saveMemoryTracking('info', 'Indexer', array('memory_usage' => memory_get_usage(true), 'method' => __METHOD__, 'description' => 'Transactions - get all items - before'));
                    $products = ($transaction->getAllItems());
                    Boxalino_CemSearch_Model_Logger::saveMemoryTracking('info', 'Indexer', array('memory_usage' => memory_get_usage(true), 'method' => __METHOD__, 'description' => 'Transactions - get all items - after'));

                    foreach ($products as $product) {

                        //is configurable
                        if ($product->getParentItemId() == null && $product->getProductType() != 'simple') {
                            $configurable[$product->getId()] = $product;
                            continue;
                        }

                        //is configurable - simple product
                        if (intval($product->getPrice()) == 0) {
                            if(isset($configurable[$product->getParentItemId()])){
                                $pid = $configurable[$product->getParentItemId()];
                            } else{
                                $pid = Mage::getModel('catalog/product')->load($product->getParentItemId());
                            }
                            $product->setOriginalPrice($pid->getOriginalPrice());
                            $product->setPrice($pid->getPrice());
                        }

                        $status = 0; // 0 - pending, 1 - confirmed, 2 - shipping

                        if ($transaction->getUpdatedAt() != $transaction->getCreatedAt()) {

                            switch ($transaction->getStatus()) {
                                case 'canceled':
                                    continue;
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
                        $transactions_to_save[] = array(
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

                    $configurable = null;
                    $products = null;
                }

                $data = $transactions_to_save;
                $count = count($transactions);

                if($header){
                    $data = array_merge(array(array_keys(end($transactions_to_save))), $transactions_to_save);
                    $header = false;
                }

                Boxalino_CemSearch_Model_Logger::saveMemoryTracking('info', 'Indexer', array('memory_usage' => memory_get_usage(true), 'method' => __METHOD__, 'description' => 'Transactions - save to file'));
                $this->savePartToCsv('transactions.csv', $data);

                $page++;

                $transactions = null;

            }

        }

        Boxalino_CemSearch_Model_Logger::saveMemoryTracking('info', 'Indexer', array('memory_usage' => memory_get_usage(true), 'method' => __METHOD__, 'description' => 'Transactions - end of export'));
        return null;
    }

    abstract protected function _getTransactions();

    abstract protected function _getIndexType();

    /**
     * @description Preparing files to send
     */
    protected function prepareFiles($website, &$categories = null,  &$tags = null )
    {

        //Prepare attributes
        $csvFiles = array();
        if (!file_exists("/tmp/boxalino")) {
            mkdir("/tmp/boxalino");
        }

        //create csv
        //save attributes
        foreach ($this->_attributesValuesByName as $attrName => $attrValues) {
            $csvFiles[] = $this->createCsv($this->_helperSearch->sanitizeFieldName($attrName), $attrValues);
        }

        //save categories
        if ($categories != null) {
            $csvFiles[] = $this->createCsv('categories', $categories);
            $categories = null;
        }

        //save tags
        if ($tags != null) {
            $csvFiles[] = $this->createCsv('tag', $tags);

            $loop = 1;
            foreach ($this->_getProductTags() as $product_id => $tag_id) {
                $csvdata[] = array('id' => $loop++, 'entity_id' => $product_id, 'tag_id' => $tag_id);
            }

            $csvFiles[] = $this->createCsv('product_tag', $csvdata);

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
    protected function createCsv($name, &$data)
    {
        $file = $name . '.csv';

        if(!is_array($data) || count($data) == 0){
            Mage::getModel('adminhtml/session')->addWarning("Data for $file have wrong format or is empty. " . "[" . gettype($data) . "]");
        }

        $csvdata = array_merge(array(array_keys(end($data))), $data);
        $csvdata[0][0] = $this->_helperSearch->sanitizeFieldName($csvdata[0][0]);

        $fh = fopen($this->_dir . '/' . $file, 'a');
        foreach ($csvdata as $dataRow) {
            fputcsv($fh, $dataRow, $this->_helperExporter->XML_DELIMITER, $this->_helperExporter->XML_ENCLOSURE);
        }
        fclose($fh);

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
            $tags = null;
        }

        return $this->_allProductTags;
    }

    protected function createXML($name)
    {

        $xml = new SimpleXMLElement('<root/>');

        $languages = $xml->addChild('languages');
        $containers = $xml->addChild('containers');

        //languages
        foreach ($this->_availableLanguages as $lang) {
            $language = $languages->addChild('language');
            $language->addAttribute('id', $lang);
        }

        //customers
        $tmp = $this->_helperExporter;
        $customerString = <<<XML
        <container id="customers" type="customers">
            <sources>
                <source type="item_data_file" id="customer_vals">
                    <file value="customers.csv"/>
                    <itemIdColumn value="customer_id"/>
                    <format value="$tmp->XML_FORMAT"/>
                    <encoding value="$tmp->XML_ENCODE"/>
                    <delimiter value="$tmp->XML_DELIMITER"/>
                    <enclosure value="$tmp->XML_ENCLOSURE_TEXT"/>
                    <escape value="$tmp->XML_ESCAPE"/>
                    <lineSeparator value="$tmp->XML_NEWLINE"/>
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
                    <format value="$tmp->XML_FORMAT"/>
                    <encoding value="$tmp->XML_ENCODE"/>
                    <delimiter value="$tmp->XML_DELIMITER"/>
                    <enclosure value="$tmp->XML_ENCLOSURE_TEXT"/>
                    <escape value="$tmp->XML_ESCAPE"/>
                    <lineSeparator value="$tmp->XML_NEWLINE"/>
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

            $attr = $this->_helperSearch->sanitizeFieldName($attr);

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
            $property->addAttribute('id', $this->_helperSearch->sanitizeFieldName($prop['id']));
            $property->addAttribute('type', $prop['ptype']);

            $transform = $property->addChild('transform');
            $logic = $transform->addChild('logic');
            $ls = $prop['name'] == null ? 'item_vals' : 'item_' . $prop['name'];
            $logic->addAttribute('source', $this->_helperSearch->sanitizeFieldName($ls));
            $logic->addAttribute('type', $prop['type']);
            if ($prop['has_lang'] == true) {
                foreach ($this->_availableLanguages as $lang) {
                    $field = $logic->addChild('field');
                    $field->addAttribute('column', $this->_helperSearch->sanitizeFieldName($prop['field']) . '_' . $lang);
                    $field->addAttribute('language', $lang);
                }
            } else {
                $logic->addChild('field')->addAttribute('column', $this->_helperSearch->sanitizeFieldName($prop['field']));
            }

            $params = $property->addChild('params');
            if ($prop['type'] != 'direct') {
                $params->addChild('referenceSource')->addAttribute('value', 'resource_' . $this->_helperSearch->sanitizeFieldName($prop['reference']));
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
        $xml->addChild('format')->addAttribute('value', $this->_helperExporter->XML_FORMAT);
        $xml->addChild('encoding')->addAttribute('value', $this->_helperExporter->XML_ENCODE);
        $xml->addChild('delimiter')->addAttribute('value', $this->_helperExporter->XML_DELIMITER);
        $xml->addChild('enclosure')->addAttribute('value', $this->_helperExporter->XML_ENCLOSURE);
        $xml->addChild('escape')->addAttribute('value', $this->_helperExporter->XML_ESCAPE);
        $xml->addChild('lineSeparator')->addAttribute('value', $this->_helperExporter->XML_NEWLINE);
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
        };

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

        $url = $this->_helperExporter->getXMLSyncUrl($this->_storeConfig['account_dev']);

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
            Mage::throwException($this->_helperExporter->getError($responseBody));;
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

        $url = $this->_helperExporter->getZIPSyncUrl($this->_storeConfig['account_dev']);

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
            $products = null;
        }

        return $this->_productsStockQty;
    }

    protected function savePartToCsv($file, &$data){

        if (!file_exists("/tmp/boxalino")) {
            mkdir("/tmp/boxalino");
        }

        //save
        if(!in_array($file, $this->_files)){
            $this->_files[] = $file;
        }

        $fh = fopen($this->_dir . '/' . $file, 'a');
        foreach ($data as $dataRow) {
            fputcsv($fh, $dataRow, $this->_helperExporter->XML_DELIMITER, $this->_helperExporter->XML_ENCLOSURE);
        }
        fclose($fh);
        $data = null;
        $fh = null;

    }

    public static function delTree($dir) {
        if(!file_exists($dir)){
            return;
        }
        $files = array_diff(scandir($dir), array('.','..'));
        foreach ($files as $file) {
            if(is_dir("$dir/$file")){
                delTree("$dir/$file");
            } else if(file_exists("$dir/$file")){
                @unlink("$dir/$file");
            }
//            (is_dir("$dir/$file")) ? delTree("$dir/$file") : @unlink("$dir/$file");
        }
        return rmdir($dir);
    }

    protected function escapeString($string){

        return htmlspecialchars(trim(preg_replace('/\s+/', ' ', $string)));

    }

    private static function clearMemory(&$obj){
        $obj = null;
    }

    private static function getFromClass(&$class, $val){

        if(isset($class->$val)){
            return $class->$val;
        } else{
            return '';
        }

    }

    /**
     * Load connection arrays if necessary.
     */
    private function loadProductLinks()
    {

        // If arrays already set - nothing to do here.
        if ($this->_isLoad) {
            return;
        }

        // Get database connection
        $connection = Mage::getSingleton('core/resource')->getConnection('core_read');
        $tableName = $connection->getTableName('catalog_product_super_link');
        // Get all data from `catalog_product_super_link`
        $query = 'select * from ' . $tableName;
        $this->_simples = array();
        $this->_configurables = array();

        // Iterate through collection
        foreach ($connection->fetchAll($query) as $row) {
            $productId = $row['product_id'];
            $parentId = $row['parent_id'];

            // Set simpleIds array if not set yet for specified parent.
            if (!isset($this->_simpleIds[$parentId])) {
                $this->_simpleIds[$parentId] = array();
            }
            // Add simple product to collection of parent.
            $this->_simpleIds[$parentId][] = $productId;
            // Add parent to simple product.
            $this->_parentId[$productId] = $parentId;
        }
        unset($connection);
        $this->_isLoad = true;
    }

    /**
     * Return parent id.
     *
     * @param null $productId
     * @return null|int|array
     */
    public function getParentId($productId = null)
    {
        // Load connections if necessary.
        $this->loadProductLinks();

        // If no product is specified - return whole array.
        if (!isset($productId)) {
            return $this->_parentId;
        }

        // If we have parent id for specified product - return it.
        if (isset($this->_parentId[$productId])) {
            return $this->_parentId[$productId];
        }

        // No parent - return null.
        return null;
    }

    /**
     * Return simple ids.
     *
     * @param null $productId
     * @return null|int|array
     */
    public function getSimpleIds($productId = null)
    {

        // Load connections if necessary.
        $this->loadProductLinks();

        // If no product is specified - return whole array.
        if (!isset($productId)) {
            return $this->_simpleIds;
        }

        // If we have simple ids for specified product - return it.
        if (isset($this->_simpleIds[$productId])) {
            return $this->_simpleIds[$productId];
        }
        // No simple ids - return null.
        return null;
    }

}