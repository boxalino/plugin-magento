<?php

class Boxalino_Manager_Model_Boxalino_Field extends Boxalino_Manager_Model_Boxalino
{
    public function getFields()
    {
        try {
            $fields = $this->_client->GetFields($this->_authentication, $this->_configVersion);
            if(empty($fields)) {
                Mage::getSingleton('adminhtml/session')->addError('Fields loading is impossible');
            } else {
                $collection = new Varien_Data_Collection();
                foreach ($fields as $field) {
                    $rawObj = new Varien_Object();
                    $rawObj->setId($field->fieldId);
                    $collection->addItem($rawObj);
                }
                return $collection;
            }

        } catch (Exception $e) {
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
        }
    }

    public function createField()
    {

    }

    public function updateField()
    {

    }

    public function deleteField()
    {

    }

}