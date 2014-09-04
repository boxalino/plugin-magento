<?php

class Boxalino_Manager_Block_Adminhtml_Configuration_Index_Differences extends Mage_Adminhtml_Block_Widget_Form_Container
{
    public function __construct()
    {
        parent::__construct();
        $this->_blockGroup = 'boxalino_manager';
        $this->_controller = 'adminhtml_configuration_index';
        $this->_mode = 'view';
        $this->_headerText = 'Show configuration differences';

        $this->_removeButton('save');
        $this->_removeButton('reset');
        $this->_removeButton('back');
    }

    protected function _prepareLayout()
    {
        if ($this->_blockGroup && $this->_controller && $this->_mode) {
            $this->setChild('form', $this->getLayout()->createBlock($this->_blockGroup . '/' . $this->_controller . '_' . $this->_mode . '_differences'));
        }
        return parent::_prepareLayout();
    }
}