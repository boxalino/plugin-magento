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

	    $query = $this->helper('catalogsearch')->getQueryText();
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

            $html .=  '<li title="' . $this->escapeHtml($item['title']).'" class="'.$item['row_class'].'">'
                . '<span class="amount">'.$item['num_of_results'].'</span>'.$this->escapeHtml($item['title']).'</li>';
        }

        $html.= '</ul>';

        return $html;
    }

    public function getSuggestData()
    {
        if (!$this->_suggestData) {
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
	        $p13n = new P13nAdapter($p13nConfig);

	        $recommendationConfig = Mage::getStoreConfig('Boxalino_CemSearch/recommendation_widgets');

	        if($query){
		        $p13n->autocomplete('*' . $query . '*', $recommendationConfig['autocomplete_limit']);
		        $collection = $p13n->getAutocompleteEntities();
	        }else{
		        $collection = array();
	        }

            foreach ($collection as $item) {
	            $_data = array(
                    'title' => $item['text'],
                    'row_class' => (++$counter)%2?'odd':'even',
                    'num_of_results' =>  $item['hits']
                );

                if ($item['text'] == $query) {
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
