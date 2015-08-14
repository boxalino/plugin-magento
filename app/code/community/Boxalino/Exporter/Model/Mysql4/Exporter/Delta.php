<?php

class Boxalino_Exporter_Model_Mysql4_Exporter_Delta extends Boxalino_Exporter_Model_Mysql4_Indexer
{
    const INDEX_TYPE = 'delta';

    /** @var date Date of last data sync */
    protected $_lastIndex = 0;

    /**
     * @description Declare where Indexer should start
     * @return void
     */
    protected function _construct()
    {
        $this->_init('boxalinoexporter/delta', '');
    }

    /**
     * @description Get date of last data sync
     */
    protected function _getLastIndex()
    {
        if ($this->_lastIndex == 0) {
            $this->_setLastIndex();
        }
        return $this->_lastIndex;
    }

    /**
     * @description set when was last data sync
     */
    protected function _setLastIndex()
    {
        $dates = array();
        $indexes = Mage::getModel('index/indexer')->getProcessesCollection()->getData();
        foreach ($indexes as $index) {
            if ($index['indexer_code'] == 'boxalinoexporter_indexer' && !empty($index['started_at'])) {
                $dates[] = DateTime::createFromFormat('Y-m-d H:i:s', $index['started_at']);
            } elseif ($index['indexer_code'] == 'boxalinoexporter_delta' && !empty($index['ended_at'])) {
                $dates[] = DateTime::createFromFormat('Y-m-d H:i:s', $index['ended_at']);
            }
        }
        if (count($dates) == 2) {
            if ($dates[0] > $dates[1]) {
                $date = $dates[0]->format('Y-m-d H:i:s');
            } else {
                $date = $dates[1]->format('Y-m-d H:i:s');
            }
        } else {
            $date = $dates[0]->format('Y-m-d H:i:s');
        }

        $this->_lastIndex = $date;
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