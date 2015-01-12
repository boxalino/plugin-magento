<?php

class Boxalino_CemSearch_Block_Facets extends Mage_Core_Block_Template
{
    private $_allFilters = array();
    public $maxLevel = array();

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

        $p13nSort = new Boxalino_CemSearch_Helper_P13n_Sort();
        $p13nSort->push('score', true);   // score / discountedPrice / title_en

        $generalConfig = Mage::getStoreConfig('Boxalino_General/search');
        $lang = substr(Mage::app()->getLocale()->getLocaleCode(), 0, 2);

        $limit = $generalConfig['quick_search_limit'] == 0 ? 1000 : $generalConfig['quick_search_limit'];

        $adapter->setupInquiry(
            $generalConfig['quick_search'],
            Mage::helper('catalogsearch')->getQueryText(),
            $lang,
            array($generalConfig['entity_id'], 'categories'),
            $p13nSort,
            0, $limit
        );

        $this->_allFilters = $adapter->getFacetsData();
    }

    public function getTopFilters()
    {
        $filters = array();
        $topFilters = explode(',', Mage::getStoreConfig('Boxalino_dla la wszystkich General/filter/top_filters'));
        $titles = explode(',', Mage::getStoreConfig('Boxalino_General/filter/top_filters_title'));
        $i = 0;
        $allFilters = $this->_allFilters;
        foreach ($topFilters as $filter) {
            if (isset($allFilters[$filter])) {
                foreach ($allFilters[$filter] as $key => $values) {
                    if ($values['stringValue'] == 1) {
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

    private function getTopFilterUrl($name, $value, $selected)
    {
        $multioption = Mage::getStoreConfig('Boxalino_General/filter/top_filters_multioption');
        $currentUrl = Mage::helper('core/url')->getCurrentUrl();
        if ($multioption == true) {
            if ($selected === false) {
                $url = $currentUrl . '&bx_' . $name . '[0]' . '=' . $value;
            } else {
                $url = str_replace('&bx_' . $name . '[0]' . '=' . $value, '', $currentUrl);
            }
        } else {
            $topFilters = explode(',', Mage::getStoreConfig('Boxalino_General/filter/top_filters'));
            if ($selected === false) {
                foreach ($topFilters as $filter) {
                    $currentUrl = str_replace('&bx_' . $filter . '[0]' . '=' . $value, '', $currentUrl);
                }
                $url = $currentUrl . '&bx_' . $name . '[0]' . '=' . urlencode($value);
            } else {
                $url = str_replace('&bx_' . $name . '[0]' . '=' . urlencode($value), '', $currentUrl);
            }
        }
        return $url;
    }

    public function getLeftFilters()
    {
        $filters = array();
        $leftFilters = explode(',', Mage::getStoreConfig('Boxalino_General/filter/left_filters_normal'));
        $leftFiltersTitles = explode(',', Mage::getStoreConfig('Boxalino_General/filter/left_filters_normal_title'));
        $i = 0;
        $allFilters = $this->_allFilters;
        foreach ($leftFilters as $filterString) {
            $position = 0;
            $filter = explode(':', $filterString);
            $filters[$filter[0]] = array('title' => $leftFiltersTitles[$i], 'values' => array());
            if (isset($allFilters[$filter[0]])) {
                if ($filter[1] == 'hierarchical') {
                    $filters[$filter[0]]['values'] = $this->returnTree($filter[0]);
                } else {
                    foreach ($allFilters[$filter[0]] as $key => $values) {
                        $filters[$filter[0]]['values'][] = $this->returnImportantValues($values, $filter[1], $filter[0], $position);
                        $position++;
                    }
                }
            }
            if (count($filters[$filter[0]]['values']) == 0) {
                unset($filters[$filter[0]]);
            }
            $i++;
        }
        return $filters;
    }

    private function returnTree($filter)
    {
        $results = array();
        $parents = $this->returnHierarchy($filter);
        $level = 0;
        if ($parents['display']['level'] == 2) {
            $results = $parents['values'][$parents['display']['level']];
            return $results;
        } else {
            $highestChild = array();
            $level = $parents['display']['level'];
            $parentId = 0;
            if (isset($parents['values'][$level])) {
                $highestLevelCount = count($parents['values'][$level]);
                foreach ($parents['values'][$level] as $value) {
                    $parentId = $parents['display']['parentId'];
                    if ($value['parentId'] == $parentId) {
                        if ($highestLevelCount == 1) {
                            $value['selected'] = true;
                        }
                        $value['level'] = $level;
                        $highestChild[] = $value;
                    }
                }
            } else {
                $level = $level - 1;
                foreach ($parents['values'][$level] as $value) {
                    if($value['selected'] == true) {
                        $parentId = $value['parentId'];
                        $value['level'] = $level;
                        $highestChild[] = $value;
                    }
                }

                foreach ($parents['values'][$level] as $value) {
                    if($parentId == $value['parentId'] && $value['selected'] == false) {
                        $value['level'] = $level;
                        $highestChild[] = $value;
                    }
                }
            }

            for ($i = $level - 1; $i >= 2; $i--) {
                $parents['values'][$i][$parentId]['selected'] = true;
                $parents['values'][$i][$parentId]['level'] = $i;
                $results[] = $parents['values'][$i][$parentId];
                $parentId = $parents['values'][$i][$parentId]['parentId'];
            }
            $results = array_reverse($results);


        }
        $results = array_merge($results, $highestChild);
        $this->setMaxLevel($filter, $level);
        return $results;
    }

    private function returnHierarchy($filter)
    {
        $whatToDisplay = array('level' => 2, 'parentId' => '');
        $parents = array();
        $values = $this->_allFilters[$filter];

        $amount = count($values);
        for ($i = 0; $i < $amount; $i++) {
            $parentLevel = count($values[$i]['hierarchy']);
            for ($j = $i + 1; $j < $amount; $j++) {
                if ($parentLevel < count($values[$j]['hierarchy'])) {
                    $level = count($values[$j]['hierarchy']);
                    $childId = $values[$j]['hierarchyId'];
                    $parents[$level][$childId] = array(
                        'stringValue' => end($values[$j]['hierarchy']),
                        'hitCount' => $values[$j]['hitCount'],
                        'parentId' => $values[$i]['hierarchyId'],
                        'url' => $this->getFilterUrl($filter, $values[$j]['stringValue'], $values[$j]['selected'], false, 0),
                        'selected' => $values[$j]['selected']
                    );
                    if ($values[$j]['selected'] === true) {
                        $whatToDisplay = array('level' => $level + 1, 'parentId' => $values[$j]['hierarchyId']);
                    }
                    continue;
                }
                if (count($values[$i]['hierarchy']) == count($values[$j]['hierarchy'])) {
                    break;
                }
            }
        }
        return array('values' => $parents, 'display' => $whatToDisplay);
    }

    private function getFilterUrl($name, $value, $selected, $ranged = false, $position = 0, $hierarchical = null)
    {
        $multioption = Mage::getStoreConfig('Boxalino_General/filter/left_filters_multioption');
        $currentUrl = Mage::helper('core/url')->getCurrentUrl();
        if (!$ranged) {
            if ($multioption == true && $hierarchical == null) {
                if ($selected === false) {
                    $url = $currentUrl . '&bx_' . $name . '[' . $position . ']' . '=' . urlencode($value);
                } else {
                    $url = str_replace('&bx_' . $name . '[' . $position . ']' . '=' . urlencode($value), '', $currentUrl);
                }
            } else {
                $position = 0;
                if ($selected === false) {
                    if (isset($_REQUEST['bx_' . $name])) {
                        foreach ($_REQUEST['bx_' . $name] as $val) {
                            $currentUrl = str_replace('&bx_' . $name . '[' . $position . ']' . '=' . urlencode($val), '', $currentUrl);
                        }
                    }
                    $url = $currentUrl . '&bx_' . $name . '[' . $position . ']' . '=' . urlencode($value);
                } else {
                    $url = str_replace('&bx_' . $name . '[' . $position . ']' . '=' . urlencode($value), '', $currentUrl);
                }
            }
        } else {
            if ($selected === false) {
                $url = $currentUrl . '&bx_' . $name . '[' . $position . ']' . '=' . $value['from'] . '-' . $value['to'];
            } else {
                $url = str_replace('&bx_' . $name . '[' . $position . ']' . '=' . $value['from'] . '-' . $value['to'], '', $currentUrl);
            }
        }
        return $url;
    }

    private function returnImportantValues($values, $option, $filter, $position)
    {
        $data = array();
        if ($option == 'ranged') {
            $data['stringValue'] = array('min' => $values['rangeFromInclusive'], 'max' => $values['rangeToExclusive']);
            $data['url'] = $this->getFilterUrl($filter, array('from' => $values['rangeFromInclusive'], 'to' => $values['rangeToExclusive']), $values['selected'], true, $position);
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
        if (isset($_REQUEST['bx_' . $filter])) {
            foreach ($vals as $val) {
                $key = array_search($val, $_REQUEST['bx_' . $filter]);
                if ($key !== false) {
                    $url = str_replace('&bx_' . $filter . '[' . $key . ']=' . $vals[$key], '', $url);
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

    private function setMaxLevel($filter, $level)
    {
        $this->maxLevel[$filter] = $level;
    }

    public function getMaxLevel($filter)
    {
        if(isset($this->maxLevel[$filter])) {
            return $this->maxLevel[$filter];
        }
        return 0;
    }
}