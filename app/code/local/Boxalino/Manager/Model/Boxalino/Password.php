<?php

class Boxalino_Manager_Model_Boxalino_Password extends Boxalino_Manager_Model_Boxalino
{
    public function updatePassword($oldPassword, $newPassword)
    {
        $credentials = Mage::helper('boxalino_manager')->getAccountCredentials();
        if ($credentials['password'] == $oldPassword) {
            $this->_client->UpdatePassword($this->_authentication, $newPassword);
            Mage::getSingleton('adminhtml/session')->addSuccess('Password changed');
        } else {
            Mage::getSingleton('adminhtml/session')->addError('Your old password is incorrect');
        }
    }
}