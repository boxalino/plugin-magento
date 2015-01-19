<?php
/**
 * User: Michal Sordyl
 * Mail: michal.sordyl@codete.co
 * Date: 28.05.14
 */

class Boxalino_CemSearch_Helper_P13n_Adapter
{
    private $config = null;
    private $p13n = null;
    private $autocompleteRequest = null;
    private $choiceRequest = null;
    private $autocompleteResponse = null;
    static private $choiceResponse = null;
    private $returnFields = null;
    private $inquiry = null;
    private $searchQuery = null;
    private $filters = array();
    private $selectedFacets = array();
    const VISITOR_COOKIE_TIME = 31536000;

    public function __construct(Boxalino_CemSearch_Helper_P13n_Config $config)
    {
        $this->config = $config;
        $this->p13n = new HttpP13n();
        $this->configureP13n();
        $this->createChoiceRequest();
    }

    private function getChoiceResponse()
    {
        if(empty(self::$choiceResponse)) {
            $this->search();
        }
        return self::$choiceResponse;
    }

    private function configureP13n()
    {
        $this->p13n->setHost($this->config->getHost());
        $this->p13n->setAuthorization($this->config->getUsername(), $this->config->getPassword());
    }

    private function createChoiceRequest()
    {
        $this->choiceRequest = $this->p13n->getChoiceRequest($this->config->getAccount(), $this->config->getDomain());
    }

    public function __destruct()
    {
        unset($this->p13n);
    }

    /**
     * @param String $choiceId can be found on admin page /Recommendations/Widgets
     * @param String test to search, eg 'shirt'
     * @param String $language 2 letter language code, eg 'en'
     * @param array $returnFields of field names, eg array('id', 'name')
     * @param P13nSort $sort array('fieldName' => , 'reverse' =>);
     * @param int $offset products to skip
     * @param int $hitCount how many records
     */
    public function setupInquiry($choiceId, $search, $language, $returnFields, $sort, $offset = 0, $hitCount = 10)
    {
        $this->inquiry = $this->createInquiry();
        $returnFields = array_merge($returnFields, Mage::helper('Boxalino_CemSearch')->getAdditionalFieldsFromP13n());
        $this->returnFields = $returnFields;
        $this->createAndSetUpSearchQuery($search, $language, $returnFields, $offset, $hitCount);
        $this->setUpSorting($sort);

        $this->inquiry->choiceId = $choiceId;

    }

    private function createInquiry()
    {
        $inquiry = new \com\boxalino\p13n\api\thrift\ChoiceInquiry();
        return $inquiry;
    }

    private function createAndSetUpSearchQuery($search, $language, $returnFields, $offset, $hitCount)
    {
        $this->searchQuery = new \com\boxalino\p13n\api\thrift\SimpleSearchQuery();
        $this->searchQuery->queryText = $search;
        $this->searchQuery->indexId = $this->config->getAccount();
        $this->searchQuery->language = $language;
        $this->searchQuery->returnFields = $returnFields;
        $this->searchQuery->offset = $offset;
        $this->searchQuery->hitCount = $hitCount;
        $this->searchQuery->facetRequests = $this->prepareFacets();

        Boxalino_CemSearch_Model_Logger::saveFrontActions('query', $search);
        Boxalino_CemSearch_Model_Logger::saveFrontActions('facets', $this->searchQuery->facetRequests);
    }

    private function setUpSorting(Boxalino_CemSearch_Helper_P13n_Sort $sorting)
    {
        $sortFieldsArray = $sorting->getSorts();
        $sortFields = array();
        foreach ($sortFieldsArray as $sortField) {
            $sortFields[] = new \com\boxalino\p13n\api\thrift\SortField(array(
                'fieldName' => $sortField['fieldName'],
                'reverse' => $sortField['reverse']
            ));
        }
        if (!empty($sortFields)) {
            $this->searchQuery->sortFields = $sortFields;
        }
    }

