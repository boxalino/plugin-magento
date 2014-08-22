<?php
class Boxalino_Manager_Model_Boxalino_Password extends Boxalino_Manager_Model_Boxalino {
    protected function updatePassword($oldPassword, $newPassword)
    {
        $credentials = $this->getAccountCredentials();
        if ($credentials['password'] == $oldPassword) {
            try {
                $this->_client->UpdatePassword($this->_authentication, $newPassword);
                return 'Password changed';
            } catch (\com\boxalino\dataintelligence\api\thrift\DataIntelligenceServiceException $e) {
                return $e->getMessage();
            }
        } else {
            return 'Your old password is incorrect';
        }
    }
}