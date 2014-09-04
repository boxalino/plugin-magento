<?php

class Boxalino_Manager_Block_Adminhtml_Configuration_Index_View_Differences extends Mage_Adminhtml_Block_Widget_Form
{
    public function _prepareForm()
    {
        $versions = Mage::getSingleton('boxalino_manager/boxalino_configuration')->getConfigVersionNumber();
        $versions = Mage::helper('boxalino_manager')->prepareConfigVersions($versions[0], $versions[1]);
        $form = new Varien_Data_Form(array(
                'id' => 'differences_form',
                'action' => $this->getUrl('*/*/show', array('id' => $this->getRequest()->getParam('id'))),
                'method' => 'post',
                'enctype' => 'multipart/form-data'
            )
        );
        $form->addField('sourceConfigVersion', 'select', array(
            'label' => Mage::helper('boxalino_manager')->__('Source version'),
            'class' => 'required-entry',
            'required' => true,
            'name' => 'sourceVersion',
            'style' => 'width:300px;',
            'value' => count($versions)-1,
            'values' => $versions,
        ));
        $form->addField('destinationConfigVersion', 'select', array(
            'label' => Mage::helper('boxalino_manager')->__('Destination version'),
            'class' => 'required-entry',
            'required' => true,
            'name' => 'destinationVersion',
            'style' => 'width:300px;',
            'values' => $versions,
        ));

        $form->addField('compare', 'submit', array(
            'name' => 'fieldId',
            'style' => 'width:80px;',
            'value' => 'Compare',
        ));
        $form->setUseContainer(true);


        $this->setForm($form);
        return parent::_prepareForm();
    }
}