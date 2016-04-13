<?php

class Boxalino_Exporter_Model_Mysql4_Exporter_Delta extends Boxalino_Exporter_Model_Mysql4_Indexer
{
    const INDEX_TYPE = 'delta';

    /**
     * @description Declare where Indexer should start
     * @return void
     */
    protected function _construct()
    {
        $this->_init('boxalinoexporter/delta', '');
    }

    /**
     * @description Get list of products with their tags
     * @return object List of products with their tags
     */
    protected function _getProductTags()
    {
        if (empty($this->_allProductTags)) {
            $tags = Mage::getResourceModel('tag/product_collection')->addAttributeToFilter('updated_at', array('from' => $this->_getLastIndex(), 'date' => true))->getData();
            foreach ($tags as $tag) {
                $this->_allProductTags[$tag['entity_id']] = $tag['tag_id'];
            }
        }
        return $this->_allProductTags;
    }
}
