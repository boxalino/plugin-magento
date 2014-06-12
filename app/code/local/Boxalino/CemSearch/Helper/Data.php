<?php

	class Boxalino_CemSearch_Helper_Data extends Mage_Core_Helper_Data {
		public function __construct() {
			spl_autoload_register(array('Boxalino_CemSearch_Helper_Data', '__loadClass'), TRUE, TRUE);
		}

		public static function __loadClass($name, $isCem = false, $ext = '.class') {
			$files = array('P13nAdapter','P13nConfig','P13nSort', 'P13nRecommendation');
			if(in_array($name, $files)){
				include_once(Mage::getModuleDir('', 'Boxalino_CemSearch').'/Helper/'.$name.'.class.php');

			}elseif (strpos($name, 'CEM_') !== false  || strpos($name, 'P13n') !== false || $isCem) {
				include_once(Mage::getModuleDir('', 'Boxalino_CemSearch').'/Lib/'.$name.$ext.'.php');
			}
		}
	}