    /**
     * @param int $hierarchyId how deep is category tree in search, starts from 0 for main categories
     * @param array $category names of categories in hierarchy
     *
     * exaples:
     * $hierarchyId = 0;
     * $category = array('Men');
     * will search all products in category 'Men' (with subcategories)
     *
     * $hierarchyId = 1;
     * $category = array('Men', 'Blazers');
     * will search all products in category 'Men' (with subcategories)
     *
     */
    public function addFilterCategories($categoryId)
    {
        $categoryNames = array();
        if (isset($categoryId) && $categoryId > 0) {
            $category = Mage::getModel('catalog/category')->load($categoryId);
            $path = $category->getPath();
            $pathArray = explode('/', $path);
            $skip = -2;
            foreach ($pathArray as $catId) {
                $categoryName = Mage::getModel('catalog/category')->load($catId)->getName();

                if (++$skip > 0) {
                    $categoryNames[] = $categoryName;
                }
            }

            $this->addFilterHierarchy('categories', $categoryId, $categoryNames);

        }
    }

    /**
     * @param $categoryId
     */
    public function addFilterCategory($categoryId){

        if (isset($categoryId) && $categoryId > 0){
            $category = Mage::getModel('catalog/category')->load($categoryId);

            if($category != null){
                $filter = new \com\boxalino\p13n\api\thrift\Filter();

                $filter->fieldName = 'categories';

                $filter->hierarchyId = $categoryId;
                $filter->hierarchy = array($category->getName());

                $this->filters[] = $filter;
            }

        }

    }

    /**
     * @param string $field field name for filter
     * @param int $hierarchyId names of categories in hierarchy
     * @param int $hierarchy names of categories in hierarchy
     * @param string|null $lang
     *
     */
    public function addFilterHierarchy($field, $hierarchyId, $hierarchy, $lang = null)
    {
        $filter = new \com\boxalino\p13n\api\thrift\Filter();

        if ($lang) {
            $filter->fieldName = $field . '_' . substr(Mage::app()->getLocale()->getLocaleCode(), 0, 2);
        } else {
            $filter->fieldName = $field;
        }

        $filter->hierarchyId = $hierarchyId;
        $filter->hierarchy = $hierarchy;

        $this->filters[] = $filter;
    }

    /**
     * @param float $from
     * @param float $to
     */
    public function setupPrice($from, $to)
    {
        $this->filters[] = new \com\boxalino\p13n\api\thrift\Filter(array(
            'fieldName' => 'discountedPrice',
            'rangeFrom' => $from,
            'rangeTo' => $to
        ));
    }

    /**
     * @param string $field field name for filter
     * @param mixed $value filter value
     * @param string|null $lang
     *
     */
    public function addFilter($field, $value, $lang = null)
    {
        $filter = new \com\boxalino\p13n\api\thrift\Filter();

        if ($lang) {
            $filter->fieldName = $field . '_' . substr(Mage::app()->getLocale()->getLocaleCode(), 0, 2);
        } else {
            $filter->fieldName = $field;
        }

        if (is_array($value)) {
            $filter->stringValues = $value;
        } else {
            $filter->stringValues = array($value);
        }

        $this->filters[] = $filter;
    }

    /**
     * @param string $field field name for filter
     * @param number $from param from
     * @param number $to param from
     * @param string|null $lang
     *
     */
    public function addFilterFromTo($field, $from, $to, $lang = null)
    {
        $filter = new \com\boxalino\p13n\api\thrift\Filter();

        if ($lang) {
            $filter->fieldName = $field . '_' . substr(Mage::app()->getLocale()->getLocaleCode(), 0, 2);
        } else {
            $filter->fieldName = $field;
        }

        $filter->rangeFrom = $from;
        $filter->rangeTo = $to;

        $this->filters[] = $filter;
    }

