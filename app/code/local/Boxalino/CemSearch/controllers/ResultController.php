<?php
	require_once "Mage/CatalogSearch/controllers/ResultController.php";

	class Boxalino_CemSearch_ResultController extends Mage_CatalogSearch_ResultController{
		public function indexAction(){
			echo 'your method has been rewrited !!<pre>';

			$query = Mage::helper('catalogsearch')->getQuery();

			//print_r($query);
			echo '</pre>';

			parent::indexAction();
		}
	}