<?php

class BoxalinoP13nClient
{
	protected $p13nServerHost = 'bd1.bx-cloud.com';
//	protected $p13nServerHost = 'di1.bx-cloud.com';
//	protected $p13nServerHost = 'c1.bx-cloud.com';
//	protected $p13nServerHost = 'cdn.bx-cloud.com';
	protected $p13nServerPort = 80;
	protected $productIdFieldName;

	protected $account;
	protected $language;
	protected $isDevelopment = false;
	protected $p13n;

	/**
	 * @param string $account
	 * @param string $language
	 * @param bool $isDevelopment
	 */
	public function __construct($account, $language, $entityIdFieldName, $isDevelopment = false)
	{

        $this->productIdFieldName = $entityIdFieldName;

		$this->account = $account;
		$this->language = $language;
		$this->isDevelopment = $isDevelopment;
		// Created here first to load necessary files
		$this->p13n = $this->createP13n();
	}

	/**
	 * @param string $name
	 * @param array $returnFields
	 * @param int|null $minimumRecommendations
	 * @param int|null $maximumRecommendations
	 * @param string|null $scenario
	 * @return array
	 */
	public function getPersonalRecommendations($name, array $returnFields, $minimumRecommendations = null, $maximumRecommendations = null, $scenario = null)
	{
		if ($maximumRecommendations === null) {
			$maximumRecommendations = 5;
		}

		$choiceRequest = $this->createChoiceRequest();

		$inquiry = $this->createChoiceInquiry($name);

		$searchQuery = $this->createSearchQuery($returnFields);
		$searchQuery->offset = 0;
		$searchQuery->hitCount = $maximumRecommendations;

		$inquiry->simpleSearchQuery = $searchQuery;
		$inquiry->minHitCount = $minimumRecommendations;

//        if (false) {
		if ($scenario === 'basket' && Utils::requestExists('basketContent')) {
			$basketContent = json_decode(Utils::requestString('basketContent', '[]'), true);
			if ($basketContent !== false && count($basketContent)) {
				$contextItems = array();

				// Sort basket content by price
				usort($basketContent, function($a, $b) {
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
		} elseif ($scenario === 'product' && Utils::requestExists('productId')) {
			$productId = Utils::requestString('productId');
			$contextItem = new \com\boxalino\p13n\api\thrift\ContextItem();
			$contextItem->indexId = $this->account;
			$contextItem->fieldName = $this->productIdFieldName;
			$contextItem->contextItemId = $productId;
			$contextItem->role = 'mainProduct';
			$inquiry->contextItems = array($contextItem);
		} elseif ($scenario === 'category' && Utils::requestExists('categoryId')) {
            $categoryId = Utils::requestString('categoryId');
            $choiceRequest->requestContext->parameters['category_id'] = array($categoryId);
        }

		$choiceRequest->inquiries = array($inquiry);
//        var_dump($choiceRequest);
//        echo 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx';
//        echo '<pre>';
//        print_r($choiceRequest);
//        echo '</pre>';
//        die();
		$choiceResponse = $this->p13n->choose($choiceRequest);

		$results = array();
		/** @var \com\boxalino\p13n\api\thrift\Variant $variant */
		foreach ($choiceResponse->variants as $variant) {
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

				$result['_widgetTitle'] = $variant->searchResultTitle;
				$result['_rule'] = $name.':'.$variant->scenarioId.':'.$variant->variantId;
				$result['_choice'] = $name;
				$result['_scenario'] = $variant->scenarioId;
				$result['_variant'] = $variant->variantId;
				$results[] = $result;
			}
		}
//        var_dump($results);
		return $results;
	}

	/**
	 * @param string $query Choice query
	 * @param array $filters Array of choice filters, defined in form of:<br />
	 * filterName => array('normal' => array of words, 'range' array of ranges, 'hierarchical' => array of hierarchies)
	 */
	public function echoChoice($query, $filters = array())
	{
		// Using predefined search choice
		$name = 'quick_search';

		$choiceRequest = $this->createChoiceRequest();

		$inquiry = $this->createChoiceInquiry($name);

		$searchQuery = $this->createSearchQuery(array('id'), $query);
		$searchQuery->offset = 0;
		$searchQuery->hitCount = 1;

		// Filters
		$queryFilters = array();
        foreach ($filters as $filterName => $filterValues) {
			if (!is_array($filterValues) || empty($filterValues)) {
				continue;
			}
			if (!empty($filterValues['normal'])) {
				$queryFilter = new \com\boxalino\p13n\api\thrift\Filter();
				$queryFilter->fieldName = $filterName;
				$queryFilter->stringValues = $filterValues['normal'];
				$queryFilters[] = $queryFilter;
			}
			if (!empty($filterValues['hierarchical'])) {
				foreach ($filterValues['hierarchical'] as $hierarchicalValues) {
					if (!is_array($hierarchicalValues) || empty($hierarchicalValues)) {
						continue;
					}
					$queryFilter = new \com\boxalino\p13n\api\thrift\Filter();
					$queryFilter->fieldName = $filterName;
					$queryFilter->hierarchy = $hierarchicalValues;
					$queryFilters[] = $queryFilter;
				}
			}
			if (!empty($filterValues['range'])) {
				foreach ($filterValues['range'] as $rangeValues) {
					if (!isset($rangeValues[0]) || !isset($rangeValues[1])) {
						continue;
					}
					$queryFilter = new \com\boxalino\p13n\api\thrift\Filter();
					$queryFilter->fieldName = $filterName;
					if ($rangeValues[0] !== null && $rangeValues[0] !== '*') {
						$queryFilter->rangeFrom = $rangeValues[0];
						$queryFilter->rangeFromInclusive = true;
					}
					if ($rangeValues[1] !== null && $rangeValues[1] !== '*') {
						$queryFilter->rangeTo = $rangeValues[1];
						$queryFilter->rangeToInclusive = true;
					}
					$queryFilters[] = $queryFilter;
				}
			}
		}
		$searchQuery->filters = $queryFilters;

		$inquiry->simpleSearchQuery = $searchQuery;
		$inquiry->minHitCount = 0;

        $contextItems = array();
        $contextItem = new \com\boxalino\p13n\api\thrift\ContextItem();
        $contextItem->indexId = $this->account;
        $contextItem->fieldName = 'searchQuery';
        $contextItem->contextItemId = $query;
        $contextItem->role = 'product';

        $contextItems[] = $contextItem;
        $inquiry->contextItems = $contextItems;

		$choiceRequest->inquiries = array($inquiry);
        return $this->p13n->choose($choiceRequest);
	}

	/**
	 * @return P13n
	 */
	protected function createP13n()
	{
		$p13n = new P13n();
		return $p13n;
	}

	/**
	 * @return string
	 */
	protected function getBigDataHost()
	{
		$hostname = gethostname();
		if (preg_match('#^c[0-9]+n([0-9]+)$#', $hostname, $match)) {
			return 'bd'.$match[1].'.bx-cloud.com';
		}
		return $this->p13nServerHost;
	}

	/**
	 * @return string
	 */
	protected function getVisitorId()
	{
		$profileId = null;
		if (!empty($_COOKIE['cemv'])) {
			$cemv = explode('|', $_COOKIE['cemv']);
			foreach ($cemv as $values) {
				$kvPair = explode('=', $values, 2);
				if (!empty($kvPair[0]) && !empty($kvPair[1]) && $kvPair[0] === 'r') {
					$profileId = $kvPair[1];
					break;
				}
			}
		}
		return $profileId;
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
}
