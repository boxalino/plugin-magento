<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category    Mage
 * @package     Mage_CatalogSearch
 * @copyright   Copyright (c) 2014 Magento Inc. (http://www.magentocommerce.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Autocomplete queries list
 */

require_once "Mage/CatalogSearch/Block/Autocomplete.php";

class Boxalino_CemSearch_Block_Autocomplete extends Mage_CatalogSearch_Block_Autocomplete
{
    protected $_suggestData = null;


	public function aaa(){
		echo 'ok2';
	}

    protected function _toHtml()
    {
        $html = '';

        if (!$this->_beforeToHtml()) {
            return $html;
        }

        $suggestData = $this->getSuggestData();
        if (!($count = count($suggestData))) {
            return $html;
        }

        $count--;

        $html = '<ul><li style="display:none"></li>';
        foreach ($suggestData as $index => $item) {
            if ($index == 0) {
                $item['row_class'] .= ' first';
            }

            if ($index == $count) {
                $item['row_class'] .= ' last';
            }

            $html .=  '<li title="'.$this->escapeHtml($item['title']).'" class="'.$item['row_class'].'">'
                . '<span class="amount">'.$item['num_of_results'].'</span>'.$this->escapeHtml($item['title']).'</li>';
        }

        $html.= '</ul>';

        return $html;
    }

    public function getSuggestData()
    {
        if (!$this->_suggestData) {
            $collection = $this->helper('catalogsearch')->getSuggestCollection();
            $query = $this->helper('catalogsearch')->getQueryText();
            $counter = 0;
            $data = array();


	        Mage::helper('Boxalino_CemSearch')->__loadClass('P13nConfig');
	        Mage::helper('Boxalino_CemSearch')->__loadClass('P13nSort');
	        Mage::helper('Boxalino_CemSearch')->__loadClass('P13nAdapter');

	        $storeConfig = Mage::getStoreConfig('Boxalino_CemSearch/backend');

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
	        $p13n = new P13nAdapter($p13nConfig);

	        $recommendationConfig = Mage::getStoreConfig('Boxalino_CemSearch/recommendation_widgets');

	        //$p13n->setupInquiry($recommendationConfig['quick_search'], '*' . $query . '*', substr(Mage::app()->getLocale()->getLocaleCode(),0,2) , array('entity_id', 'title'), $p13nSort, 0, 25);
	        $p13n->setupInquiry('quick_search', '*' . $query . '*', substr(Mage::app()->getLocale()->getLocaleCode(),0,2) , array('entity_id', 'title'), $p13nSort, 0, 25);

	        if(isset($_GET['cat'])){
		        $p13n->addFilterCategory($_GET['cat']);
	        }
	        echo '<pre>';
	        $p13n->autocomplete();
	        print_r('aaaa');
	        die;
	        $p13n->search();
	        $entity_ids = $p13n->getEntitiesIds();
	        unset($p13n); // !!!!!



	        print_r($entity_ids);


            foreach ($collection as $item) {
	            $_data = array(
                    'title' => $item->getQueryText(),
                    'row_class' => (++$counter)%2?'odd':'even',
                    'num_of_results' => $item->getNumResults()
                );

                if ($item->getQueryText() == $query) {
                    array_unshift($data, $_data);
                }
                else {
                    $data[] = $_data;
                }
            }
            $this->_suggestData = $data;
        }
        return $this->_suggestData;
    }
/*
 *
*/
}
