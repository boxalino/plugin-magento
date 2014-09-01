<?php

class Boxalino_Manager_Block_Adminhtml_Field_Create_View_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('employeeGrid');
        $this->setDefaultSort('id');
        $this->setDefaultDir('ASC');
        $this->setSaveParametersInSession(true);
    }

    protected function _prepareCollection()
    {
        $collection = Mage::getModel('boxalino_manager/boxalino_field')->getFields();
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    protected function _prepareColumns()
    {
        $this->addColumn('id', array(
            'header' => Mage::helper('boxalino_manager')->__('ID'),
            'align' => 'left',
            'width' => '10px',
            'index' => 'id',
        ));

        $this->addColumn('edit',
            array(
                'header'    => Mage::helper('boxalino_manager')->__('Edit'),
                'width'     => '50px',
                'type'      => 'action',
                'getter'     => 'getId',
                'actions'   => array(
                    array(
                        'caption' => Mage::helper('boxalino_manager')->__('Edit'),
                        'url'     => array(
                            'base'=>'*/*/edit',
                        ),
                        'field'   => 'id'
                    )
                ),
                'filter'    => false,
                'sortable'  => false,
                'index'     => 'stores',
            ));
        $this->addColumn('delete',
            array(
                'header'    => Mage::helper('boxalino_manager')->__('Delete'),
                'width'     => '50px',
                'type'      => 'action',
                'getter'     => 'getId',
                'actions'   => array(
                    array(
                        'caption' => Mage::helper('boxalino_manager')->__('Delete'),
                        'url'     => array(
                            'base'=>'*/*/delete',
                        ),
                        'field'   => 'id'
                    )
                ),
                'filter'    => false,
                'sortable'  => false,
                'index'     => 'stores',
            ));
        return parent::_prepareColumns();
    }
}