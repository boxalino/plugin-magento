<?php

abstract class Boxalino_Exporter_Model_Mysql4_Indexer extends Mage_Core_Model_Mysql4_Abstract
{
    /** @var array Configuration for each Store View */
    protected $_storeConfig = array();

    /** @var array Values of attributes where array('storeId' => array('attrName' => array('id' => 'value'))) */
    protected $_attributesValues = array();

    /** @var array Customer attributes */
    protected $_customerAttributes = array();

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

    protected $_productsImages = array();
    protected $_productsThumbnails = array();

    protected $_files = array();

    protected $_count = 0;

    protected $_attrProdCount = array();

    /** @var int Actually used storeId */
    protected $_storeId = 0;

    protected $_mainDir = '/tmp/boxalino';
    protected $_dir = '';

    protected $group = null;

    protected $_helperExporter = null;
    protected $_helperSearch = null;
    protected $_helperImage = null;

    protected $_entityIds = null;
    protected $_prefix = '';

    /**
     * @description Start of reindex
     */
    public function reindexAll()
    {

        $prefix = Mage::getConfig()->getTablePrefix();
        $this->_prefix = $prefix;

        self::logMem('Indexer init');
        if (!file_exists($this->_mainDir)) {
            mkdir($this->_mainDir);
        }
        $this->_websiteExport();
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
        $this->_helperImage = Mage::helper('catalog/image');

        self::logMem('Helpers init');
        $indexStructure = $this->_getIndexStructure();
        foreach ($indexStructure as $index => $languages) {
            $this->_dir = $this->_mainDir . '/' . $index;
            if (file_exists($this->_dir)) {
                $this->_helperExporter->delTree($this->_dir);
            }
            self::logMem('After delTree');

            $data = $this->_storeExport($languages);

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
            $file = $this->prepareFiles($data['categories'], $data['tags']);
            self::logMem('Push files');

            $this->pushXML($file);
            $this->pushZip($file);

            self::logMem('Files pushed');

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
     * @description generate the data for the language scope
     * @param array $languages Array of languages to generate for this index
     * @return array Data prepared for save to file
     */
    protected function _storeExport($languages)
    {
        $categories = array();
        $tags = array();
        self::logMem('Preparing data for website start');
        foreach ($languages as $language => $info) {
            $storeId = $info['store']->getId();

            self::logMem('Start store:' . $storeId);
            $this->_prepareStoreConfig($storeId, $info['config']);
            self::logMem('Configuration for store loaded');
            $categories = $this->_exportCategories();
            $tags = $this->_exportTags();
            $this->_availableLanguages[] = $language;

        }
        $this->_exportCustomers($info['website']);
        $this->_exportTransactions();
        $this->_exportProducts($languages);

        return array(
            'categories' => $categories,
            'tags' => $tags,
        );
    }

    /**
     * @description generate the index structure to iterate on
     * @return array Index structure
     */
    protected function _getIndexStructure()
    {
        $indexStructure = array();
        foreach (Mage::app()->getWebsites() as $website) {
            foreach ($website->getGroups() as $group) {
                foreach ($group->getStores() as $store) {
                    $config = array_merge(
                        $store->getConfig('boxalinoexporter/export_data'),
                        $store->getConfig('Boxalino_General/general')
                    );
                    if ($config['enabled'] == '1') {
                        $index = $config['di_account'];
                        $lang = $config['language'];
                        $config['groupId'] = $store->getGroupId();

                        if (!array_key_exists($index, $indexStructure)) {
                            $indexStructure[$index] = array();
                        }
                        if (!array_key_exists($lang, $indexStructure[$index])) {
                            $indexStructure[$index][$lang] = array();
                        }
                        $indexStructure[$index][$lang] = array(
                            'config'  => $config,
                            'website' => $website,
                            'store'   => $store,
                        );
                    }
                }
            }
        }
        return $indexStructure;
    }

    /**
     * @description Get configs for store by storeId
     * @param int $storeId
     * @return void
     */
    protected function _prepareStoreConfig($storeId, $config)
    {
        $this->_storeId = $storeId;
        $this->_storeConfig = $config;

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
            'special_from_date',
            'special_to_date',
            'visibility',
            'category_ids',
            'status'
        );

        $attributes = array();

        foreach (Mage::getResourceModel('catalog/product_attribute_collection')->getItems() as $at) {
            $attributes[] = $at->getAttributeCode();
        }

        if (isset($this->_storeConfig['additional_attributes']) && $this->_storeConfig['additional_attributes'] != '') {
            $fields = explode(',', $this->_storeConfig['additional_attributes']);
            foreach ($fields as $field) {

                if (!in_array($field, $attributes)) {
                    Mage::throwException("Attribute \"$field\" doesn't exist, please update your additional_attributes setting in the Boxalino Exporter settings!");
                }

                if ($field != null && strlen($field) > 0) {
                    $this->_listOfAttributes[] = $field;
                }

            }
            unset($fields);
        }

    }

    /**
     * @description Merge default customer attributes with customer attributes added by user
     * @param array $attributes optional, array to merge the user defined attributes into
     * @return array
     */
    protected function _mergeCustomerAttributes($attributes = array())
    {
        if (isset($this->_storeConfig['additional_customer_attributes']) && $this->_storeConfig['additional_customer_attributes'] != '') {
            if(count($this->_customerAttributes) == 0) {
                foreach (Mage::getModel('customer/customer')->getAttributes() as $at) {
                    $this->_customerAttributes[] = $at->getAttributeCode();
                }
            }

            foreach (explode(',', $this->_storeConfig['additional_customer_attributes']) as $field) {
                if (!in_array($field, $this->_customerAttributes)) {
                    Mage::throwException("Customer attribute \"$field\" doesn't exist, please update your additional_customer_attributes setting in the Boxalino Exporter settings!");
                }
                if ($field != null && strlen($field) > 0 && !in_array($field, $customer_attributes)) {
                    $attributes[] = $field;
                }
            }
        }
        return $attributes;
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
     * @param array $languages language structure
     * @return void
     */
    protected function _exportProducts($languages)
    {
        self::logMem('Products - start of export');
        $attrs = $this->_listOfAttributes;
        self::logMem('Products - get info about attributes - before');

        $db = $this->_getReadAdapter();
        $select = $db->select()
            ->from(
                array('main_table' => $this->_prefix . 'eav_attribute'),
                array(
                    'attribute_id',
                    'attribute_code',
                    'backend_type',
                )
            )
            ->joinInner(
                array('additional_table' => $this->_prefix . 'catalog_eav_attribute'),
                'additional_table.attribute_id = main_table.attribute_id'
            )
            ->where('main_table.entity_type_id = ?', $this->getEntityIdFor('catalog_product'))
            ->where('main_table.attribute_code IN(?)', $attrs);

        self::logMem('Products - connected to DB, built attribute info query');

        $attrsFromDb = array(
            'int' => array(),
            'varchar' => array(),
            'text' => array(),
            'decimal' => array(),
            'datetime' => array(),
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

        //prepare files
        $filesMtM = array();
        $tmp = array_keys($this->_attributesValuesByName);
        $tmp[] = 'categories';
        foreach ($tmp as $attr) {

            $key = $attr;

            if ($attr == 'categories') {
                $key = 'category';
            }

            if (!file_exists($this->_dir)) {
                mkdir($this->_dir);
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

        if ($this->_storeConfig['export_product_images']) {
            $file = 'product_cache_image_url.csv';
            if (!in_array($file, $this->_files)) {
                $this->_files[] = $file;
            }
            $fh = fopen($this->_dir . '/' . $file, 'a');
            $h = array('entity_id', 'cache_image_url');
            fputcsv($fh, $h, $this->_helperExporter->XML_DELIMITER, $this->_helperExporter->XML_ENCLOSURE);
        }

        if ($this->_storeConfig['export_product_images_thumbnail']) {
            $file = 'product_cache_image_thumbnail_url.csv';
            if (!in_array($file, $this->_files)) {
                $this->_files[] = $file;
            }
            $fh = fopen($this->_dir . '/' . $file, 'a');
            $h = array('entity_id', 'cache_image_thumbnail_url');
            fputcsv($fh, $h, $this->_helperExporter->XML_DELIMITER, $this->_helperExporter->XML_ENCLOSURE);
        }

        while ($count >= $limit) {
            if ($countMax > 0 && $this->_count >= $countMax) {
                break;
            }

            foreach ($languages as $lang => $info) {
                $storeObject = $info['store'];
                $storeId = $storeObject->getId();
                $storeBaseUrl = $storeObject->getBaseUrl();
                $storeCode = $storeObject->getCode();

                self::logMem('Products - fetch products - before');
                $select = $db->select()
                    ->from(
                        array('e' => $this->_prefix . 'catalog_product_entity')
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
                    ->joinLeft(array('ea' => $this->_prefix . 'eav_attribute'), 't_d.attribute_id = ea.attribute_id', 'ea.attribute_code')
                    ->where('t_d.store_id = ?', 0)
                    ->where('t_d.entity_type_id = ?', $this->getEntityIdFor('catalog_product'))
                    ->where('t_d.entity_id IN(?)', $ids);
                $select2 = clone $select1;
                $select3 = clone $select1;
                $select4 = clone $select1;
                $select5 = clone $select1;

                $select1->from(
                        array('t_d' => $this->_prefix . 'catalog_product_entity_varchar'),
                        $columns
                    )
                    ->joinLeft(
                        array('t_s' => $this->_prefix . 'catalog_product_entity_varchar'),
                        $joinCondition,
                        $joinColumns
                    )
                    ->where('t_d.attribute_id IN(?)', $attrsFromDb['varchar']);
                $select2->from(
                        array('t_d' => $this->_prefix . 'catalog_product_entity_text'),
                        $columns
                    )
                    ->joinLeft(
                        array('t_s' => $this->_prefix . 'catalog_product_entity_text'),
                        $joinCondition,
                        $joinColumns
                    )
                    ->where('t_d.attribute_id IN(?)', $attrsFromDb['text']);
                $select3->from(
                        array('t_d' => $this->_prefix . 'catalog_product_entity_decimal'),
                        $columns
                    )
                    ->joinLeft(
                        array('t_s' => $this->_prefix . 'catalog_product_entity_decimal'),
                        $joinCondition,
                        $joinColumns
                    )
                    ->where('t_d.attribute_id IN(?)', $attrsFromDb['decimal']);
                $select4->from(
                        array('t_d' => $this->_prefix . 'catalog_product_entity_int'),
                        $columns
                    )
                    ->joinLeft(
                        array('t_s' => $this->_prefix . 'catalog_product_entity_int'),
                        $joinCondition,
                        $joinColumns
                    )
                    ->where('t_d.attribute_id IN(?)', $attrsFromDb['int']);
                $select5->from(
                        array('t_d' => $this->_prefix . 'catalog_product_entity_datetime'),
                        $columns
                    )
                    ->joinLeft(
                        array('t_s' => $this->_prefix . 'catalog_product_entity_datetime'),
                        $joinCondition,
                        $joinColumns
                    )
                    ->where('t_d.attribute_id IN(?)', $attrsFromDb['datetime']);

                $select = $db->select()
                    ->union(
                        array($select1, $select2, $select3, $select4, $select5),
                        Zend_Db_Select::SQL_UNION_ALL
                    );

                $select1 = null;
                $select2 = null;
                $select3 = null;
                $select4 = null;
                $select5 = null;
                foreach ($db->fetchAll($select) as $r) {
                    $products[$r['entity_id']][$r['attribute_code']] = $r['value'];
                }
                self::logMem('Products - get attributes - after');

                self::logMem('Products - get stock  - before');
                $select = $db->select()
                    ->from(
                        $this->_prefix . 'cataloginventory_stock_status',
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
                        $this->_prefix . 'catalog_product_website',
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
                        $this->_prefix . 'catalog_product_super_link',
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
                        $this->_prefix . 'catalog_category_product',
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

                    if (count($product['website']) == 0 || !in_array($this->_storeConfig['groupId'], $product['website'])) {
                        $product = null;
                        continue;
                    }

                    $id = $product['entity_id'];

                    $productParam = array();
                    $haveParent = false;

                    if (array_key_exists('parent_id', $product)) {
                        $id = $product['parent_id'];
                        $haveParent = true;
                    }

                    // apply special price time range
                    if (
                        !empty($product['special_price']) &&
                        $product['price'] > $product['special_price'] && (
                            !empty($product['special_from_date']) ||
                            !empty($product['special_to_date'])
                        )
                    ) {
                        $product['special_price'] = Mage_Catalog_Model_Product_Type_Price::calculateSpecialPrice(
                            $product['price'],
                            $product['special_price'],
                            $product['special_from_date'],
                            $product['special_to_date'],
                            $storeObject
                        );
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

                            // visibility as defined in Mage_Catalog_Model_Product_Visibility:
                            // 4 - VISIBILITY_BOTH
                            // 3 - VISIBILITY_IN_SEARCH
                            // 2 - VISIBILITY_IN_CATALOG
                            // 1 - VISIBILITY_NOT_VISIBLE
                            // status as defined in Mage_Catalog_Model_Product_Status:
                            // 2 - STATUS_DISABLED
                            // 1 - STATUS_ENABLED
                            if ($attr == 'visibility' || $attr == 'status') {
                                $productParam[$attr . '_' . $lang] = $val;
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
                            case 'status':
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

                        // Add categories
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

                        /**
                         * Add special fields
                         */
                        //Add url to image cache
                        if ($this->_storeConfig['export_product_images']) {
                            $_product = Mage::getModel('catalog/product')->load($id);
                            $media_gallery = $_product->getMediaGallery();
                            foreach ($media_gallery['images'] as $_image) {
                                $url = $this->_helperImage->init($_product, 'image', $_image['file'])->__toString();
                                $url_tbm = $this->_helperImage->init($_product, 'thumbnail', $_image['file'])->resize(100)->__toString();

                                $this->_productsImages[] = array($id, $url);
                                $this->_productsThumbnails[] = array($id, $url_tbm);
                            }
                        }

                    } elseif (isset($this->_transformedProducts['products'][$id])) {
                        $this->_transformedProducts['products'][$id] = array_merge($this->_transformedProducts['products'][$id], $productParam);
                    }

                    /**
                     * Add url to product for each languages
                     */
                    if ($this->_storeConfig['export_product_url']) {
                        $this->_transformedProducts['products'][$id] =
                            array_merge(
                                $this->_transformedProducts['products'][$id],
                                array(
                                    'default_url_' . $lang => $storeBaseUrl . $this->_helperExporter->rewrittenProductUrl($id, null, $storeId) . '?___store=' . $storeCode
                                )
                            );
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

                if ($this->_storeConfig['export_product_images']) {
                    self::logMem('Products - save images');

                    $d = $this->_productsImages;
                    $this->savePartToCsv('product_cache_image_url.csv', $d);
                    $d = null;

                    $d = $this->_productsThumbnails;
                    $this->savePartToCsv('product_cache_image_thumbnail_url.csv', $d);
                    $d = null;
                    $this->_productsImages = array();
                    $this->_productsThumbnails = array();
                }

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
        foreach ($filesMtM as $f) {
            fclose($f);
        }


    }

    /**
     * @description Preparing customers to export
     * @param Mage_Core_Model_Website $website
     * @return void
     *
     */
    protected function _exportCustomers(Mage_Core_Model_Website $website)
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
            'static' => array(), // only supports email
            'varchar' => array(),
            'datetime' => array(),
        );
        $customer_attributes = $this->_mergeCustomerAttributes(array('dob', 'gender'));

        $db = $this->_getReadAdapter();
        $select = $db->select()
            ->from(
                array('main_table' => $this->_prefix . 'eav_attribute'),
                array(
                    'aid' => 'attribute_id',
                    'backend_type',
                )
            )
            ->joinInner(
                array('additional_table' => $this->_prefix . 'customer_eav_attribute'),
                'additional_table.attribute_id = main_table.attribute_id',
                array()
            )
            ->where('main_table.entity_type_id = ?', $this->getEntityIdFor('customer'))
            ->where('main_table.attribute_code IN (?)', $customer_attributes);

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
                    $this->_prefix . 'customer_entity',
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
                ->where('ce.entity_type_id = ?', 1)
                ->where('ce.entity_id IN (?)', $ids);

            $select1 = null;
            $select2 = null;
            $select3 = null;
            $select4 = null;

            $selects = array();

            if (count($attrsFromDb['varchar']) > 0) {
                $select1 = clone $select;
                $select1->from(array('ce' => $this->_prefix . 'customer_entity_varchar'), $columns)
                    ->joinLeft(array('ea' => $this->_prefix . 'eav_attribute'), 'ce.attribute_id = ea.attribute_id', 'ea.attribute_code')
                    ->where('ce.attribute_id IN(?)', $attrsFromDb['varchar']);
                $selects[] = $select1;
            }

            if (count($attrsFromDb['int']) > 0) {
                $select2 = clone $select;
                $select2->from(array('ce' => $this->_prefix . 'customer_entity_int'), $columns)
                    ->joinLeft(array('ea' => $this->_prefix . 'eav_attribute'), 'ce.attribute_id = ea.attribute_id', 'ea.attribute_code')
                    ->where('ce.attribute_id IN(?)', $attrsFromDb['int']);
                $selects[] = $select2;
            }

            if (count($attrsFromDb['datetime']) > 0) {
                $select3 = clone $select;
                $select3->from(array('ce' => $this->_prefix . 'customer_entity_datetime'), $columns)
                    ->joinLeft(array('ea' => $this->_prefix . 'eav_attribute'), 'ce.attribute_id = ea.attribute_id', 'ea.attribute_code')
                    ->where('ce.attribute_id IN(?)', $attrsFromDb['datetime']);
                $selects[] = $select3;
            }

            // only supports email
            if (count($attrsFromDb['static']) > 0) {
                $attributeId = current($attrsFromDb['static']);
                $select4 = clone $select
                    ->from(array('ce' => $this->_prefix . 'customer_entity'), array(
                        'entity_id' => 'entity_id',
                        'attribute_id' =>  new Zend_Db_Expr($attributeId),
                        'value' => 'email',
                    ))
                    ->joinLeft(array('ea' => $this->_prefix . 'eav_attribute'), 'ea.attribute_id = ' . $attributeId, 'ea.attribute_code');
                $selects[] = $select4;
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
            $select4 = null;
            $selects = null;

            $select = $db->select()
                ->from(
                    $this->_prefix . 'eav_attribute',
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
                        $this->_prefix . 'customer_address_entity',
                        array('entity_id')
                    )
                    ->where('entity_type_id = ?', $this->getEntityIdFor('customer_address'))
                    ->where('parent_id = ?', $id)
                    ->order('entity_id DESC')
                    ->limit(1);

                $select = $db->select()
                    ->from(
                        $this->_prefix . 'customer_address_entity_varchar',
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
                        $customer['gender'] = 'female';
                    } else {
                        $customer['gender'] = 'male';
                    }
                }

                $customer_to_save = array(
                    'customer_id' => $id,
                    'country' => !empty($countryCode) ? $this->_helperExporter->getCountry($countryCode)->getName() : '',
                    'zip' => array_key_exists('postcode', $billingResult) ? $billingResult['postcode'] : '',
                );
                foreach($customer_attributes as $attr) {
                    $customer_to_save[$attr] = array_key_exists($attr, $customer) ? $customer[$attr] : '';
                }
                $customers_to_save[] = $customer_to_save;
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
                    array('order' => $this->_prefix . 'sales_flat_order'),
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
                    array('item' => $this->_prefix . 'sales_flat_order_item'),
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
                    'order_id' => $transaction['entity_id'],
                    'entity_id' => $transaction['product_id'],
                    'customer_id' => array_key_exists('customer_id', $transaction) ? $transaction['customer_id'] : '',
                    'price' => $transaction['original_price'],
                    'discounted_price' => $transaction['price'],
                    'quantity' => $transaction['qty_ordered'],
                    'total_order_value' => ($transaction['base_subtotal'] + $transaction['shipping_amount']),
                    'shipping_costs' => $transaction['shipping_amount'],
                    'order_date' => $transaction['created_at'],
                    'confirmation_date' => $status == 1 ? $transaction['updated_at'] : null,
                    'shipping_date' => $status == 2 ? $transaction['updated_at'] : null,
                    'status' => $transaction['status'],
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
    protected function prepareFiles(&$categories = null, &$tags = null)
    {

        //Prepare attributes
        $csvFiles = array();
        if (!file_exists($this->_dir)) {
            mkdir($this->_dir);
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
        $exportFile = $this->_dir . '/' . $this->_storeConfig['di_username'];
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
            if ($attr == 'visibility' || $attr == 'status') {
                continue;
            }
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

        //########################################################################
        // IMAGES
        if ($this->_storeConfig['export_product_images']) {

            //categories & products images
            $source = $sources->addChild('source');
            $source->addAttribute('type', 'item_data_file');
            $source->addAttribute('id', 'item_cache_image_url');

            $source->addChild('file')->addAttribute('value', 'product_cache_image_url.csv');
            $source->addChild('itemIdColumn')->addAttribute('value', 'entity_id');

            $this->sxml_append_options($source);
        }
        if ($this->_storeConfig['export_product_images_thumbnail']) {

            //categories & products images
            $source = $sources->addChild('source');
            $source->addAttribute('type', 'item_data_file');
            $source->addAttribute('id', 'item_cache_image_thumbnail_url');

            $source->addChild('file')->addAttribute('value', 'product_cache_image_thumbnail_url.csv');
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
            $customers = $containers->addChild('container');
            $customers->addAttribute('id', 'customers');
            $customers->addAttribute('type', 'customers');

            $sources = $customers->addChild('sources');
            //#########################################################################

            //customer source
            $source = $sources->addChild('source');
            $source->addAttribute('id', 'customer_vals');
            $source->addAttribute('type', 'item_data_file');

            $source->addChild('file')->addAttribute('value', 'customers.csv');
            $source->addChild('itemIdColumn')->addAttribute('value', 'customer_id');

            $this->sxml_append_options($source);
            //#########################################################################

            $properties = $customers->addChild('properties');
            foreach (
                $this->_mergeCustomerAttributes(
                    array('id', 'customer_id', 'country', 'zip', 'dob', 'gender')
                ) as $prop
            ) {
                $type = 'string';
                $column = $prop;
                switch($prop) {
                    case 'id':
                        $type = 'id';
                        $column = 'customer_id';
                        break;
                    case 'dob':
                        $type = 'date';
                        break;
                }

                $property = $properties->addChild('property');
                $property->addAttribute('id', $prop);
                $property->addAttribute('type', $type);

                $transform = $property->addChild('transform');

                $logic = $transform->addChild('logic');
                $logic->addAttribute('source', 'customer_vals');
                $logic->addAttribute('type', 'direct');
                $logic->addChild('field')->addAttribute('column', $column);

                $property->addChild('params');
            }
        }

        if ($this->_storeConfig['export_transactions']) {
            $transactions = $containers->addChild('container');
            $transactions->addAttribute('id', 'transactions');
            $transactions->addAttribute('type', 'transactions');

            $sources = $transactions->addChild('sources');
            //#########################################################################

            //transaction source
            $source = $sources->addChild('source');
            $source->addAttribute('id', 'transactions');
            $source->addAttribute('type', 'transactions');

            $source->addChild('file')->addAttribute('value', 'transactions.csv');
            $source->addChild('orderIdColumn')->addAttribute('value', 'order_id');
            $customerIdColumn = $source->addChild('customerIdColumn');
            $customerIdColumn->addAttribute('value', 'order_id');
            $customerIdColumn->addAttribute('customer_property_id', 'customer_id');
            $productIdColumn = $source->addChild('productIdColumn');
            $productIdColumn->addAttribute('value', 'entity_id');
            $productIdColumn->addAttribute('product_property_id', 'product_entity_id');
            $source->addChild('productListPriceColumn')->addAttribute('value', 'price');
            $source->addChild('productDiscountedPriceColumn')->addAttribute('value', 'discounted_price');
            $source->addChild('totalOrderValueColumn')->addAttribute('value', 'total_order_value');
            $source->addChild('shippingCostsColumn')->addAttribute('value', 'shipping_costs');
            $source->addChild('orderReceptionDateColumn')->addAttribute('value', 'order_date');
            $source->addChild('orderConfirmationDateColumn')->addAttribute('value', 'confirmation_date');
            $source->addChild('orderShippingDateColumn')->addAttribute('value', 'shipping_date');
            $source->addChild('orderStatusColumn')->addAttribute('value', 'status');

            $this->sxml_append_options($source);
            //#########################################################################
        }

        $dom = new DOMDocument('1.0');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xml->asXML());
        $saveXML = $dom->saveXML();
        file_put_contents($name, $saveXML);
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

        if ($this->_storeConfig['export_product_url']) {
            $attrs[] = 'default_url';
        }

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
                case 'status':
                case 'visibility':
                case 'default_url':
                    $ptype = 'text';
                    break;
                case 'weight':
                case 'width':
                case 'height':
                case 'length':
                    $ptype = 'number';
                    break;
            }

            if (isset($this->_attributesValuesByName[$attr]) && $attr != 'visibility' && $attr != 'status') {
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
                    case 'visibility':
                    case 'status':
                    case 'name':
                    case 'default_url':
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
        if ($this->_storeConfig['export_tags'] && $withTag) {
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
                'type' => 'reference', //logic type
                'field' => 'category_id', //field colummn
                'has_lang' => false,
                'reference' => 'categories'
            );
        }

        //images
        if ($this->_storeConfig['export_product_images']) {
            $properties[] = array(
                'id' => 'cache_image_url',
                'name' => 'cache_image_url', //property id
                'ptype' => 'string', //property type
                'type' => 'direct', //logic type
                'field' => 'cache_image_url', //field colummn
                'has_lang' => false,
            );
        }

        //images
        if ($this->_storeConfig['export_product_images_thumbnail']) {
            $properties[] = array(
                'id' => 'cache_image_thumbnail_url',
                'name' => 'cache_image_thumbnail_url', //property id
                'ptype' => 'string', //property type
                'type' => 'direct', //logic type
                'field' => 'cache_image_thumbnail_url', //field colummn
                'has_lang' => false,
            );
        }


        $properties[] = array(
            'id' => 'product_entity_id',
            'name' => null,
            'ptype' => 'string',
            'type' => 'direct',
            'field' => 'entity_id',
            'has_lang' => false,
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
                if (!$zip->addFile($this->_dir . '/' . $f, $f)) {
                    throw new Exception(
                        'Synchronization failure: Failed to add file "' .
                        $this->_dir . '/' . $f . '" to the zip "' .
                        $name . '". Please try again.'
                    );
                }
            }

            if (!$zip->addFile($xml, 'properties.xml')) {
                throw new Exception(
                    'Synchronization failure: Failed to add file "' .
                    $xml . '" to the zip "' .
                    $name . '". Please try again.'
                );
            }

            if (!$zip->close()) {
                throw new Exception(
                    'Synchronization failure: Failed to close the zip "' .
                    $name . '". Please try again.'
                );
            }

        } else {
            throw new Exception(
                'Synchronization failure: Failed to open the zip "' .
                $name . '" for writing. Please check the permissions and try again.'
            );
        }
    }

    protected function pushXML($file)
    {
        $fields = array(
            'username' => $this->_storeConfig['di_username'],
            'password' => $this->_storeConfig['di_password'],
            'account' => $this->_storeConfig['di_account'],
            'template' => 'standard_source',
            'xml' => file_get_contents($file . '.xml')
        );

        $url = $this->_helperExporter->getXMLSyncUrl($this->_storeConfig['account_dev']);
        return $this->pushFile($fields, $url, 'xml');

    }

    protected function pushFile($fields, $url, $type)
    {
        if ($this->_getIndexType() == 'delta' && !in_array('products.csv', $this->_files)) {
            return 'skipped empty product delta sync';
        }

        self::logMem($type . ' push');
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
            self::logMem($type . ' push error: ' . $responseBody);
            Mage::throwException($this->_helperExporter->getError($responseBody));;
        }
        self::logMem($type . ' pushed. Response: ' . $responseBody);
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
            'account' => $this->_storeConfig['di_account'],
            'dev' => $this->_storeConfig['account_dev'] == 0 ? 'false' : 'true',
            'delta' => $this->_getIndexType() == 'delta' ? 'true' : 'false',
            'data' => $this->getCurlFile("@$file.zip", "application/zip"),
        );

        $url = $this->_helperExporter->getZIPSyncUrl($this->_storeConfig['account_dev']);

        return $this->pushFile($fields, $url, 'zip');
    }

    protected function getCurlFile($filename, $type)
    {
        try {
            if (class_exists('CURLFile')) {
                return new CURLFile(substr($filename, 1), $type);
            }
        } catch(Exception $e){
            return $filename . ";type=$type";
        }
        return $filename . ";type=$type";
    }

    protected function savePartToCsv($file, &$data)
    {

        if (!file_exists($this->_dir)) {
            mkdir($this->_dir);
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
                    $this->_prefix . 'eav_entity_type',
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