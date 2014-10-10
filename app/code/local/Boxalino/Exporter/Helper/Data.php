<?php

class Boxalino_Exporter_Helper_Data extends Mage_Core_Helper_Data
{
    const URL_XML = 'http://di1.bx-cloud.com/frontend/dbmind/en/dbmind/api/data/source/update';
    const URL_XML_DEV = 'http://di1.bx-cloud.com/frontend/dbmind/_/en/dbmind/api/data/source/update';
    const URL_ZIP = "http://di1.bx-cloud.com/frontend/dbmind/en/dbmind/api/data/push";
    const URL_ZIP_DEV = "http://di1.bx-cloud.com/frontend/dbmind/_/en/dbmind/api/data/push";
    /**
     * Array of parent_id for specified products.
     * IMPORTANT: We assume that every simple product has at most one configurable parent.
     *
     * @var null
     */
    /**
     * Array of variants ids for specified products.
     *
     * @var null
     */
    static private $simpleIds = null;
    public $XML_DELIMITER = ',';
    public $XML_ENCLOSURE = '"';
    public $XML_ENCLOSURE_TEXT = "&quot;"; // it's $XML_ENCLOSURE
    public $XML_NEWLINE = '\n';
    public $XML_ESCAPE = '\\\\';
    public $XML_ENCODE = 'UTF-8';
    public $XML_FORMAT = 'CSV';
    protected $_attributesWithIds = array();
    protected $_allTags = array();
    protected $_countries = array();
    private $parentId = null;

    public function defaultAttributes()
    {
        $attributes = array(
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

        return $attributes;
    }

    /**
     * @param $language
     * @return bool
     */
    public function isAvailableLanguages($language)
    {
        $languages = array(
            'en',
            'fr',
            'de',
            'it',
            'es',
            'zh',
            'cz',
            'ru',
        );

        if (array_search($language, $languages) !== false) {
            return true;
        }

        return false;
    }

    /**
     * @return array Return array with attributes which have connect optionId = optionValue
     */
    public function attributesWithIds()
    {
        if (empty($this->_attributesWithIds)) {
            $attributes = Mage::getResourceModel('eav/entity_attribute_collection')->getData();
            foreach ($attributes as $attribute) {
                if ($attribute['frontend_input'] == 'select' || $attribute['frontend_input'] == 'multiselect') {
                    $this->_attributesWithIds[] = $attribute['attribute_code'];
                }
            }
        }
        return $this->_attributesWithIds;
    }

    /**
     * @return array Array of all tags array('tag_id' => 'value');
     */
    public function getAllTags()
    {
        if (empty($this->_allTags)) {
            $tagsModel = Mage::getModel('tag/tag');
            $tags = $tagsModel->getCollection()->addStatusFilter($tagsModel->getApprovedStatus())->getData();
            foreach ($tags as $tag) {
                $this->_allTags[$tag['tag_id']] = $tag['name'];
            }
        }

        return $this->_allTags;
    }

    /**
     * @return string URL to normal data sync
     * @param $dev
     */
    public function getZIPSyncUrl($dev = false)
    {
        if ($dev) {
            return self::URL_ZIP_DEV;
        } else {
            return self::URL_ZIP;
        }
    }

    /**
     * @return string URL to delta sync
     * @param $dev
     */
    public function getXMLSyncUrl($dev = false)
    {
        if ($dev) {
            return self::URL_XML_DEV;
        } else {
            return self::URL_XML;
        }

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
            return $this->parentId;
        }

        // If we have parent id for specified product - return it.
        if (isset($this->parentId[$productId])) {
            return $this->parentId[$productId];
        }

        // No parent - return null.
        return null;
    }

    /**
     * Load connection arrays if necessary.
     */
    private function loadProductLinks()
    {

        // If arrays already set - nothing to do here.
        if (isset($this->parentId) && isset($this->simpleIds)) {
            return;
        }

        // Get database connection
        $connection = Mage::getSingleton('core/resource')->getConnection('core_read');
        $tableName = $connection->getTableName('catalog_product_super_link');
        // Get all data from `catalog_product_super_link`
        $query = 'select * from ' . $tableName;
        $this->_simples = array();
        $this->_configurables = array();

        $simpleIds = array();
        $parentIds = array();
        // Iterate through collection
        foreach ($connection->fetchAll($query) as $row) {
            $productId = $row['product_id'];
            $parentId = $row['parent_id'];

            // Set simpleIds array if not set yet for specified parent.
            if (!isset($simpleIds[$parentId])) {
                $simpleIds[$parentId] = array();
            }
            // Add simple product to collection of parent.
            $simpleIds[$parentId][] = $productId;
            // Add parent to simple product.
            $parentIds[$productId] = $parentId;
        }

        self::$simpleIds = $simpleIds;
        self::$parentId = $parentIds;
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
            return $this->simpleIds;
        }

        // If we have simple ids for specified product - return it.
        if (isset($this->simpleIds[$productId])) {
            return $this->simpleIds[$productId];
        }
        // No simple ids - return null.
        return null;
    }

    public function getError($responseBody)
    {
        $htmlTagsToReplace = array('body', 'p', 'br');
        $startPosition = strpos($responseBody, '<p>');
        $endPosition = strpos($responseBody, '&lt;br&gt;') + 3;
        $error = html_entity_decode(substr($responseBody, $startPosition, $endPosition));
        foreach ($htmlTagsToReplace as $tag) {
            $error = str_replace('<' . $tag . '>', PHP_EOL, $error);
        }
        return $error;
    }

    public function getCountry($countryCode)
    {
        if(!isset($this->_countries[$countryCode])) {
            $this->_countries[$countryCode] = Mage::getModel('directory/country')->loadByCode($countryCode);
        }

        return $this->_countries[$countryCode];
    }

}