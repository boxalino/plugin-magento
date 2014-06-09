<?php

	class Boxalino_CemSearch_Helper_Data extends Mage_Core_Helper_Data {
		public function __construct() {
			spl_autoload_register(array('Boxalino_CemSearch_Helper_Data', '__loadClass'), TRUE, TRUE);
		}

		public static function __loadClass($name) {
			if (strpos($name, 'CEM_') !== false  || strpos($name, 'P13n') !== false) {
				include_once(Mage::getModuleDir('', 'Boxalino_CemSearch').'/Lib/'.$name.'.class.php');
			}
		}
	}
