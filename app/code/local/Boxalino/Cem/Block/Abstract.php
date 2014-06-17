<?php

abstract class Boxalino_Cem_Block_Abstract extends Mage_Core_Block_Template {
	public function isPluginEnabled() {
		return Mage::helper('boxalinocem')->isEnabled();
	}

	public function isPageEnabled($uri) {
		return Mage::helper('boxalinocem')->isPageEnabled($uri);
	}

	public function getSearchUrl() {
		return $this->getUrl('boxalinocem/search');
	}

	public function getLanguage() {
		return Mage::helper('boxalinocem')->getLanguage();
	}

	public function getSuggestUrl() {
		return Mage::helper('boxalinocem')->getSuggestUrl();
	}

	public function getSuggestParameters() {
		return Mage::helper('boxalinocem')->getSuggestParameters();
	}


}
