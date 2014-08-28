<?php

class Boxalino_Manager_Model_Boxalino_Password extends Boxalino_Manager_Model_Boxalino
{
    public function updatePassword($oldPassword, $newPassword)
    {
        $credentials = Mage::helper('boxalino_manager')->getAccountCredentials();
        if ($credentials['password'] == $oldPassword) {
            try {
                $this->_client->UpdatePassword($this->_authentication, $newPassword);
                Mage::getSingleton('adminhtml/session')->addSuccess('Password changed');
            } catch (\com\boxalino\dataintelligence\api\thrift\DataIntelligenceServiceException $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            }
        } else {
            Mage::getSingleton('adminhtml/session')->addError('Your old password is incorrect');
        }
    }
}