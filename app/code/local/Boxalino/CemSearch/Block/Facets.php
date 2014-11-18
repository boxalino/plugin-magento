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
                        $filters[$filter]['url'] = $this->getTopFilterUrl($filter, '1', $allFilters[$filter][$key]['selected']);
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

    private function getTopFilterUrl($name, $value, $selected)
    {
        $multioption = Mage::getStoreConfig('Boxalino_General/filter/top_filters_multioption');
        $currentUrl = Mage::helper('core/url')->getCurrentUrl();
        if($multioption == true) {
            if ($selected === false) {
                $url = $currentUrl . '&bx_' . $name . '[0]' . '=' . $value;
            } else {
                $url = str_replace('&bx_' . $name . '[0]' . '=' . $value, '', $currentUrl);
            }
        } else {
            $topFilters = explode(',',Mage::getStoreConfig('Boxalino_General/filter/top_filters'));
            if ($selected === false) {
                foreach($topFilters as $filter) {
                    $currentUrl = str_replace('&bx_' . $filter . '[0]' . '=' . $value, '', $currentUrl);
                }
                $url = $currentUrl . '&bx_' . $name . '[0]' . '=' . $value;
            } else {
                $url = str_replace('&bx_' . $name . '[0]' . '=' . $value, '', $currentUrl);
            }
        }
        return $url;
    }

    private function getFilterUrl($name, $value, $selected, $ranged = false, $position = 0)
    {
        $multioption = Mage::getStoreConfig('Boxalino_General/filter/left_filters_multioption');
        $currentUrl = Mage::helper('core/url')->getCurrentUrl();
        if(!$ranged) {
            if($multioption == true) {
                if ($selected === false) {
                    $url = $currentUrl . '&bx_' . $name . '[' . $position . ']' . '=' . $value;
                } else {
                    $url = str_replace('&bx_' . $name . '[' . $position . ']' . '=' . $value, '', $currentUrl);
                }
            } else {
                $position = 0;
                if ($selected === false) {
                    if(isset($_REQUEST['bx_' . $name])) {
                        foreach($_REQUEST['bx_' . $name] as $val) {
                            $currentUrl = str_replace('&bx_' . $name . '[' . $position . ']' . '=' . $val, '', $currentUrl);
                        }
                    }
                    $url = $currentUrl . '&bx_' . $name . '[' . $position . ']' . '=' . $value;
                } else {
                    $url = str_replace('&bx_' . $name . '[' . $position . ']' . '=' . $value, '', $currentUrl);
                }
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
            $data['stringValue'] = array('min' => $values['rangeFromInclusive'], 'max' => $values['rangeToExclusive']);
            $data['url'] = $this->getFilterUrl($filter, array('from' => $values['rangeFromInclusive'], 'to' => $values['rangeToExclusive']), $values['selected'], true, $position);
        } elseif($values['hierarchyId'] > 0) {
            $data['hierarchyId'] = $values['hierarchyId'][0];
            $data['url'] = $this->getFilterUrl($filter, $values['hierarchy'][0], $values['selected'], false, $position);
            $data['stringValue'] = $values['hierarchy'][0];
        } else {
            $data['url'] = $this->getFilterUrl($filter, $values['stringValue'], $values['selected'], false, $position);
            $data['stringValue'] = $values['stringValue'];
        }
        $data['hitCount'] = $values['hitCount'];
        $data['selected'] = $values['selected'];
        return $data;
    }

    public function removeFilterFromUrl($url, $filter, $vals)
    {
        if(isset($_REQUEST['bx_'.$filter])) {
            foreach($vals as $val) {
                $key = array_search($val, $_REQUEST['bx_'.$filter]);
                if($key !== false) {
                    $url = str_replace('&bx_'.$filter.'['.$key.']='.$vals[$key], '', $url);
                }
            }
        }
        return $url;
    }

    public function getMinMaxValues($values)
    {
        $first = $values[0];
        $last = end($values);
        return array('min' => round(floor($first['stringValue']['min']), -2), 'max' => round(ceil($last['stringValue']['max'])), 1);
    }
}