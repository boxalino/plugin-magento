<?php

class Boxalino_CemExport_Model_Mysql4_Product extends Mage_Catalog_Model_Resource_Eav_Mysql4_Product {
	public function __construct() {
		parent::__construct();

        $this->setType('catalog_product');
    }
}
