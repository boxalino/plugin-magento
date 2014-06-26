<?php

class Boxalino_Export_Model_Product_Url extends Mage_Catalog_Model_Product_Url {
	public function getUrl(Mage_Catalog_Model_Product $product, $params = array()) {
		if (!isset($params['_query'])) {
			$params['_query'] = array();
		}
		return parent::getUrl($product, $params);
	}
}
