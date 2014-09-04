<?php

class Boxalino_Manager_Model_Boxalino_Configuration extends Boxalino_Manager_Model_Boxalino
{
    public function GetConfigurationDifferences($sourceVersion = null, $destinationVersion = null) {
        $srcVersionObj = new \com\boxalino\dataintelligence\api\thrift\ConfigurationVersion();
        $srcVersionObj->configurationVersionNumber = $sourceVersion;
        $dstVersionObj = new \com\boxalino\dataintelligence\api\thrift\ConfigurationVersion();
        $dstVersionObj->configurationVersionNumber = $destinationVersion;
        return $this->_client->GetConfigurationDifferences($this->_authentication, $srcVersionObj, $dstVersionObj);
    }

    public function publishConfiguration($configVersion)
    {
        if (!empty($configVersion)) {
            $this->_client->PublishConfiguration($this->_authentication, $this->createConfigVersion($configVersion));
        } else {
            Mage::getSingleton('adminhtml/session')->addError('Configuration version cannot be null!');
            Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::helper('adminhtml')->getUrl('boxalino_manager/configuration'));
            Mage::app()->getResponse()->sendResponse();
            exit;
        }
    }

    public function cloneConfiguration($configVersion)
    {

        if (!empty($configVersion)) {
            $this->_client->CloneConfiguration($this->_authentication, $this->createConfigVersion($configVersion));
        } else {
            Mage::getSingleton('adminhtml/session')->addError('Configuration version cannot be null!');
            Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::helper('adminhtml')->getUrl('boxalino_manager/configuration'));
            Mage::app()->getResponse()->sendResponse();
            exit;
        }
    }

    public function getConfigVersionNumber()
    {
        $versionDev = $this->_configDev->configurationVersionNumber;
        $versionProd = $this->_configProd->configurationVersionNumber;
        return array($versionProd, $versionDev);
    }

    private function createConfigVersion($configVersion)
    {
        if((int) $configVersion > 0) {
            $configObj = new \com\boxalino\dataintelligence\api\thrift\ConfigurationVersion();
            $configObj->configurationVersionNumber = (int)$configVersion;
            return $configObj;
        } else {
            Mage::getSingleton('adminhtml/session')->addError('Configuration version must be integer!');
            Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::helper('adminhtml')->getUrl('boxalino_manager/configuration'));
            Mage::app()->getResponse()->sendResponse();
            exit;
        }
    }
}