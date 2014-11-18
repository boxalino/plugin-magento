<?php
class Boxalino_CemSearch_Block_Facets extends Mage_Core_Block_Template
{
    private $_allFilters = array();

    public function __construct()
    {
        $storeConfig = Mage::getStoreConfig('Boxalino_General/general');

        $p13nConfig = new Boxalino_CemSearch_Helper_P13n_Config(
            $storeConfig['host'],
            Mage::helper('Boxalino_CemSearch')->getAccount(),
            $storeConfig['p13n_username'],
            $storeConfig['p13n_password'],
            $storeConfig['domain']
        );

        $adapter = new Boxalino_CemSearch_Helper_P13n_Adapter($p13nConfig);
        $this->_allFilters = $adapter->getFacetsData();
    }
    public function getTopFilters()
    {
        $filters = array();
        $topFilters = explode(',',Mage::getStoreConfig('Boxalino_General/filter/top_filters'));
        $titles = explode(',',Mage::getStoreConfig('Boxalino_General/filter/top_filters_title'));
        $i = 0;
        $allFilters = $this->_allFilters;
        foreach($topFilters as $filter) {
            if(isset($allFilters[$filter])) {
                foreach($allFilters[$filter] as $key => $values) {
                    if($values['stringValue'] == 1) {
                        $filters[$filter] = $allFilters[$filter][$key];
                        $filters[$filter]['title'] = $titles[$i];
                        $filters[$filter]['url'] = $this->getFilterUrl($filter, '1', $allFilters[$filter][$key]['selected'], false);
                        $filters[$filter]['selected'] = $allFilters[$filter][$key]['selected'];
                    }
                }
            }
            $i++;
        }
        return $filters;
    }

    public function getLeftFilters()
    {
        $filters = array();
        $leftFilters = explode(',',Mage::getStoreConfig('Boxalino_General/filter/left_filters_normal'));
        $leftFiltersTitles = explode(',',Mage::getStoreConfig('Boxalino_General/filter/left_filters_normal_title'));
        $i = 0;
        $allFilters = $this->_allFilters;
        foreach($leftFilters as $filterString) {
            $position = 0;
            $filter = explode(':', $filterString);
            $filters[$filter[0]] = array('title' => $leftFiltersTitles[$i], 'values' => array());
            if(isset($allFilters[$filter[0]])) {
                foreach($allFilters[$filter[0]] as $key => $values) {
                    $filters[$filter[0]]['values'][] = $this->returnImportantValues($values, $filter[1], $filter[0], $position);
                    $position++;
                }
            }
            if(count($filters[$filter[0]]['values']) == 0) {
                unset($filters[$filter[0]]);
            }
            $i++;
        }
        return $filters;
    }

    private function getFilterUrl($name, $value, $selected, $ranged = false, $position = 0)
    {
        $currentUrl = Mage::helper('core/url')->getCurrentUrl();
        if(!$ranged) {
            if ($selected === false) {
                $url = $currentUrl . '&bx_' . $name.'['.$position.']'. '=' . $value;
            } else {
                $url = str_replace('&bx_' . $name.'['.$position.']'. '=' . $value, '', $currentUrl);
            }
        } else {
            if ($selected === false) {
                $url = $currentUrl . '&bx_' . $name.'['.$position.']'. '=' . $value['from'].'-'.$value['to'];
            } else {
                $url = str_replace('&bx_' . $name.'['.$position.']'. '=' . $value['from'].'-'.$value['to'], '', $currentUrl);
            }
        }

        return $url;
    }

    private function returnImportantValues($values, $option, $filter, $position)
    {
        $data = array();
        if($option == 'ranged') {
            $data['stringValue'] = Mage::helper('core')->currency($values['rangeFromInclusive'], true, false) . ' - ' . Mage::helper('core')->currency($values['rangeToExclusive'], true, false);
            $data['url'] = $this->getFilterUrl($filter, array('from' => $values['rangeFromInclusive'], 'to' => $values['rangeToExclusive']), $values['selected'], true, $position);
        } elseif($values['hierarchyId'] > 0) {
            $data['hierarchyId'] =
            $data['url'] = $this->getFilterUrl($filter, $values['hierarchyId'], $values['selected'], false, $position);
            $data['stringValue'] = $values['hierarchy'][0];
        } else {
            $data['url'] = $this->getFilterUrl($filter, $values['stringValue'], $values['selected'], false, $position);
            $data['stringValue'] = $values['stringValue'];
        }
        $data['hitCount'] = $values['hitCount'];
        $data['selected'] = $values['selected'];
        return $data;
    }
}