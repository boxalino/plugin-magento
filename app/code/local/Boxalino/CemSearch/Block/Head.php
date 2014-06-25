<?php

	class Boxalino_CemSearch_Block_Head extends Boxalino_CemSearch_Block_Abstract{
		public function printScripts(){

			$session =  Mage::getSingleton('Boxalino_CemSearch_Model_Session');

			$scripts = $session->getScripts(false);
			foreach($scripts as $script){
				echo $script;
			}
			$session->clearScripts();

			echo Mage::helper('Boxalino_CemSearch')->reportPageView();

			$route = Mage::app()->getFrontController()->getRequest()->getRouteName();
			$controller = Mage::app()->getFrontController()->getRequest()->getControllerName();
			if( $route == 'catalogsearch' && $controller == 'result' ){
				echo Mage::helper('Boxalino_CemSearch')->reportSearch($_GET['q']);
			}

		}
	}