    public function autocomplete($text, $limit, $products_limit = 0)
    {
        $choiceId = 'autocomplete';
        $fields = array(Mage::getStoreConfig('Boxalino_General/search/entity_id'), 'title', 'score');
        $this->autocompleteRequest = $this->getAutocompleteRequest($this->config->getAccount(), $this->config->getDomain());

        $searchQuery = new \com\boxalino\p13n\api\thrift\SimpleSearchQuery();
        $searchQuery->indexId = $this->config->getAccount();
        $searchQuery->language = substr(Mage::app()->getLocale()->getLocaleCode(), 0, 2);
        $searchQuery->returnFields = $fields;
        $searchQuery->offset = 0;
        $searchQuery->hitCount = $products_limit;
        $searchQuery->queryText = $text;

        if($this->filterByVisibleProducts()) {
            $searchQuery->filters[] = $this->filterByVisibleProducts();
        }
        if($this->filterByStatusProducts()) {
            $searchQuery->filters[] = $this->filterByStatusProducts();
        }
        $autocompleteQuery = new \com\boxalino\p13n\api\thrift\AutocompleteQuery();
        $autocompleteQuery->indexId = $this->config->getAccount();
        $autocompleteQuery->language = substr(Mage::app()->getLocale()->getLocaleCode(), 0, 2);
        $autocompleteQuery->queryText = $text;
        $autocompleteQuery->suggestionsHitCount = $limit;

        $this->autocompleteRequest->choiceId = $choiceId;
        $this->autocompleteRequest->autocompleteQuery = $autocompleteQuery;
        $this->autocompleteRequest->searchChoiceId = $choiceId;
        $this->autocompleteRequest->searchQuery = $searchQuery;

        Boxalino_CemSearch_Model_Logger::saveFrontActions('autocomplete_Query', $text);
        Boxalino_CemSearch_Model_Logger::saveFrontActions('autocomplete_Request', $this->autocompleteRequest);

        $this->autocompleteResponse = $this->p13n->autocomplete($this->autocompleteRequest);

        Boxalino_CemSearch_Model_Logger::saveFrontActions('autocomplete_Response', $this->autocompleteResponse, 1);

    }

    public function getAutocompleteEntities()
    {
        $suggestions = array();

        foreach ($this->autocompleteResponse->hits as $hit) {
            $suggestions[] = array('text' => $hit->suggestion, 'hits' => $hit->searchResult->totalHitCount);
        }
        return $suggestions;
    }

    private function filterByVisibleProducts()
    {
        $filter = new \com\boxalino\p13n\api\thrift\Filter();
        $filter->fieldName = 'products_visibility';
        $filter->negative = true;
        $filter->stringValues = array("not_visible_individually");
        return $filter;
    }

    private function filterByStatusProducts()
    {
        $filter = new \com\boxalino\p13n\api\thrift\Filter();
        $filter->fieldName = 'products_status';
        $filter->stringValues = array("enabled");
        return $filter;
    }

    public function getAutocompleteProducts()
    {
        $products = array();

        foreach ($this->autocompleteResponse->hits as $hit) {
            $id = substr(md5($hit->suggestion), 0, 10);
            $products[$id] = array();
            foreach ($hit->searchResult->hits as $productsHit) {
                $products[$id][] = array(
                    'id' => $productsHit->values[Mage::getStoreConfig('Boxalino_General/search/entity_id')][0],
                    'score' => $productsHit->values['score'][0],
                );
            }
        }
        return $products;
    }

    public function search()
    {
        if (!empty($this->filters)) {
            $this->searchQuery->filters = $this->filters;
        }
        if($this->filterByVisibleProducts()) {
            $this->searchQuery->filters[] = $this->filterByVisibleProducts();
        }
        if($this->filterByStatusProducts()) {
            $this->searchQuery->filters[] = $this->filterByStatusProducts();
        }
        $this->inquiry->simpleSearchQuery = $this->searchQuery;
        $this->choiceRequest->inquiries = array($this->inquiry);

        Boxalino_CemSearch_Model_Logger::saveFrontActions('choice_Request', $this->choiceRequest);

        self::$choiceResponse = $this->p13n->choose($this->choiceRequest);

        Boxalino_CemSearch_Model_Logger::saveFrontActions('choice_Response', self::$choiceResponse, 1);
    }

