<?php

class Boxalino_Manager_Helper_Data extends Mage_Admin_Helper_Data
{
    public function getConfig()
    {
        return Mage::getStoreConfig('Boxalino_General/general');
    }
}