<?php

class Boxalino_Manager_Block_Adminhtml_Field_Index_View_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('fieldsGrid');
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
            'width' => '100%',
            'index' => 'id',
            'sortable' => false
        ));

        $this->addColumn('delete',
            array(
                'header' => Mage::helper('boxalino_manager')->__('Delete'),
                'width' => '50px',
                'type' => 'action',
                'getter' => 'getId',
                'actions' => array(
                    array(
                        'caption' => Mage::helper('boxalino_manager')->__('Delete'),
                        'url' => array(
                            'base' => '*/*/delete',
                        ),
                        'field' => 'fieldId'
                    )
                ),
                'filter' => false,
                'sortable' => false,
                'index' => 'stores',
            ));
        return parent::_prepareColumns();
    }

    protected function _prepareMassaction()
    {
        parent::_prepareMassaction();
        $this->setMassactionIdField('entity_id');
        $this->getMassactionBlock()->setFormFieldName('fieldsToDelete');
        // Append new mass action option
        $this->getMassactionBlock()->addItem(
            'fieldsGrid',
            array('label' => $this->__('Delete all fields'),
                'url' => $this->getUrl('*/*/massdelete')
            )
        );

        return $this;
    }

    protected function _addColumnFilterToCollection($column)
    {
        $id = null;
        $filter = $this->getParam($this->getVarNameFilter(), null);
        if (!is_null($filter)) {
            $filter = $this->helper('adminhtml')->prepareFilterString($filter);
            if (isset($filter['id'])) {
                //if there is a filter by status you may need to show the archive items
                $id = $filter['id'];
                $this->setCollection(Mage::getModel('boxalino_manager/boxalino_field')->getFields($id));
            }
        }
    }
}