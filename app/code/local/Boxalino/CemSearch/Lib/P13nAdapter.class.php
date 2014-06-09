<?php
	/**
	 * User: Michal Sordyl
	 * Mail: michal.sordyl@codete.co
	 * Date: 28.05.14
	 */

	require_once __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'Thrift' . DIRECTORY_SEPARATOR . 'HttpP13n.php';

	class P13nAdapter{
		private $config = null;
		private $p13n = null;
		private $choiceRequest = null;
		private $choiceResponse = null;
		private $returnFields = null;

		public function __construct(P13nConfig $config){
			$this->config = $config;
			$this->p13n = new HttpP13n();
			$this->configureP13n();
			$this->createChoiseRequest();
		}

		public function __destruct(){
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
		public function setupInquiry($choiceId, $search, $language, $returnFields, $sort, $offset = 0, $hitCount = 10){
			$inquiry = $this->createInquiry();
			$this->returnFields = $returnFields;
			$searchQuery = $this->createAndSetUpSearchQuery($search, $language, $returnFields, $offset, $hitCount);
			$searchQuery = $this->setUpSorting($searchQuery, $sort);

			$inquiry->choiceId = $choiceId;
			$inquiry->simpleSearchQuery = $searchQuery;

			$this->choiceRequest->inquiries = array($inquiry);
		}

		public function search(){
			$this->choiceResponse = $this->p13n->choose($this->choiceRequest);
		}

		public function getEntitiesIds(){
			$result = array();
			foreach($this->choiceResponse->variants as $variant){
				/** @var \com\boxalino\p13n\api\thrift\SearchResult $searchResult */
				$searchResult = $variant->searchResult;
				foreach($searchResult->hits as $item){
					$result[] = $item->values['entity_id'][0];
				}
			}
			return $result;
		}

		public function getEntities(){
			$result = array();
			foreach($this->choiceResponse->variants as $variant){
				/** @var \com\boxalino\p13n\api\thrift\SearchResult $searchResult */
				$searchResult = $variant->searchResult;
				foreach($searchResult->hits as $item){
					$result[] = $item->values;
				}
			}
			return $result;
		}

		public function printData(){
			$results = array();
			/** @var \com\boxalino\p13n\api\thrift\Variant $variant */
			foreach($this->choiceResponse->variants as $variant){
				/** @var \com\boxalino\p13n\api\thrift\SearchResult $searchResult */
				$searchResult = $variant->searchResult;
				foreach($searchResult->hits as $item){
					$result = array();
					foreach($item->values as $key => $value){
						if(is_array($value) && count($value) == 1){
							$result[$key] = array_shift($value);
						}else{
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

			foreach($this->returnFields as $field){
				echo '<td>' . $field . '</td>';
			}
			echo '</tr>';

			foreach($results as $result){
				echo '<tr>';
				foreach($this->returnFields as $field){
					echo '<td>' . $result[$field] . '</td>';
				}
				echo '</tr>';
			}
			echo '</table>';

		}

		private function createAndSetUpSearchQuery($search, $language, $returnFields, $offset, $hitCount){
			$searchQuery = new \com\boxalino\p13n\api\thrift\SimpleSearchQuery();
			$searchQuery->queryText = $search;
			$searchQuery->indexId = $this->config->getIndexId();
			$searchQuery->language = $language;
			$searchQuery->returnFields = $returnFields;
			$searchQuery->offset = $offset;
			$searchQuery->hitCount = $hitCount;

			return $searchQuery;
		}

		private function setUpSorting($searchQuery, P13nSort $sorting){
			$sortFieldsArray = $sorting->getSorts();
			$sortFields = array();
			foreach($sortFieldsArray as $sortField){
				$sortFields[] = new \com\boxalino\p13n\api\thrift\SortField(array(
					'fieldName' => $sortField['fieldName'],
					'reverse' => $sortField['reverse']
				));
			}
			if(!empty($sortFields)){
				$searchQuery->sortFields = $sortFields;
			}
			return $searchQuery;
		}

		private function createInquiry(){
			$inquiry = new \com\boxalino\p13n\api\thrift\ChoiceInquiry();
			return $inquiry;
		}

		private function configureP13n(){
			$this->p13n->setHost($this->config->getHost());
			$this->p13n->setAuthorization($this->config->getUsername(), $this->config->getPassword());
		}

		private function createChoiseRequest(){
			$this->choiceRequest = $this->p13n->getChoiceRequest($this->config->getAccount(), $this->config->getDomain());
		}

	}