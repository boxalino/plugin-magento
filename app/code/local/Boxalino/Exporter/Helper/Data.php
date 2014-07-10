<?php
class Boxalino_Exporter_Helper_Data extends Mage_Core_Helper_Data
{
    protected $_attributesWithIds = array();
    protected $_allTags = array();

    const DELTA_URL = '';

    const FULL_URL = '';

    /**
     * Array of parent_id for specified products.
     * IMPORTANT: We assume that every simple product has at most one configurable parent.
     *
     * @var null
     */
    static private $parentId = null;

    /**
     * Array of variants ids for specified products.
     *
     * @var null
     */
    static private $simpleIds = null;

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

        if(array_search($language, $languages) !== false) {
            return true;
        }

        return false;
    }

    /**
     * @return array Return array with attributes which have connect optionId = optionValue
     */
    public function attributesWithIds()
    {
        if(empty($this->_attributesWithIds)) {
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
     * @TODO: Add url to data sync
     */
    public function getDataSyncUrl()
    {
        return self::FULL_URL;
    }

    /**
     * @return string URL to delta sync
     * @TODO: Add url to delta sync
     */
    public function getDeltaSyncUrl()
    {
        return self::DELTA_URL;
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

        // Iterate through collection
        foreach ($connection->fetchAll($query) as $row) {
            $productId = $row['product_id'];
            $parentId = $row['parent_id'];

            // Set simpleIds array if not set yet for specified parent.
            if (!isset($this->simpleIds[$parentId])) {
                $this->simpleIds[$parentId] = array();
            }
            // Add simple product to collection of parent.
            $this->simpleIds[$parentId][] = $productId;
            // Add parent to simple product.
            $this->parentId[$productId] = $parentId;
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
}