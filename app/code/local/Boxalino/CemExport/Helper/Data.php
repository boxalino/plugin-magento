<?php

	class Boxalino_CemExport_Helper_Data extends Mage_Core_Helper_Data{

		public function __construct(){
			spl_autoload_register(array('Boxalino_CemExport_Helper_Data', '__loadClass'), TRUE, TRUE);
		}

		public static function __loadClass($name){
			if(strpos($name, 'CEM_') === 0){
				include_once(Mage::getModuleDir('', 'Boxalino_CemExport') . '/Lib/' . $name . '.class.php');
			}
		}

	}
