<?php

	class Boxalino_CemSearch_Block_Monblock extends Mage_Core_Block_Template{
		public function methodblock(){
			$return = '';
			$collection = Mage::getModel('test/test')->getCollection()->setOrder('id_boxalino_person', 'asc');

			foreach($collection as $data){
				$return .= $data->getData('name') . ' ' . $data->getData('surname'). ' ' . $data->getData('phone') . '<br />';
			}
			Mage::getSingleton('adminhtml/session')->addSuccess('Cool Ca marche !!');
			return $return;
		}
	}