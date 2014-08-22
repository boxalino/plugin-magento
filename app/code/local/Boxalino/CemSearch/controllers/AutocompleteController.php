<?php

class Boxalino_CemSearch_AutocompleteController extends Mage_Core_Controller_Front_Action
{
    public function indexAction()
    {

        $query = '*' . $_GET['query'] . '*';

        Mage::helper('Boxalino_CemSearch')->__loadClass('P13nConfig');
        Mage::helper('Boxalino_CemSearch')->__loadClass('P13nSort');
        Mage::helper('Boxalino_CemSearch')->__loadClass('P13nAdapter');

        $storeConfig = Mage::getStoreConfig('Boxalino_General/general');

        $p13nConfig = new P13nConfig(
            $storeConfig['host'],
            Mage::helper('Boxalino_CemSearch')->getAccount(),
            $storeConfig['p13n_username'],
            $storeConfig['p13n_password'],
            $storeConfig['domain']
        );
        $p13nSort = new P13nSort();
        $p13nSort->push('score', true);   // score / discountedPrice / title_en
        $p13n = new P13nAdapter($p13nConfig);

        $generalConfig = Mage::getStoreConfig('Boxalino_CemSearch/general');
        $lang = substr(Mage::app()->getLocale()->getLocaleCode(), 0, 2);

        $p13n->setupInquiry($generalConfig['autocomplete'], $query, $lang, array($generalConfig['entity_id'], 'title'), $p13nSort, 0, 25);

        $p13n->search();
        $entities = $p13n->getEntities();
        unset($p13n); // !!!!!

        $suggestions = array();
        $titles = array();
        foreach ($entities as $entity) {
            if (!in_array($entity['title'][0], $titles) &&
                (!isset($generalConfig['autocomplete_limit']) ||
                    $generalConfig['autocomplete_limit'] == 0 ||
                    count($titles) < $generalConfig['autocomplete_limit']
                )
            ) {
                $titles[] = $entity['title'][0];
                $suggestions[] = array(
                    'value' => $entity['title'][0],
                    'data' => $entity[Mage::getStoreConfig('Boxalino_General/search/entity_id')][0]
                );
            }

        }

        $values = array(
            'query' => $query,
            'suggestions' => $suggestions
        );

        echo json_encode($values);

        return $values;
    }
}