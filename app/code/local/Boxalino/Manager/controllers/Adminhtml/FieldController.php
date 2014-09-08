<?php

class Boxalino_Manager_Adminhtml_FieldController extends Mage_Adminhtml_Controller_Action
{
    public function indexAction()
    {
        $this->loadLayout();
        $this->_addContent($this->getLayout()->createBlock('boxalino_manager/adminhtml_field_index_fields'));
        $this->renderLayout();
    }

    public function editAction()
    {
        $this->loadLayout();
        var_dump(Mage::getSingleton('boxalino_manager/boxalino_field')->updateField());
        var_dump($this->getRequest()->get('fieldsId'));
        $this->renderLayout();
    }

    public function saveAction()
    {
        if($data = $this->getRequest()->getPost()) {
            try {
                Mage::getSingleton('boxalino_manager/boxalino_field')->createField($data['fieldId']);
                $this->_redirect('*/*/edit', array('fieldId' => $data['fieldId']));
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
                $this->_redirect('*/*/index');
            }
        } else {
            $this->_redirect('*/*/index');
        }
    }

    public function updateAction()
    {

    }

    public function deleteAction()
    {
        if($data = $this->getRequest()->getParams()) {
            try {
                Mage::getSingleton('boxalino_manager/boxalino_field')->deleteField($data['fieldId']);
                Mage::getSingleton('adminhtml/session')->addSuccess('Field was removed successfully!');
                $this->_redirect('*/*/index');
        } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
                $this->_redirect('*/*/index');
            }
        } else {
            $this->_redirect('*/*/index');
        }
    }

    public function massdeleteAction()
    {
        try {
            if ($data = $this->getRequest()->getPost()) {
                if ($data['massaction_prepare_key'] == 'fieldsToDelete' && isset($data['fieldsToDelete'])) {
                    foreach($data['fieldsToDelete'] as $field) {
                        Mage::getSingleton('boxalino_manager/boxalino_field')->deleteField($field);
                    }
                    Mage::getSingleton('adminhtml/session')->addSuccess('Fields were removed successfully!');
                    $this->_redirect('*/*/index');
                }
            }
        } catch (Exception $e) {
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            $this->_redirect('*/*/index');
        }
    }

}