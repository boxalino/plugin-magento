<?php

class Boxalino_CemSearch_Block_Head extends Boxalino_CemSearch_Block_Abstract
{
    public function printScripts()
    {
        $enabled = Mage::getStoreConfig('Boxalino_General/tracker/enabled');
        if ($enabled == 1) {
            $session = Mage::getSingleton('Boxalino_CemSearch_Model_Session');
            $scripts = $session->getScripts(false);
            Mage::helper('Boxalino_CemSearch')->scriptBegin();

            foreach ($scripts as $script) {
                echo $script;
            }
            $session->clearScripts();

            echo Mage::helper('Boxalino_CemSearch')->reportPageView();

            $route = Mage::app()->getFrontController()->getRequest()->getRouteName();
            $controller = Mage::app()->getFrontController()->getRequest()->getControllerName();
            if ($route == 'catalogsearch' && $controller == 'result') {
                echo Mage::helper('Boxalino_CemSearch')->reportSearch($_GET['q'], Mage::helper('Boxalino_CemSearch')->getFiltersValues($_GET));
            }


            Mage::helper('Boxalino_CemSearch')->scriptEnd();
        } else {
            return '';
        }
    }

    public function addExternalJS($args)
    {
        echo '<script type="text/javascript" src="//ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.min.js"></script>';
    }
}
