<?php

class Boxalino_CemSearch_Helper_P13n_Client
{
    protected $p13nServerHost = 'cdn.bx-cloud.com';
    protected $p13nServerPort = 443;
    protected $productIdFieldName;
    protected $account;
    protected $password;
    protected $language;
    protected $isDevelopment = false;
    protected $p13n;

    /**
     * @param string $account
     * @param array $authData
     * @param string $language
     * @param bool $isDevelopment
     * @param int $entityIdFieldName
     */
    public function __construct($account, $authData, $language, $entityIdFieldName, $isDevelopment = false)
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
        $choiceRequest = $this->createChoiceRequest();
        foreach($widgets as $widget) {
            $name = $widget['name'];
            $variantNames[] = $name;
            $minimumRecommendations = (float) $widget['min_recs'];
            $maximumRecommendations = (float) $widget['max_recs'];
            if ($maximumRecommendations === null) {
                $maximumRecommendations = 5;
            }

            $inquiry = $this->createChoiceInquiry($name);

            $searchQuery = $this->createSearchQuery($returnFields);
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
     * @return \com\boxalino\p13n\api\thrift\ChoiceRequest
     */
    protected function createChoiceRequest()
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
    protected function createChoiceInquiry($name, $scope = null)
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
    protected function createSearchQuery(array $returnFields, $query = null)
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
