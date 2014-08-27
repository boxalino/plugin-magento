<?php

class Boxalino_Manager_Block_Adminhtml_Password_Form extends Mage_Adminhtml_Block_Widget_Form_Container
{
    public function __construct()
    {
        parent::__construct();

        $this->_objectId = 'id';
        $this->_blockGroup = 'boxalino_manager';
        $this->_controller = 'adminhtml_password';

        $this->_updateButton('save', 'label', Mage::helper('boxalino_manager')->__('Change'));

        $this->_removeButton('delete');
        $this->_removeButton('reset');
        $this->_removeButton('back');
    }

    public function getHeaderText()
    {
        return Mage::helper('boxalino_manager')->__('Change Password');
    }
}