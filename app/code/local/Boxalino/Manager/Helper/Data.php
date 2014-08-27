<?php

class Boxalino_Manager_Helper_Data extends Mage_Admin_Helper_Data
{
    public $generalConfig = null;

    public function getGeneralConfig()
    {
        if ($this->generalConfig == null) {
            $this->generalConfig = Mage::getStoreConfig('Boxalino_General/general');
        }

        return $this->generalConfig;
    }
}