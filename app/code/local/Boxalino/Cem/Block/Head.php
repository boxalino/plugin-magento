<?php

	class Boxalino_Cem_Block_Head extends Boxalino_Cem_Block_Abstract{
		public function printScripts(){
			$session =  Mage::getSingleton('boxalinocem/session');

			$scripts = $session->getScripts(false);
			foreach($scripts as $script){
				echo $script;
			}
			$session->clearScripts();

			echo Mage::helper('boxalinocem')->reportPageView();

			$route = Mage::app()->getFrontController()->getRequest()->getRouteName();
			$controller = Mage::app()->getFrontController()->getRequest()->getControllerName();
			if( $route == 'catalogsearch' && $controller == 'result' ){
				echo Mage::helper('boxalinocem')->reportSearch($_GET['q']);
			}

		}
	}
