<?php
/**
 * Created by: Szymon Nosal <szymon.nosal@codete.com>
 * Created at: 06.06.14 11:36
 */

/**
 * Catalog Search Controller
 *
 * @category   Mage
 * @package    Mage_CatalogSearch
 * @module     Catalog
 */
class Boxalino_CemSearch_AdvancedController extends Mage_Core_Controller_Front_Action
{

    public function indexAction()
    {
        $this->loadLayout();
        $this->_initLayoutMessages('catalogsearch/session');
        $this->renderLayout();
    }

    public function resultAction()
    {
        $this->loadLayout();

        $params = $this->getRequest()->getQuery();

        $tmp = Mage::getModel('catalogsearch/advanced');
        try {
            $tmp->addFilters($params);
        } catch (Mage_Core_Exception $e) {
            Mage::getSingleton('catalogsearch/session')->addError($e->getMessage());
            $this->_redirectError(
                Mage::getModel('core/url')
                    ->setQueryParams($params)
                    ->getUrl('*/*/')
            );
        }

        $criteria = $tmp->getSearchCriterias();
        unset($tmp);
        $lang = substr(Mage::app()->getLocale()->getLocaleCode(),0,2);

        //setUp Boxalino
        $storeConfig = Mage::getStoreConfig('Boxalino_CemSearch/backend');

        Mage::helper('Boxalino_CemSearch')->__loadClass('P13nConfig');
        Mage::helper('Boxalino_CemSearch')->__loadClass('P13nSort');
        Mage::helper('Boxalino_CemSearch')->__loadClass('P13nAdapter');

        $p13nConfig = new P13nConfig(
            $storeConfig['host'],
            $storeConfig['account'],
            $storeConfig['username'],
            $storeConfig['password'],
            $storeConfig['domain'],
            $storeConfig['indexId']
        );
        $p13nSort = new P13nSort();
        $p13nSort->push('score', true);

        $p13n = new P13nAdapter($p13nConfig);

        //setup search
        $p13n->setupInquiry('quick_search', $params['name'], 'en', array('entity_id', 'discountedPrice', 'title_en', 'score'), $p13nSort, 0, 1000);
//        var_dump($params);
//        var_dump($criteria);
        ## ADD FILTERS

        $skip = array('name');

        foreach($params as $key => $value){

            if(isset($value['from']) || isset($value['to'])){
                $from = null;
                $to = null;

                if(isset($params[$key]['from']) && $params[$key]['from'] != '' /* && $params['price']['from'] >= 0*/){
                    $from = $params[$key]['from'];
                }
                if(isset($params[$key]['to']) && $params[$key]['to'] != '' /*&& $params['price']['to'] >= 0*/){
                    $to = $params[$key]['to'];
                }

                $skip[] = $key;

                if($key == 'price'){
                    $key = 'discountedPrice';
                }

                $p13n->addFilterFromTo($key, $from, $to);

            }
//            elseif(is_array($value) || in_array($key, $not_filter) || $value == '') {
//                echo 'xxx   ';
//                continue;
//            } else{
//
//                if($key == 'description'){
//                    $p13n->addFilter('body', $value, $lang);
//                } else{
//                    $p13n->addFilter($key, $value);
//                }
//
//            }
        }

        foreach($criteria as $criterium){

            $name = strtolower($criterium['name']);

            if(in_array($name, $skip)){
                continue;
            }

            $values = explode(", ", $criterium['value']);

            if($name == 'description'){
                $name = 'body';
            }

            $p13n->addFilter($name, $values, $lang);
        }

        //get result from boxalino
        $p13n->search();
        $entity_ids = $p13n->getEntitiesIds();
        unset($p13n);
//
//        var_dump($entity_ids);

        try {
            Mage::getSingleton('catalogsearch/advanced')->addFilters($params, $entity_ids);
        } catch (Mage_Core_Exception $e) {
            Mage::getSingleton('catalogsearch/session')->addError($e->getMessage());
            $this->_redirectError(
                Mage::getModel('core/url')
                    ->setQueryParams($this->getRequest()->getQuery())
                    ->getUrl('*/*/')
            );
        }

        $this->_initLayoutMessages('catalog/session');
        $this->renderLayout();

    }
}