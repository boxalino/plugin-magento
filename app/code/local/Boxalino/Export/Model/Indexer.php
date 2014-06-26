<?php

/**
 * Boxalino CemExport indexer model
 *
 * @author nitro@boxalino.com
 */
class Boxalino_Export_Model_Indexer extends Mage_Index_Model_Indexer_Abstract {
	protected function _construct() {
		$this->_init('boxalinocem/dbmind_indexer');

/*		$this->_matchedEntities[Mage_Catalog_Model_Product::ENTITY] = array(
			Mage_Index_Model_Event::TYPE_SAVE,
			Mage_Index_Model_Event::TYPE_MASS_ACTION,
			Mage_Index_Model_Event::TYPE_DELETE
		);
		$this->_matchedEntities[Mage_Catalog_Model_Resource_Eav_Attribute::ENTITY] = array(
			Mage_Index_Model_Event::TYPE_SAVE,
			Mage_Index_Model_Event::TYPE_DELETE,
		);
		$this->_matchedEntities[Mage_Core_Model_Store::ENTITY] = array(
			Mage_Index_Model_Event::TYPE_SAVE,
			Mage_Index_Model_Event::TYPE_DELETE
		);
		$this->_matchedEntities[Mage_Core_Model_Store_Group::ENTITY] = array(
			Mage_Index_Model_Event::TYPE_SAVE
		);
		$this->_matchedEntities[Mage_Core_Model_Config_Data::ENTITY] = array(
			Mage_Index_Model_Event::TYPE_SAVE
		);
		$this->_matchedEntities[Mage_Catalog_Model_Convert_Adapter_Product::ENTITY] = array(
			Mage_Index_Model_Event::TYPE_SAVE
		);*/
	}


	public function getName() {
		return Mage::helper('boxalinocem')->__('Boxalino Export Index');
	}

	public function getDescription() {
		return Mage::helper('boxalinocem')->__('Rebuild Boxalino Export search index');
	}

	protected function _registerEvent(Mage_Index_Model_Event $event) {
	}

	protected function _processEvent(Mage_Index_Model_Event $event) {
	}
}
