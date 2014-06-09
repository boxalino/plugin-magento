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
        $storeConfig = Mage::getStoreConfig('Boxalino_CemSearch/backend');
//        var_dump($storeConfig);
//        var_dump($params);

        Mage::helper('Boxalino_CemSearch')->__loadClass('P13nConfig');
        Mage::helper('Boxalino_CemSearch')->__loadClass('P13nSort');
        Mage::helper('Boxalino_CemSearch')->__loadClass('P13nAdapter');

//        $p13nConfig = new P13nConfig('cdn.bx-cloud.com', 'testshop', 'codete', 'oNaeGhahVoo7', '.example.com', 'testshop');
        $p13nConfig = new P13nConfig(
            $storeConfig['host'],
            $storeConfig['account'],
            $storeConfig['username'],
            $storeConfig['password'],
            $storeConfig['domain'],
            $storeConfig['indexId']
        );
        $p13nSort = new P13nSort();
        $p13nSort->push('score', true);   // score / discountedPrice / title_en
        //$p13nSort->push('discountedPrice', true);
        //$p13nSort->push('title_en', true);


        $p13n = new P13nAdapter($p13nConfig);

        // TODO this patameters shoud be moved to settings in admin panel

        $p13n->setupInquiry('quick_search', $params['name'], 'en', array('entity_id', 'discountedPrice', 'title_en', 'score'), $p13nSort, 0, 25);
//        $p13n->setupInquiry('quick_search', $params['name'], 'en', array('entity_id', 'discountedPrice', 'title_en', 'score'), $p13nSort, 0, 25);
        //$p13n->setupInquiry('recommendation_widget', 'Luggage', 'en', array('entity_id', 'discountedPrice', 'title'), $p13nSort, 0, 25);
        $p13n->search();
        $entity_ids = $p13n->getEntitiesIds();
//        print_r($entity_ids);
        unset($p13n);


        try {
//             Mage::getSingleton('catalogsearch/advanced')->addFilters(array('price' => array('from' => 0, 'to' => 99999999999999999999999999999999999)));
//            Mage::getSingleton('catalogsearch/advanced')->addFilters(array('name' => 'xxxaa'));
            Mage::getSingleton('catalogsearch/advanced')->addFilters($this->getRequest()->getQuery(), $entity_ids);
        } catch (Mage_Core_Exception $e) {
            Mage::getSingleton('catalogsearch/session')->addError($e->getMessage());
            $this->_redirectError(
                Mage::getModel('core/url')
                    ->setQueryParams($this->getRequest()->getQuery())
                    ->getUrl('*/*/')
            );
        }
        array('color' => 'black');

//        var_dump(Mage::getSingleton('catalogsearch/advanced')->getSearchCriterias());
//        var_dump(get_class_methods($this->getRequest()));
//        var_dump(($this->getRequest()->getQuery()));
//        var_dump(get_class_methods(Mage::getSingleton('catalogsearch/advanced')));

        $this->_initLayoutMessages('catalog/session');
        $this->renderLayout();




    }
}