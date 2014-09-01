<?php
class Boxalino_Manager_Block_Adminhtml_Field_Create_View_Form extends Mage_Adminhtml_Block_Widget_Form
{
    protected function _prepareForm()
    {
        $form = new Varien_Data_Form(array(
                'id' => 'edit_form',
                'action' => $this->getUrl('*/*/save', array('id' => $this->getRequest()->getParam('id'))),
                'method' => 'post',
                'enctype' => 'multipart/form-data'
            )
        );

        $form->addField('fieldId', 'text', array(
            'label' => Mage::helper('boxalino_manager')->__('Field name'),
            'class' => 'required-entry',
            'required' => true,
            'name' => 'fieldId',
            'style' => 'width:300px;'
        ));

        $form->setUseContainer(true);
        $this->setForm($form);
        return parent::_prepareForm();
    }
}