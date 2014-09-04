<?php

class Boxalino_Manager_Block_Adminhtml_Password_Index_View_Form extends Mage_Adminhtml_Block_Widget_Form
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

        $form->addField('oldPassword', 'password', array(
            'name' => 'oldPassword',
            'label' => 'Old password',
            'style' => 'width:300px;',
        ));

        $form->addField('newPassword', 'password', array(
            'name' => 'newPassword',
            'label' => 'New password',
            'style' => 'width:300px;',
        ));

        $form->addField('confirmNewPassword', 'password', array(
            'name' => 'confirmNewPassword',
            'label' => 'Confirm new password',
            'style' => 'width:300px;',
        ));

        $form->setUseContainer(true);
        $this->setForm($form);
        return parent::_prepareForm();
    }
}