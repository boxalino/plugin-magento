<?php

class Boxalino_Manager_Block_Adminhtml_Configuration_Index_View_Actions extends Mage_Adminhtml_Block_Widget_Form
{
    public function _prepareForm()
    {
        $versions = Mage::getSingleton('boxalino_manager/boxalino_configuration')->getConfigVersionNumber();
        $versions = Mage::helper('boxalino_manager')->prepareConfigVersions($versions[0], $versions[1]);

        $form = new Varien_Data_Form(array(
                'id' => 'differences_form',
                'action' => $this->getUrl('*/*/action', array('id' => $this->getRequest()->getParam('id'))),
                'method' => 'post',
                'enctype' => 'multipart/form-data'
            )
        );
        $form->addField('configVersion', 'select', array(
            'label' => Mage::helper('boxalino_manager')->__('Configuration version'),
            'class' => 'required-entry',
            'required' => true,
            'name' => 'configVersion',
            'style' => 'width:300px;',
            'values' => $versions
        ));

        $form->addField('clone', 'submit', array(
            'name' => 'action',
            'style' => 'width:80px;',
            'value' => 'Clone'
        ));

        $form->addField('publish', 'submit', array(
            'name' => 'action',
            'style' => 'width:80px;',
            'value' => 'Publish'
        ));
        $form->setUseContainer(true);


        $this->setForm($form);
        return parent::_prepareForm();
    }
}