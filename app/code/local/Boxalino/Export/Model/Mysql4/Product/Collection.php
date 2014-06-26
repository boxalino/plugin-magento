<?php

class Boxalino_Export_Model_Mysql4_Product_Collection extends Mage_Catalog_Model_Resource_Eav_Mysql4_Product_Collection {
	private $_size = 0;
	private $_itemsOrdering = array();


	protected function _construct() {
		parent::_construct();

		if ($this->isEnabledFlat()) {
			$this->_init('boxalinocem/product', 'catalog/product_flat');
		} else {
			$this->_init('boxalinocem/product');
		}
    }


	public function getSize() {
		return $this->_size;
	}

	public function setSize($size) {
		$this->_size = $size;
		return $this;
	}


	public function getItemsOrdering() {
		return $this->_itemsOrdering;
	}

	public function setItemsOrdering($ordering) {
		$this->_itemsOrdering = $ordering;
		return $this;
	}


    protected function _fetchAll($select) {
    	if (is_object($select)) {
			$select->reset(Zend_Db_Select::ORDER);
			$select->reset(Zend_Db_Select::LIMIT_OFFSET);
			$select->reset(Zend_Db_Select::LIMIT_COUNT);
		}
    	return parent::_fetchAll($select);
    }

	protected function _afterLoad() {
		// order items
		usort($this->_items, array($this, 'sortItems'));

		return parent::_afterLoad();
    }


    private function sortItems($i1, $i2) {
    	$index1 = array_search($i1->getId(), $this->_itemsOrdering);
    	$index2 = array_search($i2->getId(), $this->_itemsOrdering);

    	if ($index1 < $index2) {
    		return -1;
    	} else if ($index1 > $index2) {
    		return 1;
    	}
    	return 0;
    }
}
