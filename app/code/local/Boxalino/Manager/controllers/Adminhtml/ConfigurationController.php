<?php

class Boxalino_Manager_Adminhtml_ConfigurationController extends Mage_Adminhtml_Controller_Action
{
    public function indexAction()
    {
        $this->loadLayout();
        $this->_addContent($this->getLayout()->createBlock('boxalino_manager/adminhtml_configuration_index_differences'));
        $this->_addContent($this->getLayout()->createBlock('boxalino_manager/adminhtml_configuration_index_actions'));
        $this->renderLayout();
    }

    public function showAction()
    {
        if ($data = $this->getRequest()->getPost()) {
            try {
                $configDiff = Mage::getSingleton('boxalino_manager/boxalino_configuration')->GetConfigurationDifferences($data['sourceVersion'], $data['destinationVersion']);
                $this->loadLayout()->_addContent(
                    $this->getLayout()
                        ->createBlock('boxalino_manager/adminhtml_configuration_show_show')
                        ->setData(array('differences' => $configDiff, 'versions' => array($data['sourceVersion'], $data['destinationVersion'])))
                        ->setTemplate('boxalinomanager/configuration.phtml')
                );
                $this->renderLayout();
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
                $this->_redirect('*/*/index');
            }
        }
    }

    public function actionAction()
    {
        if ($data = $this->getRequest()->getPost()) {
            try {
                switch ($data['action']) {
                    case 'Publish':
                        Mage::getSingleton('boxalino_manager/boxalino_configuration')->publishConfiguration($data['configVersion']);
                        Mage::getSingleton('adminhtml/session')->addSuccess('Configuration was published successfully');
                        break;
                    case 'Clone':
                        Mage::getSingleton('boxalino_manager/boxalino_configuration')->cloneConfiguration($data['configVersion']);
                        Mage::getSingleton('adminhtml/session')->addSuccess('Configuration was cloned successfully');
                        break;
                }
                $this->_redirect('*/*/index');
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
                $this->_redirect('*/*/index');
            }
        }
    }
}