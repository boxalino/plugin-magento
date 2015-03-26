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
    protected $_order = array();
    protected $_first = null;

    protected function _toHtml()
    {

        if (Mage::getStoreConfig('Boxalino_General/general/enabled') == 0) {
            return null;
        }

        $html = '';

        if (!$this->_beforeToHtml()) {
            return $html;
        }

        $suggestData = $this->getSuggestData();
        if (!($count = count($suggestData)) && !count($this->_suggestDataProducts)) {
            return '<ul><li>' . $this->helper('catalogsearch')->getQueryText() . '</li></ul>';
        } elseif(!($count = count($suggestData)) && $this->_suggestDataProducts){
            return '<ul class="queries"><li style="display:none"></li></ul>' . $this->prepareDataProducts();
        }

        $count--;
        $catalogSearchHelper = Mage::helper('catalogsearch');
        $autocompleteConfig = Mage::getStoreConfig('Boxalino_General/autocomplete_extra');
        $resultUrl = $catalogSearchHelper->getResultUrl();

        $html = '<ul class="queries"><li style="display:none"></li>';
        foreach ($suggestData as $index => $item) {
            if ($index == 0) {
                $item['row_class'] .= ' first';
                $this->_first = $item['id'];
            }

            if ($index == $count) {
                $item['row_class'] .= ' last';
            }
            $all = false;
            if ($autocompleteConfig['enabled_for_all']) {
                $all = true;
            }

            if ($autocompleteConfig['enabled'] && count($item['facets']) > 0 && (($all == false && $index == 0) || ($all && $index >= 0))) {
                $html .= '<li data-word="' . $item['id'] . '" title="' . $this->escapeHtml($item['title']) . '" class="' . $item['row_class'] . '">'
                    . '<span class="query-title">' . $item['html'] . '</span><span class="amount">(' . $item['num_of_results'] . ')</span></li>';

                $c = 0;
                foreach ($item['facets'] as $facet) {
                    if ($c++ >= $autocompleteConfig['items']) {
                        break;
                    }
                    $html .= '<a class="facet" data-word="' . $facet['id'] . '" title="' . $this->escapeHtml($item['title']) . '&bx_categories[0]=' . urlencode($facet['href']) . '" href="' . $resultUrl . '?q=' . $this->escapeHtml($item['title']) . '&bx_categories[0]=' . urlencode($facet['href']) . '"><li data-word="' . $facet['id'] . '" class="facet ' . $item['row_class'] . '"  title="' . $this->escapeHtml($facet['title']) . '" ><span class="query-title">' . $this->escapeHtml($facet['title']) . '</span><span class="amount">(' . $facet['hits'] . ')</span></li></a>';
                }
            } else {
                $html .= '<li data-word="' . $item['id'] . '" title="' . $this->escapeHtml($item['title']) . '" class="' . $item['row_class'] . '">'
                    . '<span class="query-title">' . $item['html'] . '</span><span class="amount">(' . $item['num_of_results'] . ')</span></li>';
            }
        }
        $html .= '</ul>';

        $html .= $this->prepareDataProducts();

        return $html;
    }

    public function prepareDataProducts(){
        $html = '<ul class="products">';

        foreach ($this->_suggestDataProducts as $prods) {

//            var_dump($prods);

            foreach ($prods as $prod) {

                if (Mage::getStoreConfig('Boxalino_General/autocomplete_html/enabled')) {

//                    $prod = $prods;

                    $product = Mage::getModel('catalog/product')->load($prod['id']);
                    if ($prod['hash'] == $this->_first){
                        $html .= '<li data-word="' . $prod['hash'] . '" class="product-autocomplete" title="' . $this->escapeHtml($product->getName()) . '">';
                    } else{
                        $html .= '<li style="display:none" data-word="' . $prod['hash'] . '" class="product-autocomplete" title="' . $this->escapeHtml($product->getName()) . '">';
                    }
                    $html .= '<a href="' . $product->getProductUrl() . '" >';
                    $html .= '<div class="product-image"><img src="' . $product->getThumbnailUrl() . '" alt="' . $product->getName() . '" /></div>';
                    $html .= '<div class="product-title"><span>' . $product->getName() . '</span></div>';
                    $html .= '</a>';
                    $html .= '</li>';
                } else {
                    $html .= $this->prepareProductView($prod);
                }
            }

        }

        $html .= '</ul>';

        return $html;

    }

    private function prepareProductView($product){

        $html = '';

        if($product['hash'] == $this->_first){
            $html .= '<li style="display:block" data-word="' . $product['hash'] . '" class="product-autocomplete" title="' . $this->escapeHtml($product['title']) . '">';
        } else{
            $html .= '<li style="display:none" data-word="' . $product['hash'] . '" class="product-autocomplete" title="' . $this->escapeHtml($product['title']) . '">';
        }

        $html .= '<a href="' . $product['url'] . '" >';
        unset($product['url']);
        unset($product['hash']);
        unset($product[Mage::getStoreConfig('Boxalino_General/search/entity_id')]);

        foreach($this->_order as $f){
            if($f == 'image'){
                $html .= '<div class="product-' . $f . '"><img src="' . $product[$f] . '" alt="' . $product['title'] . '" style="max-height:75px; max-width:75px;" /></div>';
            } elseif(isset($product[$f])){
                $html .= '<div class="product-' . $f . '"><span>' . $product[$f] . '</span></div>';
            }
        }

        $html .= '</a>';
        $html .= '</li>';

        return $html;


    }

    public function getSuggestData()
    {

        if (Mage::getStoreConfig('Boxalino_General/general/enabled') == 0) {
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

            $map = array();
            if ($query) {
                $fi = explode(',', Mage::getStoreConfig('Boxalino_General/autocomplete_html/items'));

                if(Mage::getStoreConfig('Boxalino_General/autocomplete_html/enabled')){
                    $fields = array(Mage::getStoreConfig('Boxalino_General/search/entity_id'), 'title', 'score');
                } else{
                    $fields = array(Mage::getStoreConfig('Boxalino_General/search/entity_id'));
                    $map[Mage::getStoreConfig('Boxalino_General/search/entity_id')] = Mage::getStoreConfig('Boxalino_General/search/entity_id');
                    foreach($fi as $f){
                        $tmp = explode(':', $f);
                        $fields[] = $tmp[1];
                        $map[$tmp[1]] = $tmp[0];
                        $this->_order[] = $tmp[0];
                    }
                }

                $p13n->autocomplete($query, $generalConfig['autocomplete_limit'], $generalConfig['autocomplete_products_limit'], $fields);
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
                    'html' => $item['html'],
                    'row_class' => (++$counter) % 2 ? 'odd' : 'even',
                    'num_of_results' => $item['hits'],
                    'facets' => $item['facets']
                );

                if ($item['text'] == $query) {
                    array_unshift($data, $_data);
                } else {
                    $data[] = $_data;
                }
            }

            $this->_suggestData = $data;
            if(Mage::getStoreConfig('Boxalino_General/autocomplete_html/enabled')) {
                $this->_suggestDataProducts = $p13n->getAutocompleteProducts();
            } else{
                $this->_suggestDataProducts = $p13n->getAutocompleteProductsAll($map, $fields);
            }

        }
        return $this->_suggestData;
    }
    /*
     *
    */
}
