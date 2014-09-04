<?php
require_once Mage::getModuleDir('', 'Boxalino_CemSearch') . DS . 'Lib' . DS . 'AbstractThrift.php';

class Boxalino_Manager_Model_Boxalino extends AbstractThrift
{
    protected $_client = null;
    protected $_authentication = null;
    protected $_authenticationCreateTimestamp = null;
    protected $_configProd = null;
    protected $_configDev = null;

    final public function __construct()
    {
        parent::__construct();
        require_once Mage::getModuleDir('', 'Boxalino_Manager') . DS . 'Lib' . DS . 'BoxalinoDataIntelligence.php';
        require_once Mage::getModuleDir('', 'Boxalino_Manager') . DS . 'Lib' . DS . 'Types.php';
        $this->_client = $this->getClient();
        $this->_createToken();
        $this->getConfigurationVersion();
    }

    final protected function getClient($clientId = '')
    {
        try {
            $THttpClient = new \Thrift\Transport\THttpClient('di1.bx-cloud.com', 80, '/frontend/dbmind/_/en/dbmind/thrift', 'http');
            $client = new \com\boxalino\dataintelligence\api\thrift\BoxalinoDataIntelligenceClient(new \Thrift\Protocol\TBinaryProtocol($THttpClient));
            $THttpClient->open();
            return $client;
        } catch (Exception $e) {
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::helper('adminhtml')->getUrl('adminhtml/system_config/edit', array('section' => 'Boxalino_General')));
            Mage::app()->getResponse()->sendResponse();
            exit;
        }
    }

    final private function _createToken()
    {
        $date = new DateTime('now');
        if ($this->_authentication == null || $this->_authenticationCreateTimestamp == null || $this->_authenticationCreateTimestamp + 3000 < $date->getTimestamp()) {
            try {
                $authenticationRequest = new com\boxalino\dataintelligence\api\thrift\AuthenticationRequest(Mage::helper('boxalino_manager')->getAccountCredentials());
                $this->_authentication = $this->_client->GetAuthentication($authenticationRequest);
                $this->_authenticationCreateTimestamp = $date->getTimestamp();
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
                Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::helper('adminhtml')->getUrl('adminhtml/system_config/edit', array('section' => 'Boxalino_General')));
                Mage::app()->getResponse()->sendResponse();
                exit;
            }
        }
    }

    final private function getConfigurationVersion($configVersion = null)
    {
        try {
            $this->_configDev = $this->_client->GetConfigurationVersion($this->_authentication, \com\boxalino\dataintelligence\api\thrift\ConfigurationVersionType::CURRENT_DEVELOPMENT_VERSION);
            $this->_configProd = $this->_client->GetConfigurationVersion($this->_authentication, \com\boxalino\dataintelligence\api\thrift\ConfigurationVersionType::CURRENT_PRODUCTION_VERSION);
        } catch (Exception $e) {
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
        }

    }
}