    private function prepareFacets()
    {
        $facets = array();
        $normalFilters = '';
        $topFilters = '';
        $enableLeftFilters = Mage::getStoreConfig('Boxalino_General/filter/left_filters_enable');
        $enableTopFilters = Mage::getStoreConfig('Boxalino_General/filter/top_filters_enable');

        if($enableLeftFilters == 1) {
            $normalFilters = explode(',',Mage::getStoreConfig('Boxalino_General/filter/left_filters_normal'));
        }
        if($enableTopFilters == 1) {
            $topFilters = explode(',', Mage::getStoreConfig('Boxalino_General/filter/top_filters'));
        }
        if(!empty($normalFilters)) {
            foreach ($normalFilters as $filterString) {
                $filter = explode(':', $filterString);
                if ($filter[0] != '') {
                    $facet = new \com\boxalino\p13n\api\thrift\FacetRequest();
                    $facet->fieldName = $filter[0];
                    $facet->numerical = $filter[1] == 'ranged' ? true : $filter[1] == 'numerical' ? true : false;
                    $facet->range = $filter[1] == 'ranged' ? true : false;
                    $facet->selectedValues = $this->facetSelectedValue($filter[0], $filter[1]);
                    $facets[] = $facet;
                }
            }
        }
        if($topFilters) {
            foreach ($topFilters as $filter) {
                if ($filter != '') {
                    $facet = new \com\boxalino\p13n\api\thrift\FacetRequest();
                    $facet->fieldName = $filter;
                    $facet->numerical = false;
                    $facet->range = false;
                    $facet->selectedValues = $this->facetSelectedValue($filter, 'standard');
                    $facets[] = $facet;
                }
            }
        }
        return $facets;
    }

    private function facetSelectedValue($name, $option)
    {
        if(empty($this->selectedFacets)) {
            foreach($_REQUEST as $key => $values) {
                if(strpos($key, 'bx_') !== false) {
                    $fieldName = substr($key, 3);
                    foreach($values as $value) {
                        $this->selectedFacets[$fieldName][] = $value;
                    }
                }
            }
        }
        $selectedFacets = array();
        if(isset($this->selectedFacets[$name])) {
            foreach ($this->selectedFacets[$name] as $value) {
                $selectedFacet = new \com\boxalino\p13n\api\thrift\FacetValue();
                if($option == 'ranged') {
                    $rangedValue = explode('-', $value);
                    if($rangedValue[0] != '*') {
                        $selectedFacet->rangeFromInclusive = $rangedValue[0];
                    }
                    if($rangedValue[1] != '*') {
                        $selectedFacet->rangeToExclusive = $rangedValue[1];
                    }
                } else {
                    $selectedFacet->stringValue = urldecode($value);
                }
                $selectedFacets[] = $selectedFacet;

            }
            return $selectedFacets;
        }
        return;
    }

    public function getEntitiesIds()
    {
        $result = array();
        $response = $this->getChoiceResponse();
        foreach ($response->variants as $variant) {
            /** @var \com\boxalino\p13n\api\thrift\SearchResult $searchResult */
            $searchResult = $variant->searchResult;
            foreach ($searchResult->hits as $item) {
                $result[] = $item->values[Mage::getStoreConfig('Boxalino_General/search/entity_id')][0];
            }
        }
        return $result;
    }

