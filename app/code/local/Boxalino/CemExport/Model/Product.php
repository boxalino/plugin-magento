<?php

class Boxalino_CemExport_Model_Product extends Mage_Catalog_Model_Product {
	public function getUrlModel() {
		if ($this->_urlModel === null) {
			$this->_urlModel = Mage::getSingleton('boxalinocem/product_url');
		}
		return $this->_urlModel;
	}

	public function getProductUrlWithParams($params = array()) {
		return $this->getUrlModel()->getUrl($this, array('_query' => $params));
	}


	public function getAddToWishlistUrl() {
		return $this->getUrlModel()
			->getUrlInstance()
			->setStore($this->getStoreId())
			->getUrl(
				'wishlist/index/add',
				array('product' => $this->getId())
			);
	}

	public function getAddToCompareUrl() {
		return $this->getUrlModel()
			->getUrlInstance()
			->setStore($this->getStoreId())
			->getUrl(
				'catalog/product_compare/add',
				array(
					'product' => $this->getId(),
					Mage_Core_Controller_Front_Action::PARAM_NAME_URL_ENCODED => 'UENC_URI'
				)
			);
	}

	public function getAddToCartUrl() {
		if ($this->getTypeInstance(true)->hasRequiredOptions($this)) {
            return $this->getProductUrlWithParams(array('option' => 'cart'));
		}
		return $this->getUrlModel()
			->getUrlInstance()
			->setStore($this->getStoreId())
			->getUrl(
				'checkout/cart/add',
				array(
					'product' => $this->getId(),
					Mage_Core_Controller_Front_Action::PARAM_NAME_URL_ENCODED => 'UENC_URI'
				)
			);
	}
}
