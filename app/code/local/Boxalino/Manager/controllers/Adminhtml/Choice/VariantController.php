<?php

class Boxalino_Manager_Adminhtml_Choice_VariantController extends Mage_Adminhtml_Controller_Action
{
    public function indexAction()
    {
        $this->loadLayout()->renderLayout();
        Mage::getModel('boxalino_manager/Boxalino_ChoiceVariant');
    }
}