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

    /** @var array All tags of products */
    protected $_allProductTags = array();

    protected $_transformedCategories = array();

    protected $_transformedTags = array();

    protected $_transformedProducts = array();

    protected $_categoryParent = array();

    protected $_countries = null;

    protected $_availableLanguages = array();

    protected $_attributesValuesByName = array();

    protected $_files = array();

    protected $_count = 0;

    protected $_attrProdCount = array();

    /** @var int Actually used storeId */
    protected $_storeId = 0;

    protected $_dir = '/tmp/boxalino';

    protected $group = null;

    protected $_helperExporter = null;
    protected $_helperSearch = null;

    protected $_entityIds = null;

    /**
     * @description Start of reindex
     */
    public function reindexAll()
    {
        self::logMem('Indexer init');
        $this->_websiteExport();
        $this->_helperExporter->delTree($this->_dir);
        return $this;
    }

    /**
     * @description Declare what code have to do for Website scope
     * @return void
     */
    protected function _websiteExport()
    {
        $this->_helperExporter = Mage::helper('boxalinoexporter');
        $this->_helperSearch = Mage::helper('boxalinoexporter');
        self::logMem('Helpers init');
        foreach (Mage::app()->getWebsites() as $website) {

            $this->_helperExporter->delTree($this->_dir);
            self::logMem('After delTree');

            if (!$this->_isEnabled()) {
                continue;
            }

            $data = $this->_storeExport($website);

            self::logMem('something with attributes - before');

            foreach ($this->_listOfAttributes as $k => $attr) {
                if (
                    !isset($this->_attributesValuesByName[$attr]) ||
                    (isset($this->_attrProdCount[$attr]) &&
                        $this->_attrProdCount[$attr])
                ) {
                    continue;
                } else {
                    unset($this->_attributesValuesByName[$attr]);
                    unset($this->_listOfAttributes[$k]);
                }
            }

            self::logMem('something with attributes - after');
            $file = $this->prepareFiles($website, $data['categories'], $data['tags']);
            self::logMem('Push files');

            $this->pushXML($file);
            $this->pushZip($file);

            $this->_transformedCategories = array();
            $this->_transformedTags = array();
            $this->_transformedProducts = array();
            $this->_categoryParent = array();
            $this->_availableLanguages = array();
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
        $tags = array();
        self::logMem('Preparing data for website start');
        foreach ($website->getGroups() as $group) {

            $this->group = $group;

            foreach ($group->getStores() as $store) {
                self::logMem('Start store:' . $store->getId());
                $this->_prepareStoreConfig($store->getId());
                self::logMem('Configuration for store loaded');
                if ($this->_isEnabled()) {
                    $categories = $this->_exportCategories();
                    $tags = $this->_exportTags();
                    self::logMem('Without available languages');
                    $this->_availableLanguages[] = $this->_storeConfig['language'];
                    self::logMem('With available languages');

                }
            }

            if ($this->_isEnabled()) {
                $this->_exportCustomers();
                $this->_exportTransactions();
                $this->_exportProducts();
            }

        }

        return array(
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
        $this->_listOfAttributes = array(
            'entity_id',
            'name',
            'description',
            'short_description',
            'sku',
            'price',
            'special_price',
            'visibility',
            'category_ids',
        );

        $attributes = array();

        foreach (Mage::getResourceModel('catalog/product_attribute_collection')->getItems() as $at) {
            $attributes[] = $at->getAttributeCode();
        }

        if (isset($this->_storeConfig['additional_attributes']) && $this->_storeConfig['additional_attributes'] != '') {
            $fields = explode(',', $this->_storeConfig['additional_attributes']);
            foreach ($fields as $field) {

                if (!in_array($field, $attributes)) {
                    Mage::throwException("Attribute \"$field\" not exist!");
                }

                if ($field != null && strlen($field) > 0) {
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
                        $this->_attributesValues[$this->_storeId][$attribute][$option['value']] = $this->_helperSearch->escapeString($option['label']);

                        $value = intval($option['value']);
                        $name = 'value_' . $this->_storeConfig['language'];

                        if (isset($this->_attributesValuesByName[$attribute])) {

                            if (isset($this->_attributesValuesByName[$attribute][$value])) {
                                $this->_attributesValuesByName[$attribute][$value][$name] = /*strtolower*/
                                    $this->_helperSearch->escapeString($option['label']);
                            } else {
                                $this->_attributesValuesByName[$attribute][$value] = array($attribute . '_id' => $value, $name => /*strtolower*/
                                    $this->_helperSearch->escapeString($option['label']));
                            }

                        } else {
                            $this->_attributesValuesByName[$attribute] = array($value => array($attribute . '_id' => $value, $name => /*strtolower*/
                                $this->_helperSearch->escapeString($option['label'])));
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
        } else if (!isset($this->_storeConfig['enabled']) && Mage::getStoreConfig('Boxalino_General/general/enabled')) {
            return true;
        }

        return false;
    }

    /**
     * @description Preparing categories to export
     * @return array Categories
     */
    protected function _exportCategories()
    {
        if ($this->_storeConfig['export_categories']) {
            self::logMem('Categories are not loaded');
            $categories = $this->_getCategories();
            self::logMem('Categories are loaded');
            foreach ($categories as $category) {

                if ($category->getParentId() == null) {
                    continue;
                }

                if (isset($this->_transformedCategories[$category->getId()])) {
                    $this->_transformedCategories[$category->getId()]['value_' . $this->_storeConfig['language']] = $this->_helperSearch->escapeString($category->getName());
                } else {
                    $parentId = null;
                    if ($category->getParentId() != 0) {
                        $parentId = $category->getParentId();
                    }
                    $this->_transformedCategories[$category->getId()] = array('category_id' => $category->getId(), 'parent_id' => $parentId, 'value_' . $this->_storeConfig['language'] => $this->_helperSearch->escapeString($category->getName()));
                }
            }
            $categories = null;
            self::logMem('Categories are returned for data saving');
            return $this->_transformedCategories;
        }
        return null;
    }

    /**
     * @description Get list of all categories
     * @return object All categories for Store
     */
    protected function _getCategories()
    {
        $categories = Mage::getModel('catalog/category')->setStoreId($this->_storeId)->getCollection()->addAttributeToSelect('*');
        return $categories;
    }

    /**
     * @description Preparing tags to export
     * @return array Tags
     *
     */
    protected function _exportTags()
    {

        if ($this->_storeConfig['export_tags']) {
            self::logMem('Tags are not loaded');
            $tags = $this->_helperExporter->getAllTags();
            self::logMem('Tags are loaded');

            foreach ($tags as $id => $tag) {
                if (isset($this->_transformedTags[$id])) {
                    $this->_transformedTags[$id]['value_' . $this->_storeConfig['language']] = $tag;
                } else {
                    $this->_transformedTags[$id] = array('tag_id' => $id, 'value_' . $this->_storeConfig['language'] => $tag);
                }
            }

            $tags = null;
            self::logMem('Tags are returned for data saving');
            return $this->_transformedTags;
        }

        return null;
    }

    /**
     * @description Preparing products to export
     * @return void
     */
    protected function _exportProducts()
    {
        self::logMem('Products - start of export');
        $attrs = $this->_listOfAttributes;
        self::logMem('Products - get info about attributes - before');

        $db = $this->_getReadAdapter();
        $select = $db->select()
            ->from(
                array('main_table' => 'eav_attribute'),
                array(
                    'attribute_id',
                    'attribute_code',
                    'backend_type',
                )
            )
            ->joinInner(
                array('additional_table' => 'catalog_eav_attribute'),
                'additional_table.attribute_id = main_table.attribute_id'
            )
            ->where('main_table.entity_type_id = ?', $this->getEntityIdFor('catalog_product'))
            ->where('main_table.attribute_code IN(?)', $attrs);

        self::logMem('Products - connected to DB, built attribute info query');

        $attrsFromDb = array(
            'int'     => array(),
            'varchar' => array(),
            'text'    => array(),
            'decimal' => array(),
        );

        foreach ($db->fetchAll($select) as $r) {
            $type = $r['backend_type'];
            if (isset($attrsFromDb[$type])) {
                $attrsFromDb[$type][] = $r['attribute_id'];
            }
        }
        self::logMem('Products - attributes preparing done');

        $countMax = $this->_storeConfig['maximum_population'];
        $localeCount = 0;

        $limit = $this->_storeConfig['export_chunk'];
        $count = $limit;
        $page = 1;
        $header = true;

        $stores = array();
        foreach ($this->group->getStores() as $store){

            $stores[$store->getId()]['id'] = $store->getId();
            $stores[$store->getId()]['lang'] = Mage::app()->getStore($store->getId())->getConfig('boxalinoexporter/export_data/language');

        }

        //prepare files
        $filesMtM = array();
        $tmp =array_keys($this->_attributesValuesByName);
        $tmp[] = 'categories';
        foreach($tmp as $attr){

            $key = $attr;

            if ($attr == 'categories') {
                $key = 'category';
            }

            if (!file_exists('/tmp/boxalino')) {
                mkdir('/tmp/boxalino');
            }

            $file = 'product_' . $attr . '.csv';

            //save
            if (!in_array($file, $this->_files)) {
                $this->_files[] = $file;
            }

            $fh = fopen($this->_dir . '/' . $file, 'a');
            fputcsv($fh, array('entity_id', $key . '_id'), $this->_helperExporter->XML_DELIMITER, $this->_helperExporter->XML_ENCLOSURE);

            $filesMtM[$attr] = $fh;

        }
        $tmp = null;

        while($count >= $limit) {
            if ($countMax > 0 && $this->_count >= $countMax) {
                break;
            }

            foreach ($stores as $store) {

                $storeId = $store['id'];
                $lang = $store['lang'];

                self::logMem('Products - fetch products - before');
                $select = $db->select()
                    ->from(
                        array('e' => 'catalog_product_entity')
                    )
                    ->limit($limit, ($page - 1) * $limit);

                $this->_getIndexType() == 'delta' ? $select->where('created_at >= ? OR updated_at >= ?', $this->_getLastIndex()) : '';

                self::logMem('Products - fetch products - after');

                $products = array();
                $ids = array();
                $count = 0;
                foreach ($db->fetchAll($select) as $r) {
                    $products[$r['entity_id']] = $r;
                    $ids[] = $r['entity_id'];
                    $products[$r['entity_id']]['website'] = array();
                    $products[$r['entity_id']]['categories'] = array();
                    $count++;
                }

                self::logMem('Products - get attributes - before');
                $columns = array(
                    'entity_id',
                    'attribute_id',
                );
                $joinCondition = $db->quoteInto('t_s.attribute_id = t_d.attribute_id AND t_s.entity_id = t_d.entity_id AND t_s.store_id = ?', $storeId);
                $joinColumns = array('value' => 'IF(t_s.value_id IS NULL, t_d.value, t_s.value)');

                $select1 = $db->select()
                    ->joinLeft(array('ea' => 'eav_attribute'), 't_d.attribute_id = ea.attribute_id', 'ea.attribute_code')
                    ->where('t_d.store_id = ?', 0)
                    ->where('t_d.entity_type_id = ?', $this->getEntityIdFor('catalog_product'))
                    ->where('t_d.entity_id IN(?)', $ids);
                $select2 = clone $select1;
                $select3 = clone $select1;
                $select4 = clone $select1;

                $select1->from(
                    array('t_d' => 'catalog_product_entity_varchar'),
                    $columns
                )
                    ->joinLeft(
                        array('t_s' => 'catalog_product_entity_varchar'),
                        $joinCondition,
                        $joinColumns
                    )
                    ->where('t_d.attribute_id IN(?)', $attrsFromDb['varchar']);
                $select2->from(
                    array('t_d' => 'catalog_product_entity_text'),
                    $columns
                )
                    ->joinLeft(
                        array('t_s' => 'catalog_product_entity_text'),
                        $joinCondition,
                        $joinColumns
                    )
                    ->where('t_d.attribute_id IN(?)', $attrsFromDb['text']);
                $select3->from(
                    array('t_d' => 'catalog_product_entity_decimal'),
                    $columns
                )
                    ->joinLeft(
                        array('t_s' => 'catalog_product_entity_decimal'),
                        $joinCondition,
                        $joinColumns
                    )
                    ->where('t_d.attribute_id IN(?)', $attrsFromDb['decimal']);
                $select4->from(
                    array('t_d' => 'catalog_product_entity_int'),
                    $columns
                )
                    ->joinLeft(
                        array('t_s' => 'catalog_product_entity_int'),
                        $joinCondition,
                        $joinColumns
                    )
                    ->where('t_d.attribute_id IN(?)', $attrsFromDb['int']);

                $select = $db->select()
                    ->union(
                        array($select1, $select2, $select3, $select4),
                        Zend_Db_Select::SQL_UNION_ALL
                    );

                $select1 = null;
                $select2 = null;
                $select3 = null;
                $select4 = null;
                foreach ($db->fetchAll($select) as $r) {
                    $products[$r['entity_id']][$r['attribute_code']] = $r['value'];
                }
                self::logMem('Products - get attributes - after');

                self::logMem('Products - get stock  - before');
                $select = $db->select()
                    ->from(
                        'cataloginventory_stock_status',
                        array(
                            'product_id',
                            'stock_status',
                        )
                    )
                    ->where('stock_id = ?', 1)
                    ->where('website_id = ?', 1)
                    ->where('product_id IN(?)', $ids);
                foreach ($db->fetchAll($select) as $r) {
                    $products[$r['product_id']]['stock_status'] = $r['stock_status'];
                }
                self::logMem('Products - get stock  - after');

                self::logMem('Products - get products from website - before');
                $select = $db->select()
                    ->from(
                        'catalog_product_website',
                        array(
                            'product_id',
                            'website_id',
                        )
                    )
                    ->where('product_id IN(?)', $ids);
                foreach ($db->fetchAll($select) as $r) {
                    $products[$r['product_id']]['website'][] = $r['website_id'];
                }
                self::logMem('Products - get products from website - after');

                self::logMem('Products - get products connections - before');
                $select = $db->select()
                    ->from(
                        'catalog_product_super_link',
                        array(
                            'product_id',
                            'parent_id',
                        )
                    )
                    ->where('product_id IN(?)', $ids);
                foreach ($db->fetchAll($select) as $r) {
                    $products[$r['product_id']]['parent_id'] = $r['parent_id'];
                }
                self::logMem('Products - get products connections - after');

                self::logMem('Products - get categories - before');
                $select = $db->select()
                    ->from(
                        'catalog_category_product',
                        array(
                            'product_id',
                            'category_id',
                        )
                    )
                    ->where('product_id IN(?)', $ids);
                $ids = null;
                foreach ($db->fetchAll($select) as $r) {
                    $products[$r['product_id']]['categories'][] = $r['category_id'];
                }
                $select = null;
                self::logMem('Products - get categories - after');

                foreach ($products as $product) {
                    self::logMem('Products - start transform');

                    if (count($product['website']) == 0) {
                        $product = null;
                        continue;
                    }

                    $id = $product['entity_id'];

                    $productParam = array();
                    $haveParent = false;

                    if (array_key_exists('parent_id', $product)) {
                        $id = $product['parent_id'];
                        $haveParent = true;
                    } else if ($product['visibility'] == 1 && !array_key_exists('parent_id', $product)) {
                        $product = null;
                        continue;
                    }

                    foreach ($attrs as $attr) {
                        self::logMem('Products - start attributes transform');

                        if (isset($this->_attributesValuesByName[$attr])) {

                            $val = array_key_exists($attr, $product) ? $this->_helperSearch->escapeString($product[$attr]) : '';
                            if ($val == null) {
                                continue;
                            }

                            $attr = $this->_helperSearch->sanitizeFieldName($attr);

                            $this->_attrProdCount[$attr] = true;

                            // if visibility is set everywhere (have value "4"),
                            // then we split it for value "2" and "3" (search and catalog separately)
                            if ($attr == 'visibility' && $val == '4') {
                                fputcsv($filesMtM[$attr], array($id, '2'), $this->_helperExporter->XML_DELIMITER, $this->_helperExporter->XML_ENCLOSURE);
                                fputcsv($filesMtM[$attr], array($id, '3'), $this->_helperExporter->XML_DELIMITER, $this->_helperExporter->XML_ENCLOSURE);

                            } else {
                                fputcsv($filesMtM[$attr], array($id, $val), $this->_helperExporter->XML_DELIMITER, $this->_helperExporter->XML_ENCLOSURE);
                            }


                            $val = null;

                            continue;
                        }

                        $val = array_key_exists($attr, $product) ? $this->_helperSearch->escapeString($product[$attr]) : '';
                        switch ($attr) {
                            case 'category_ids':
                                break;
                            case 'description':
                            case 'short_description':
                            case 'name':
                                $productParam[$attr . '_' . $lang] = $val;
                                break;
                            default:
                                $productParam[$attr] = $val;
                                break;
                        }
                        self::logMem('Products - end attributes transform');

                    }

                    if ($haveParent) {
                        $product = null;
                        continue;
                    }

                    if (!isset($this->_transformedProducts['products'][$id])) {
                        if ($countMax > 0 && $this->_count >= $countMax) {
                            $product = null;
                            $products = null;
                            break;
                        }
                        $productParam['entity_id'] = $id;
                        $this->_transformedProducts['products'][$id] = $productParam;

                        //Add categories
                        if (isset($product['categories']) && count($product['categories']) > 0) {
                            foreach ($product['categories'] as $cat) {

                                while ($cat != null) {
                                    fputcsv($filesMtM['categories'], array($id, $cat), $this->_helperExporter->XML_DELIMITER, $this->_helperExporter->XML_ENCLOSURE);
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
                    self::logMem('Products - end transform');

                }
            }

            if (isset($this->_transformedProducts['products']) && count($this->_transformedProducts['products']) > 0) {

                self::logMem('Products - validate names start');

                $data = $this->_transformedProducts['products'];

                if ($header && count($data) > 0) {
                    $data = array_merge(array(array_keys(end($data))), $data);
                    $header = false;
                }
                self::logMem('Products - save to file');
                $this->savePartToCsv('products.csv', $data);
                $data = null;
                $this->_transformedProducts['products'] = null;
                $this->_transformedProducts['products'] = array();

            }

            $page++;

            $products = null;

        }

        $attrFDB = null;
        $attrsFromDb = null;
        $attrs = null;
        $this->_transformedProducts = null;
        $db = null;

        //close file
        foreach($filesMtM as $f){
            fclose($f);
        }


    }

    /**
     * @description Preparing customers to export
     * @return void
     *
     */
    protected function _exportCustomers()
    {

        if (!$this->_storeConfig['export_customers']) {
            return;
        }

        self::logMem('Customers - Countries are not loaded');
        $countryCollection = Mage::getModel('directory/country')->getCollection();
        self::logMem('Customers - Countries are loaded');

        if ($this->_countries == null) {
            foreach ($countryCollection as $country) {
                $this->_countries[$country->getId()] = $country->getName();
            }
        }
        $countryCollection = null;

        $limit = $this->_storeConfig['export_chunk'];
        $count = $limit;
        $page = 1;
        $header = true;

        $attrsFromDb = array(
            'int' => array(),
            'varchar' => array(),
            'datetime' => array(),
        );

        $db = $this->_getReadAdapter();
        $select = $db->select()
            ->from(
                array('main_table' => 'eav_attribute'),
                array(
                    'aid' => 'attribute_id',
                    'attribute_code',
                    'backend_type',
                )
            )
            ->joinInner(
                array('additional_table' => 'customer_eav_attribute'),
                'additional_table.attribute_id = main_table.attribute_id'
            )
            ->joinLeft( // @todo is this left join still necessary?
                array('scope_table' => 'customer_eav_attribute_website'),
                'scope_table.attribute_id = main_table.attribute_id AND ' .
                'scope_table.website_id = ' . $this->group->getWebsiteId()
            )
            ->where('main_table.entity_type_id = ?', $this->getEntityIdFor('customer'))
            ->where('attribute_code IN ("dob", "gender")');

        foreach ($db->fetchAll($select) as $attr) {
            if (isset($attrsFromDb[$attr['backend_type']])) {
                $attrsFromDb[$attr['backend_type']][] = $attr['aid'];
            }
        }

        do {
            self::logMem("Customers - load page $page");
            $customers_to_save = array();

            $customers = array();

            $select = $db->select()
                ->from(
                    'customer_entity',
                    array('entity_id', 'created_at', 'updated_at')
                )
                ->where('entity_type_id = ?', '1')->limit($limit, ($page - 1) * $limit);

            $this->_getIndexType() == 'delta' ? $select->where('created_at >= ? OR updated_at >= ?', $this->_getLastIndex()) : '';

            foreach ($db->fetchAll($select) as $r) {
                $customers[$r['entity_id']] = array('id' => $r['entity_id']);
            }

            $ids = array_keys($customers);
            $columns = array(
                'entity_id',
                'attribute_id',
                'value',
            );

            $select = $db->select()
                ->joinLeft(array('ea' => 'eav_attribute'), 'ce.attribute_id = ea.attribute_id', 'ea.attribute_code')
                ->where('ce.entity_type_id = ?', 1)
                ->where('ce.entity_id IN(?)', $ids);

            $select1 = null;
            $select2 = null;
            $select3 = null;

            $selects = array();

            if(count($attrsFromDb['varchar']) > 0){
                $select1 = clone $select;
                $select1->from(array('ce' => 'customer_entity_varchar'), $columns)
                    ->where('ce.attribute_id IN(?)', $attrsFromDb['varchar']);
                $selects[] = $select1;
            }

            if(count($attrsFromDb['int']) > 0){
                $select2 = clone $select;
                $select2->from(array('ce' => 'customer_entity_int'), $columns)
                    ->where('ce.attribute_id IN(?)', $attrsFromDb['int']);
                $selects[] = $select2;
            }

            if(count($attrsFromDb['datetime']) > 0){
                $select3 = clone $select;
                $select3->from(array('ce' => 'customer_entity_datetime'), $columns)
                    ->where('ce.attribute_id IN(?)', $attrsFromDb['datetime']);
                $selects[] = $select3;
            }

            $select = $db->select()
                ->union(
                    $selects,
                    Zend_Db_Select::SQL_UNION_ALL
                );

            foreach ($db->fetchAll($select) as $r) {
                $customers[$r['entity_id']][$r['attribute_code']] = $r['value'];
            }

            $select = null;
            $select1 = null;
            $select2 = null;
            $select3 = null;
            $selects = null;

            $select = $db->select()
                ->from(
                    'eav_attribute',
                    array(
                        'attribute_id',
                        'attribute_code',
                    )
                )
                ->where('entity_type_id = ?', $this->getEntityIdFor('customer_address'))
                ->where('attribute_code IN ("country_id","postcode")');

            $addressAttr = array();
            foreach ($db->fetchAll($select) as $r) {
                $addressAttr[$r['attribute_id']] = $r['attribute_code'];
            }
            $addressIds = array_keys($addressAttr);

            self::logMem('Customers - loaded page ' . $page);

            foreach ($customers as $customer) {
                self::logMem('Customers - Load billing address ');
                $id = $customer['id'];

                $select = $db->select()
                    ->from(
                        'customer_address_entity',
                        array('entity_id')
                    )
                    ->where('entity_type_id = ?', 2)
                    ->where('parent_id = ?', $id);

                $select = $db->select()
                    ->from(
                        'customer_address_entity_varchar',
                        array('attribute_id', 'value')
                    )
                    ->where('entity_type_id = ?', $this->getEntityIdFor('customer_address'))
                    ->where('entity_id = ?', $select)
                    ->where('attribute_id IN(?)', $addressIds);

                $billingResult = array();
                foreach ($db->fetchAll($select) as $br) {
                    if (in_array($br['attribute_id'], $addressIds)) {
                        $billingResult[$addressAttr[$br['attribute_id']]] = $br['value'];
                    }
                }

                $countryCode = null;
                if (isset($billingResult['country_id'])) {
                    $countryCode = $billingResult['country_id'];
                }

                if (array_key_exists('gender', $customer)) {
                    if ($customer['gender'] % 2 == 0) {
                        $gender = 'female';
                    } else {
                        $gender = 'male';
                    }
                } else {
                    $gender = '';
                }

                $customers_to_save[] = array(
                    'customer_id' => $id,
                    'gender' => $gender,
                    'dob' => array_key_exists('dob', $customer) ? $customer['dob'] : '',
                    'country' => !empty($countryCode) ? $this->_helperExporter->getCountry($countryCode)->getName() : '',
                    'zip' => array_key_exists('postcode', $billingResult) ? $billingResult['postcode'] : '',
                );

            }

            $data = $customers_to_save;

            if (count($customers) == 0 && $header) {
                return null;
            }

            if ($header) {
                $data = array_merge(array(array_keys(end($customers_to_save))), $customers_to_save);
                $header = false;
            }
            self::logMem('Customers - save to file');
            $this->savePartToCsv('customers.csv', $data);
            $data = null;

            $count = count($customers_to_save);
            $page++;

        } while ($count >= $limit);
        $customers = null;

        self::logMem('Customers - end of exporting');
    }

    /**
     * @description Preparing transactions to export
     * @return void
     */
    protected function _exportTransactions()
    {
        if (!$this->_storeConfig['export_transactions']) {
            return;
        }

        self::logMem('Transactions - start of export');
        $db = $this->_getReadAdapter();

        $limit = $this->_storeConfig['export_chunk'];
        $count = $limit;
        $page = 1;
        $header = true;

        while ($count >= $limit) {
            self::logMem('Transactions - load page ' . $page);
            $transactions_to_save = array();
            $configurable = array();

            $select = $db
                ->select()
                ->from(
                    array('order' => 'sales_flat_order'),
                    array(
                        'entity_id',
                        'status',
                        'updated_at',
                        'created_at',
                        'customer_id',
                        'base_subtotal',
                        'shipping_amount',
                    )
                )
                ->joinLeft(
                    array('item' => 'sales_flat_order_item'),
                    'order.entity_id = item.order_id',
                    array(
                        'product_id',
                        'product_options',
                        'price',
                        'original_price',
                        'product_type',
                        'qty_ordered',
                    )
                )
                ->where('order.status <> ?', 'canceled')
                ->order(array('order.entity_id', 'item.product_type'))
                ->limit($limit, ($page - 1) * $limit);

            $this->_getIndexType() == 'delta' ? $select->where('order.created_at >= ? OR order.updated_at >= ?', $this->_getLastIndex()) : '';

            $transactions = $db->fetchAll($select);
            self::logMem("Transactions - loaded page $page");

            foreach ($transactions as $transaction) {
                //is configurable
                if ($transaction['product_type'] == 'configurable') {
                    $configurable[$transaction['product_id']] = $transaction;
                    continue;
                }

                $productOptions = unserialize($transaction['product_options']);

                //is configurable - simple product
                if (intval($transaction['price']) == 0 && $transaction['product_type'] == 'simple') {
                    if (isset($configurable[$productOptions['info_buyRequest']['product']])) {
                        $pid = $configurable[$productOptions['info_buyRequest']['product']];

                        $transaction['original_price'] = $pid['original_price'];
                        $transaction['price'] = $pid['price'];
                    } else {
                        $pid = Mage::getModel('catalog/product')->load($productOptions['info_buyRequest']['product']);

                        $transaction['original_price'] = ($pid->getPrice());
                        $transaction['price'] = ($pid->getPrice());

                        $tmp = array();
                        $tmp['original_price'] = $transaction['original_price'];
                        $tmp['price'] = $transaction['price'];

                        $configurable[$productOptions['info_buyRequest']['product']] = $tmp;

                        $pid = null;
                        $tmp = null;
                    }
                }

                $status = 0; // 0 - pending, 1 - confirmed, 2 - shipping
                if ($transaction['updated_at'] != $transaction['created_at']) {
                    switch ($transaction['status']) {
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

                $transactions_to_save[] = array(
                    'order_id'          => $transaction['entity_id'],
                    'entity_id'         => $transaction['product_id'],
                    'customer_id'       => array_key_exists('customer_id', $transaction) ? $transaction['customer_id'] : '',
                    'price'             => $transaction['original_price'],
                    'discounted_price'  => $transaction['price'],
                    'quantity'          => $transaction['qty_ordered'],
                    'total_order_value' => ($transaction['base_subtotal'] + $transaction['shipping_amount']),
                    'shipping_costs'    => $transaction['shipping_amount'],
                    'order_date'        => $transaction['created_at'],
                    'confirmation_date' => $status == 1 ? $transaction['updated_at'] : null,
                    'shipping_date'     => $status == 2 ? $transaction['updated_at'] : null,
                    'status'            => $transaction['status'],
                );
            }

            $data = $transactions_to_save;
            $count = count($transactions);

            $configurable = null;
            $transactions = null;

            if ($count == 0 && $header) {
                return;
            }

            if ($header) {
                $data = array_merge(array(array_keys(end($transactions_to_save))), $transactions_to_save);
                $header = false;
            }

            self::logMem('Transactions - save to file');
            $this->savePartToCsv('transactions.csv', $data);
            $data = null;

            $page++;

        }

        self::logMem('Transactions - end of export');
    }

    /**
     * @return string Index type
     */
    protected function _getIndexType()
    {
        return static::INDEX_TYPE; // access via late static binding (PHP 5.3)
    }

    /**
     * @description Preparing files to send
     */
    protected function prepareFiles($website, &$categories = null, &$tags = null)
    {

        //Prepare attributes
        $csvFiles = array();
        if (!file_exists('/tmp/boxalino')) {
            mkdir('/tmp/boxalino');
        }

        //create csvs
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
        if ($tags != null && $this->_getProductTags() != null) {
            $csvFiles[] = $this->createCsv('tag', $tags);

            $loop = 1;
            foreach ($this->_getProductTags() as $product_id => $tag_id) {
                $csvdata[] = array('id' => $loop++, 'entity_id' => $product_id, 'tag_id' => $tag_id);
            }

            $csvFiles[] = $this->createCsv('product_tag', $csvdata);
        }
        //csvs done

        //Create name for file
        $exportFile = '/tmp/boxalino/' . $this->_storeConfig['di_username'];
        $csvFiles = array_filter($csvFiles);

        //Create xml
        $this->createXML($exportFile . '.xml', ($tags != null && $this->_getProductTags() != null) ? true : false);

        //Create zip
        $this->createZip($exportFile . '.zip', array_filter($this->_files), $exportFile . '.xml');

        return $exportFile;
    }

    /**
     * @param $name
     * @param $data
     * @return string
     */
    protected function createCsv($name, &$data)
    {
        $file = $name . '.csv';

        if (!is_array($data) || count($data) == 0) {
            Mage::getModel('adminhtml/session')->addWarning("Data for $file is not an array or is empty. [" . gettype($data) . ']');
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

    protected function createXML($name, $withTag)
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
        //#########################################################################

        //product source
        $source = $sources->addChild('source');
        $source->addAttribute('id', 'item_vals');
        $source->addAttribute('type', 'item_data_file');

        $source->addChild('file')->addAttribute('value', 'products.csv');
        $source->addChild('itemIdColumn')->addAttribute('value', 'entity_id');

        $this->sxml_append_options($source);
        //#########################################################################

        $attrs = array_keys($this->_attributesValuesByName);
        if ($this->_storeConfig['export_tags'] && $withTag) {
            $attrs[] = 'tag';

        }

        foreach ($attrs as $attr) {

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

        //########################################################################
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
        //#########################################################################

        //property
        $properties = $products->addChild('properties');
        $props = $this->prepareProperties($withTag);

        foreach ($props as $prop) {
            if ($prop['id'] == 'entity_id') {

            }

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
        //##################################

        //##################################

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
    }

    /**
     * @description add xmlElement to other xmlElement
     * @param SimpleXMLElement $to
     * @param SimpleXMLElement $from
     */
    protected function sxml_append(SimpleXMLElement $to, SimpleXMLElement $from)
    {
        $toDom = dom_import_simplexml($to);
        $fromDom = dom_import_simplexml($from);
        $toDom->appendChild($toDom->ownerDocument->importNode($fromDom, true));
    }

    /**
     * @desciption add default xmlElements
     * @param SimpleXMLElement $xml
     */
    protected function sxml_append_options(SimpleXMLElement &$xml)
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
    protected function prepareProperties($withTag)
    {

        $properties = array();

        $attrs = $this->_listOfAttributes;

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
                    'id'        => $attr,
                    'name'      => $attr,
                    'ptype'     => 'text',
                    'type'      => 'reference',
                    'field'     => $attr . '_id',
                    'has_lang'  => false,
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
                    'id'        => $attr,
                    'name'      => null,
                    'ptype'     => $ptype,
                    'type'      => $type,
                    'field'     => $field,
                    'has_lang'  => $lang,
                    'reference' => $ref
                );
            }
        }
        //tag
        if ($this->_storeConfig['export_tags'] && $withTag) {
            $properties[] = array(
                'id'        => 'tag',
                'name'      => 'tag',
                'ptype'     => 'text',
                'type'      => 'reference',
                'field'     => 'tag_id',
                'has_lang'  => false,
                'reference' => 'tag'
            );
        }

        //categories
        if ($this->_storeConfig['export_categories']) {
            $properties[] = array(
                'id'        => 'category',
                'name'      => 'categories',   //property id
                'ptype'     => 'hierarchical', //property type
                'type'      => 'reference',    //logic type
                'field'     => 'category_id',  //field colummn
                'has_lang'  => false,
                'reference' => 'categories'
            );
        }
        $properties[] = array(
            'id'        => 'product_entity_id',
            'name'      => null,
            'ptype'     => 'string',
            'type'      => 'direct',
            'field'     => 'entity_id',
            'has_lang'  => false,
            'reference' => null
        );

        return $properties;
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
            'username' => $this->_storeConfig['di_username'],
            'password' => $this->_storeConfig['di_password'],
            'account'  => $this->_storeConfig['di_account'],
            'template' => 'standard_source',
            'xml'      => file_get_contents($file . '.xml')
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
        curl_setopt($s, CURLOPT_ENCODING, '');
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
            'username' => $this->_storeConfig['di_username'],
            'password' => $this->_storeConfig['di_password'],
            'account'  => $this->_storeConfig['di_account'],
            'dev'      => $this->_storeConfig['account_dev'] == 0 ? 'false' : 'true',
            'delta'    => $this->_getIndexType() == 'delta' ? 'true' : 'false', // I know...
            'data'     => "@$file.zip;type=application/zip",
        );

        $url = $this->_helperExporter->getZIPSyncUrl($this->_storeConfig['account_dev']);

        return $this->pushFile($fields, $url, 'zip');
    }

    protected function savePartToCsv($file, &$data)
    {

        if (!file_exists('/tmp/boxalino')) {
            mkdir('/tmp/boxalino');
        }

        //save
        if (!in_array($file, $this->_files)) {
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

    /**
     * Store memory tracking information in log file, if enabled
     */
    private static function logMem($message)
    {
        $callers = debug_backtrace();
        Boxalino_CemSearch_Model_Logger::saveMemoryTracking(
            'info',
            'Indexer',
            array(
                'memory_usage' => memory_get_usage(true),
                'method' => $callers[1]['function'],
                'description' => $message
            )
        );
    }

    /**
     * Fetch entity id for a entity type.
     *
     * @param string $entityType
     * @return null|string
     */
    public function getEntityIdFor($entityType)
    {
        if ($this->_entityIds == null) {
            $db = $this->_getReadAdapter();
            $select = $db->select()
                ->from(
                    'eav_entity_type',
                    array('entity_type_id', 'entity_type_code')
                );
            $this->_entityIds = array();
            foreach ($db->fetchAll($select) as $row) {
                $this->_entityIds[$row['entity_type_code']] = $row['entity_type_id'];
            }
        }
        return array_key_exists($entityType, $this->_entityIds) ? $this->_entityIds[$entityType] : null;
    }

}