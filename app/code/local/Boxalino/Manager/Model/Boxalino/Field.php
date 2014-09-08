<?php

class Boxalino_Manager_Model_Boxalino_Field extends Boxalino_Manager_Model_Boxalino
{
    public function getFields($filterId = null)
    {
        $fields = $this->_client->GetFields($this->_authentication, $this->_configDev);
        if(empty($fields)) {
            Mage::getSingleton('adminhtml/session')->addError('Fields loading is impossible');
        } else {
            $collection = new Varien_Data_Collection();
            foreach ($fields as $field) {
                if(is_null($filterId) || (!is_null($filterId) && strpos($field->fieldId, $filterId) !== false)) {
                    $rawObj = new Varien_Object();
                    $rawObj->setId($field->fieldId);
                    $collection->addItem($rawObj);
                }
            }
            return $collection;
        }
    }

    public function deleteField($fieldId)
    {
        $this->_client->deleteField($this->_authentication, $this->_configDev, $fieldId);
    }

}