    public function prepareAdditionalDataFromP13n()
    {
        $result = array();
        $response = self::getChoiceResponse();
        $additionalFields = Mage::helper('Boxalino_CemSearch')->getAdditionalFieldsFromP13n();
        if(!empty($response->variants)) {
            foreach ($response->variants as $variant) {
                /** @var \com\boxalino\p13n\api\thrift\SearchResult $searchResult */
                $searchResult = $variant->searchResult;
                foreach ($searchResult->hits as $item) {
                    foreach($additionalFields as $field) {
                        if(isset($item->values[$field])) {
                            if(!empty($item->values[$field])) {
                                $result[$item->values['id'][0]][$field] = $item->values[$field];
                            }
                        }
                    }
                }
            }
        }
        Mage::getModel('core/session')->setData('boxalino_additional_data', $result);
    }

    public function getFacetsData()
    {
        $preparedFacets = array();
        $response = self::getChoiceResponse();
        foreach($response->variants as $variant) {
            $facets = $variant->searchResult->facetResponses;
            foreach($facets as $facet) {
                if(!empty($facet->values)) {
                    $filter[$facet->fieldName] = array();
                    foreach($facet->values as $value) {
                        $param['stringValue'] = $value->stringValue;
                        $param['hitCount'] = $value->hitCount;
                        $param['rangeFromInclusive'] = $value->rangeFromInclusive;
                        $param['rangeToExclusive'] = $value->rangeToExclusive;
                        $param['hierarchyId'] = $value->hierarchyId;
                        $param['hierarchy'] = $value->hierarchy;
                        $param['selected'] = $value->selected;
                        $filter[$facet->fieldName][] = $param;
                    }
                    $preparedFacets = $filter;
                }
            }
        }
        return $preparedFacets;
    }

    public function printData()
    {
        $results = array();
        /** @var \com\boxalino\p13n\api\thrift\Variant $variant */
        foreach (self::$choiceResponse->variants as $variant) {
            /** @var \com\boxalino\p13n\api\thrift\SearchResult $searchResult */
            $searchResult = $variant->searchResult;
            foreach ($searchResult->hits as $item) {
                $result = array();
                foreach ($item->values as $key => $value) {
                    if (is_array($value) && count($value) == 1) {
                        $result[$key] = array_shift($value);
                    } else {
                        $result[$key] = $value;
                    }
                }
                // Widget's meta data, mostly used for event tracking
                $result['_widgetTitle'] = $variant->searchResultTitle;
                $results[] = $result;
            }
        }

        echo '<table border="1">';
        echo '<tr>';

        foreach ($this->returnFields as $field) {
            echo '<td>' . $field . '</td>';
        }
        echo '</tr>';

        foreach ($results as $result) {
            echo '<tr>';
            foreach ($this->returnFields as $field) {
                echo '<td>' . $result[$field] . '</td>';
            }
            echo '</tr>';
        }
        echo '</table>';

    }

    /**
     * @param string $accountname
     * @param string $cookieDomain
     * @return \com\boxalino\p13n\api\thrift\AutocompleteRequest
     */
    private function getAutocompleteRequest($accountname, $cookieDomain = null)
    {
        $request = new \com\boxalino\p13n\api\thrift\AutocompleteRequest();

        // Setup information about account
        $userRecord = new \com\boxalino\p13n\api\thrift\UserRecord();
        $userRecord->username = $accountname;
        $request->userRecord = $userRecord;

        if (empty($_COOKIE['cems'])) {
            $sessionid = session_id();
            if (empty($sessionid)) {
                session_start();
                $sessionid = session_id();
            }
        } else {
            $sessionid = $_COOKIE['cems'];
        }

        if (empty($_COOKIE['cemv'])) {
            $profileid = '';
            if (function_exists('openssl_random_pseudo_bytes')) {
                $profileid = bin2hex(openssl_random_pseudo_bytes(16));
            }
            if (empty($profileid)) {
                $profileid = uniqid('', true);
            }
        } else {
            $profileid = $_COOKIE['cemv'];
        }
        $request->profileId = $profileid;

        // Refresh cookies
        if (empty($cookieDomain)) {
            setcookie('cems', $sessionid, 0);
            setcookie('cemv', $profileid, time() + self::VISITOR_COOKIE_TIME);
        } else {
            setcookie('cems', $sessionid, 0, '/', $cookieDomain);
            setcookie('cemv', $profileid, time() + 1800, '/', self::VISITOR_COOKIE_TIME);
        }

        return $request;
    }


