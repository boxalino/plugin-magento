<?php

	class Boxalino_CemSearch_AutocompleteController extends Mage_Core_Controller_Front_Action{
		public function indexAction(){

			$query = '*' . $_GET['query'] . '*';

			Mage::helper('Boxalino_CemSearch')->__loadClass('P13nConfig');
			Mage::helper('Boxalino_CemSearch')->__loadClass('P13nSort');
			Mage::helper('Boxalino_CemSearch')->__loadClass('P13nAdapter');

			$storeConfig = Mage::getStoreConfig('Boxalino_CemSearch/backend');

			$p13nConfig = new P13nConfig(
				$storeConfig['host'],
				$storeConfig['account'] ,
				$storeConfig['username'],
				$storeConfig['password'],
				$storeConfig['domain'],
				$storeConfig['indexId']
			);
			$p13nSort = new P13nSort();
			$p13nSort->push('score', true);   // score / discountedPrice / title_en
			$p13n = new P13nAdapter($p13nConfig);

			$recommendationConfig = Mage::getStoreConfig('Boxalino_CemSearch/recommendation_widgets');

			$p13n->setupInquiry($recommendationConfig['autocomplete'], $query, 'en', array('entity_id', 'title'), $p13nSort, 0, 25);
			//$p13n->setupInquiry('autocomplete', $query, 'en', array('entity_id', 'title'), $p13nSort, 0, 25);

			$p13n->search();
			$entities = $p13n->getEntities();
			unset($p13n); // !!!!!

			$suggestions = array();
			$titles = array();
			$searchConfig = Mage::getStoreConfig('Boxalino_CemSearch/recommendation_widgets');
			foreach($entities as $entity){
				if( ! in_array($entity['title'][0], $titles) &&
					(	! isset($searchConfig['autocomplete_limit']) ||
						$searchConfig['autocomplete_limit'] == 0 ||
						count($titles) < $searchConfig['autocomplete_limit']
					)
				){
					$titles[] = $entity['title'][0];
					$suggestions[] = array(
						'value' => $entity['title'][0],
						'data' => $entity['entity_id'][0]
					);
				}

			}

			$values = array(
				'query' => $query,
				'suggestions' => $suggestions
			);

			echo json_encode($values);
			die;
		}
	}