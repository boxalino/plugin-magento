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
    protected $_suggestDataProducts = null;


    protected function _toHtml()
    {

        if (Mage::getStoreConfig('Boxalino_General/general/enabled', 0) == 0) {
            return null;
        }

        $html = '';

        if (!$this->_beforeToHtml()) {
            return $html;
        }

        $suggestData = $this->getSuggestData();
        if (!($count = count($suggestData))) {
            return '<ul><li>' . $this->helper('catalogsearch')->getQueryText() . '</li></ul>';
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

            $html .= '<li data-word="' . $item['id'] . '" title="' . $this->escapeHtml($item['title']) . '" class="' . $item['row_class'] . '">'
                . '<span class="amount">' . $item['num_of_results'] . '</span>' . $this->escapeHtml($item['title']) . '</li>';
        }
        $html .= '</ul><ul>';

        $first_word = true;
        foreach ($this->_suggestDataProducts as $key => $word) {
            foreach ($word as $product) {
                $product = Mage::getModel('catalog/product')->load($product['id']);
                $class = '';
                if (!$first_word) {
                    $class = 'hide';
                }
                $html .= '<li data-word="' . $key . '" class="product-autocomplete ' . $class . '" title="' . $this->escapeHtml($product->getName()) . '">';
                $html .= '<a href="' . $product->getProductUrl() . '" ><table><tr><td>';
                $html .= '<img src="' . $product->getThumbnailUrl() . '" alt="' . $product->getName() . '" />';
                $html .= '</td><td>';
                $html .= '<span>' . $product->getName() . '</span>';
                $html .= '</td></tr></table></a>';
                $html .= '</li>';
            }
            $first_word = false;
        }

        $html .= '</ul>';

        return $html;
    }

    public function getSuggestData()
    {

        if (Mage::getStoreConfig('Boxalino_General/general/enabled', 0) == 0) {
            return parent::getSuggestData();
        }

        if (!$this->_suggestData) {
            $query = $this->helper('catalogsearch')->getQueryText();
            $counter = 0;
            $data = array();

            $storeConfig = Mage::getStoreConfig('Boxalino_General/general');

            $p13nConfig = new Boxalino_CemSearch_Helper_P13n_Config(
                $storeConfig['host'],
                Mage::helper('Boxalino_CemSearch')->getAccount(),
                $storeConfig['p13n_username'],
                $storeConfig['p13n_password'],
                $storeConfig['domain']
            );
            $p13n = new Boxalino_CemSearch_Helper_P13n_Adapter($p13nConfig);

            $generalConfig = Mage::getStoreConfig('Boxalino_General/search');

            if ($query) {
                $p13n->autocomplete('*' . $query . '*', $generalConfig['autocomplete_limit'], $generalConfig['autocomplete_products_limit']);
                $collection = $p13n->getAutocompleteEntities();
            } else {
                $collection = array();
            }

            foreach ($collection as $item) {

                if ($item['hits'] <= 0) {
                    continue;
                }

                $_data = array(
                    'id' => substr(md5($item['text']), 0, 10),
                    'title' => $item['text'],
                    'row_class' => (++$counter) % 2 ? 'odd' : 'even',
                    'num_of_results' => $item['hits']
                );

                if ($item['text'] == $query) {
                    array_unshift($data, $_data);
                } else {
                    $data[] = $_data;
                }
            }
            $this->_suggestData = $data;
            $this->_suggestDataProducts = $p13n->getAutocompleteProducts();
        }
        return $this->_suggestData;
    }
    /*
     *
    */
}