    /*
     * Recommendations
     */
    protected $p13nServerHost = 'cdn.bx-cloud.com';
    protected $p13nServerPort = 443;
    protected $productIdFieldName;
    protected $account;
    protected $password;
    protected $language;
    protected $isDevelopment = false;
    /**
     * @param string $name
     * @param array $returnFields
     * @param int|null $minimumRecommendations
     * @param int|null $maximumRecommendations
     * @param string|null $scenario
     * @return array
     */
    public function getPersonalRecommendations(array $widgets, array $returnFields, $widgetType)
    {
        $variantNames = array();
        $choiceRequest = $this->createRecommendationChoiceRequest();
        foreach($widgets as $widget) {
            $name = $widget['name'];
            $variantNames[] = $name;
            $minimumRecommendations = (float) $widget['min_recs'];
            $maximumRecommendations = (float) $widget['max_recs'];
            if ($maximumRecommendations === null) {
                $maximumRecommendations = 5;
            }

            $inquiry = $this->createRecommendationChoiceInquiry($name);

            $searchQuery = $this->createRecommendationSearchQuery($returnFields);
            $searchQuery->offset = 0;
            $searchQuery->hitCount = $maximumRecommendations;

            $inquiry->simpleSearchQuery = $searchQuery;
            $inquiry->minHitCount = $minimumRecommendations;
            if ($widgetType === 'basket' && $_REQUEST['basketContent']) {
                $basketContent = json_decode($_REQUEST['basketContent'], true);
                if ($basketContent !== false && count($basketContent)) {
                    $contextItems = array();

                    // Sort basket content by price
                    usort($basketContent, function ($a, $b) {
                        if ($a['price'] > $b['price']) {
                            return -1;
                        } elseif ($b['price'] > $a['price']) {
                            return 1;
                        }
                        return 0;
                    });

                    $basketItem = array_shift($basketContent);

                    $contextItem = new \com\boxalino\p13n\api\thrift\ContextItem();
                    $contextItem->indexId = $this->account;
                    $contextItem->fieldName = $this->productIdFieldName;
                    $contextItem->contextItemId = $basketItem['id'];
                    $contextItem->role = 'mainProduct';

                    $contextItems[] = $contextItem;

                    foreach ($basketContent as $basketItem) {
                        $contextItem = new \com\boxalino\p13n\api\thrift\ContextItem();
                        $contextItem->indexId = $this->account;
                        $contextItem->fieldName = $this->productIdFieldName;
                        $contextItem->contextItemId = $basketItem['id'];
                        $contextItem->role = 'subProduct';

                        $contextItems[] = $contextItem;
                    }
                    $inquiry->contextItems = $contextItems;
                }
            } elseif ($widgetType === 'product' && !empty($_REQUEST['productId'])) {
                $productId = $_REQUEST['productId'];
                $contextItem = new \com\boxalino\p13n\api\thrift\ContextItem();
                $contextItem->indexId = $this->account;
                $contextItem->fieldName = $this->productIdFieldName;
                $contextItem->contextItemId = $productId;
                $contextItem->role = 'mainProduct';
                $inquiry->contextItems = array($contextItem);
            }
            if($this->filterByVisibleProducts()) {
                $inquiry->simpleSearchQuery->filters[] = $this->filterByVisibleProducts();
            }
            if($this->filterByStatusProducts()) {
                $inquiry->simpleSearchQuery->filters[] = $this->filterByStatusProducts();
            }
            $choiceRequest->inquiries[] = $inquiry;
        }
        $choiceResponse = $this->p13n->choose($choiceRequest);
        $results = array();
        /** @var \com\boxalino\p13n\api\thrift\Variant $variant */
        foreach ($choiceResponse->variants as $variantId => $variant) {
            $name = $variantNames[$variantId];
            foreach ($variant->searchResult->hits as $item) {
                $result = array();
                foreach ($item->values as $key => $value) {
                    if (is_array($value) && count($value) == 1) {
                        $result[$key] = array_shift($value);
                    } else {
                        $result[$key] = $value;
                    }
                }
                if (!isset($result['name']) && isset($result['title'])) {
                    $result['name'] = $result['title'];
                }

                $result['_rule'] = $name . ':' . $variant->scenarioId . ':' . $variant->variantId;
                $result['_choice'] = $name;
                $result['_scenario'] = $variant->scenarioId;
                $result['_variant'] = $variant->variantId;
                $results[$name][] = $result;
            }
        }
        return $results;
    }

