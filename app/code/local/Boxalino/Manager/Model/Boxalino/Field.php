<?php

class Boxalino_Manager_Model_Boxalino_Field extends Boxalino_Manager_Model_Boxalino
{
    public function getFields()
    {
        $fields = $this->_client->GetFields($this->_authentication, $this->_configDev);
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
    }

    public function createField($fieldId)
    {
        $this->_client->createField($this->_authentication, $this->_configDev, $fieldId);
    }

    public function updateField()
    {
        $t = new \com\boxalino\dataintelligence\api\thrift\Field;
        $t->fieldId = 'test';
        return($this->_client->updateField($this->_authentication, $this->_configDev, $t));
    }

    public function deleteField($fieldId)
    {
        $this->_client->deleteField($this->_authentication, $this->_configDev, $fieldId);
    }

}