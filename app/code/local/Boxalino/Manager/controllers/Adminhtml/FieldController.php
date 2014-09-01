<?php

class Boxalino_Manager_Adminhtml_FieldController extends Mage_Adminhtml_Controller_Action
{
    public function indexAction()
    {
        $this->loadLayout();
        $this->_addContent($this->getLayout()->createBlock('boxalino_manager/adminhtml_field_create_form'));
        $this->_addContent($this->getLayout()->createBlock('boxalino_manager/adminhtml_field_create_fields'));
        $this->renderLayout();
    }

    public function editAction()
    {
        
    }

    public function createAction()
    {

    }

    public function updateAction()
    {

    }

    public function removeAction()
    {

    }
}