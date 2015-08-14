<?php

class Boxalino_Exporter_Model_Indexer extends Mage_Index_Model_Indexer_Abstract
{
    public function getName()
    {
        return Mage::helper('boxalinoexporter')->__('Boxalino Full Data Export');
    }

    public function getDescription()
    {
        return Mage::helper('boxalinoexporter')->__('Send all data to Boxalino');
    }

    protected function _construct()
    {
        $this->_init('boxalinoexporter/exporter_indexer');
    }

    protected function _registerEvent(Mage_Index_Model_Event $event)
    {
    }

    protected function _processEvent(Mage_Index_Model_Event $event)
    {
    }


}