<?php

class Boxalino_Manager_Adminhtml_PasswordController extends Mage_Adminhtml_Controller_Action
{
    public function indexAction()
    {
        $this->loadLayout();

        $this->_addContent($this->getLayout()->createBlock('boxalino_manager/adminhtml_password_index_form'));

        $this->renderLayout();
    }

    public function saveAction()
    {
        if ($data = $this->getRequest()->getPost()) {
            if ($data['newPassword'] != 'null' && ($data['newPassword'] == $data['confirmNewPassword']) && $data['oldPassword'] != $data['newPassword']) {
                $model = Mage::getModel('boxalino_manager/boxalino_password');
                $model->updatePassword($data['oldPassword'], $data['newPassword']);
            } else {
                Mage::getSingleton('adminhtml/session')->addError('Your old password is incorrect, your new password are not matching or your old password is not different than old.');
            }
        }
        $this->_redirect('*/*/index');
    }
}