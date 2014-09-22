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
    private $choiceResponse = null;
    private $returnFields = null;
    private $inquiry = null;
    private $searchQuery = null;
    private $filters = array();
    const VISITOR_COOKIE_TIME = 31536000;

    public function __construct(Boxalino_CemSearch_Helper_P13n_Config $config)
    {
        $this->config = $config;
        $this->p13n = new HttpP13n();
        $this->configureP13n();
        $this->createChoiceRequest();
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
//            $categoryDepth = count($categoryNames) - 1;

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
//                $this->addFilterHierarchy('categories', $categoryId, $category->getName());
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

        $autocompleteQuery = new \com\boxalino\p13n\api\thrift\AutocompleteQuery();
        $autocompleteQuery->indexId = $this->config->getAccount();
        $autocompleteQuery->language = substr(Mage::app()->getLocale()->getLocaleCode(), 0, 2);
        $autocompleteQuery->queryText = $text;
        $autocompleteQuery->suggestionsHitCount = $limit;

        $this->autocompleteRequest->choiceId = $choiceId;
        $this->autocompleteRequest->autocompleteQuery = $autocompleteQuery;
        $this->autocompleteRequest->searchChoiceId = $choiceId;
        $this->autocompleteRequest->searchQuery = $searchQuery;
        $this->autocompleteResponse = $this->p13n->autocomplete($this->autocompleteRequest);

    }

    public function getAutocompleteEntities()
    {
        $suggestions = array();

        foreach ($this->autocompleteResponse->hits as $hit) {
            $suggestions[] = array('text' => $hit->suggestion, 'hits' => $hit->searchResult->totalHitCount);
        }
        return $suggestions;
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
        $this->inquiry->simpleSearchQuery = $this->searchQuery;
        $this->choiceRequest->inquiries = array($this->inquiry);
        $this->choiceResponse = $this->p13n->choose($this->choiceRequest);
    }

    public function getEntitiesIds()
    {
        $result = array();
        foreach ($this->choiceResponse->variants as $variant) {
            /** @var \com\boxalino\p13n\api\thrift\SearchResult $searchResult */
            $searchResult = $variant->searchResult;
            foreach ($searchResult->hits as $item) {
                $result[] = $item->values[Mage::getStoreConfig('Boxalino_General/search/entity_id')][0];
            }
        }
        return $result;
    }

    public function getEntities()
    {
        $result = array();
        foreach ($this->choiceResponse->variants as $variant) {
            /** @var \com\boxalino\p13n\api\thrift\SearchResult $searchResult */
            $searchResult = $variant->searchResult;
            foreach ($searchResult->hits as $item) {
                $result[] = $item->values;
            }
        }
        return $result;
    }

    public function printData()
    {
        $results = array();
        /** @var \com\boxalino\p13n\api\thrift\Variant $variant */
        foreach ($this->choiceResponse->variants as $variant) {
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

}