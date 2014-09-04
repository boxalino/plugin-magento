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

    public function getAccountCredentials()
    {
        $config = $this->getGeneralConfig();
        if ((isset($config['di_account']) && $config['di_account'] != '') && (isset($config['di_username']) && $config['di_username'] != '') && (isset($config['di_password']) && $config['di_password'] != '')) {
            $credentials = array(
                'account' => $config['di_account'],
                'username' => $config['di_username'],
                'password' => $config['di_password'],
            );
            return $credentials;
        } else {
            Mage::getSingleton('adminhtml/session')->addError('You must set our Data Intelligence Credentials in configuration!');
        }
    }

    public function prepareConfigVersions($prodVersion, $devVersion)
    {
        if(is_int($prodVersion) && is_int($devVersion)) {
            $versions = array();
            $max = max($devVersion, $prodVersion);
            for ($i = $max; $i > 0; $i--) {
                if($i == $devVersion) {
                    $versions[$i] = 'Dev: ' . $i;
                } elseif ($i == $prodVersion) {
                    $versions[$i] = 'Prod: ' . $i;
                } else {
                    $versions[$i] = 'Archive: ' . $i;
                }
            }
            return $versions;
        } else {
            Mage::getSingleton('adminhtml/session')->addError('Versions must be integer!');
        }
    }
}