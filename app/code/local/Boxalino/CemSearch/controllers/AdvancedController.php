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
        try {
//             Mage::getSingleton('catalogsearch/advanced')->addFilters(array('price' => array('from' => 0, 'to' => 99999999999999999999999999999999999)));
//            Mage::getSingleton('catalogsearch/advanced')->addFilters(array('name' => 'xxxaa'));
            Mage::getSingleton('catalogsearch/advanced')->addFilters($this->getRequest()->getQuery(), range(400,450));
        } catch (Mage_Core_Exception $e) {
            Mage::getSingleton('catalogsearch/session')->addError($e->getMessage());
            $this->_redirectError(
                Mage::getModel('core/url')
                    ->setQueryParams($this->getRequest()->getQuery())
                    ->getUrl('*/*/')
            );
        }

//        var_dump(Mage::getSingleton('catalogsearch/advanced')->getSearchCriterias());
//        var_dump(get_class_methods($this->getRequest()));
        var_dump(($this->getRequest()->getQuery()));
//        var_dump(get_class_methods(Mage::getSingleton('catalogsearch/advanced')));

        $this->_initLayoutMessages('catalog/session');
        $this->renderLayout();




    }
}