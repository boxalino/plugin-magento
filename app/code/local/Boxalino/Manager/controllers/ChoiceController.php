<?php

class Boxalino_Manager_ChoiceController extends Mage_Adminhtml_Controller_Action
{
    public function indexAction()
    {
        $this->loadLayout()->renderLayout();
        Mage::getModel('boxalino_manager/Boxalino_Choice');
    }
}