<?php

class Boxalino_Exporter_Model_Mysql4_Exporter_Indexer extends Boxalino_Exporter_Model_Mysql4_Indexer
{
    const INDEX_TYPE = 'full';

    /**
     * @description Declare where Indexer should start
     * @return void
     */
    protected function _construct()
    {
        $this->_init('boxalinoexporter/indexer', '');
    }

}