    /**
     * @param string $account
     * @param array $authData
     * @param string $language
     * @param bool $isDevelopment
     * @param int $entityIdFieldName
     */
    public function createRecommendation($account, $authData, $language, $entityIdFieldName, $isDevelopment = false)
    {

        $this->productIdFieldName = $entityIdFieldName;

        $this->account = $account;
        $this->language = $language;
        $this->isDevelopment = $isDevelopment;
        // Created here first to load necessary files
        $this->p13n = $this->createP13n($authData);
    }

    /**
     * @return P13n
     */
    private function createP13n($authData)
    {
        $p13n = new HttpP13n();
        $p13n->setHost($this->p13nServerHost);
        $p13n->setAuthorization($authData['username'], $authData['password']);
        return $p13n;
    }

    /**
     * @return \com\boxalino\p13n\api\thrift\ChoiceRequest
     */
    protected function createRecommendationChoiceRequest()
    {
        $choiceRequest = new \com\boxalino\p13n\api\thrift\ChoiceRequest();
        $choiceRequest->profileId = $this->getVisitorId();

        $userRecord = new \com\boxalino\p13n\api\thrift\UserRecord();
        $userRecord->username = $this->account;

        $choiceRequest->userRecord = $userRecord;

        return $choiceRequest;
    }

    /**
     * @return string
     */
    protected function getVisitorId()
    {
        $profileid = null;
        if (empty($_COOKIE['cemv'])) {
            $profileid = '';
            if (function_exists('openssl_random_pseudo_bytes')) {
                $profileid = bin2hex(openssl_random_pseudo_bytes(16));
            }
            if (empty($profileid)) {
                $profileid = uniqid('', true);
            }
        } else {
            $profileid = $_COOKIE['cemv'];
        }

        return $profileid;
    }

    /**
     * @param string $name Choice name
     * @param string|null $scope Choice scope (null for default)
     * @return \com\boxalino\p13n\api\thrift\ChoiceInquiry
     */
    protected function createRecommendationChoiceInquiry($name, $scope = null)
    {
        $inquiry = new \com\boxalino\p13n\api\thrift\ChoiceInquiry();
        $inquiry->choiceId = $name;
        if ($scope !== null) {
            $inquiry->scope = $scope;
        }
        return $inquiry;
    }

    /**
     * @param array $returnFields
     * @param string|null $query
     * @return \com\boxalino\p13n\api\thrift\SimpleSearchQuery
     */
    protected function createRecommendationSearchQuery(array $returnFields, $query = null)
    {
        $searchQuery = new \com\boxalino\p13n\api\thrift\SimpleSearchQuery();
        $searchQuery->indexId = $this->account;
        if ($query !== null) {
            $searchQuery->queryText = $query;
        }
        $searchQuery->language = $this->language;
        $searchQuery->returnFields = $returnFields;

        return $searchQuery;
    }

    /**
     * @return string
     */
    protected function getBigDataHost()
    {
        $hostname = gethostname();
        if (preg_match('#^c[0-9]+n([0-9]+)$#', $hostname, $match)) {
            return 'bd' . $match[1] . '.bx-cloud.com';
        }
        return $this->p13nServerHost;
    }

}
