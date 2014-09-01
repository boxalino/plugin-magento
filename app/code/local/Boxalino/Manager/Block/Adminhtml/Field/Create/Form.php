<?php

class Boxalino_Manager_Block_Adminhtml_Field_Create_Form extends Mage_Adminhtml_Block_Widget_Form_Container
{
    public function __construct()
    {
        parent::__construct();
        $this->_objectId = 'id';
        $this->_blockGroup = 'boxalino_manager';
        $this->_controller = 'adminhtml_field_create';
        $this->_mode = 'view';
        $this->_headerText = 'test';

        $this->_updateButton('save', 'label', Mage::helper('boxalino_manager')->__('Create field'));
        $this->_removeButton('back');
        $this->_removeButton('reset');
    }
}