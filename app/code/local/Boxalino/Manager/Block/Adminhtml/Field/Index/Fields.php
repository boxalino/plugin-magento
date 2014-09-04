<?php

class Boxalino_Manager_Block_Adminhtml_Field_Index_Fields extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        parent::__construct();
        $this->_controller = 'adminhtml_field_index_view';
        $this->_blockGroup = 'boxalino_manager';
        $this->_headerText = '';
        $this->_removeButton('add');
    }
}