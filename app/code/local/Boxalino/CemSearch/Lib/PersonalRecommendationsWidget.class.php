<?php

class PersonalRecommendationsWidget extends CemWidget
{
	/**
	 * @param int|null $minimumRecommendations
	 * @param int|null $maximumRecommendations
	 * @param string|null $scenario
	 * @return array
	 */
	public function getPersonalRecommendations($minimumRecommendations = null, $maximumRecommendations = null, $scenario = null)
	{
		$name = $this->widgetName;
		$account = $this->getAccount();
		$language = $this->language->id;
		$returnFields = array(
			'id',
			'entity_id',
			'title',
			'_image_small',
			'_url',
			'_url_basket',
			'standardPrice',
			'discountedPrice',
			'sku',
		);

		$p13nClient = new VacP13nClient($account, $language, $this->controller->development);
		return $p13nClient->getPersonalRecommendations($name, $returnFields, $minimumRecommendations, $maximumRecommendations, $scenario);
	}

	/**
	 *
	 */
	public function echoChoice()
	{
		$account = $this->getAccount();
		$language = $this->language->id;

		$activeQuery = $this->activeQueryDetails();
		if (isset($activeQuery['query'])) {
			$query = $activeQuery['query'];
		} else {
			$query = '';
		}

		$filters = array();
		$activeFilters = $this->activeFilters();
		if (!empty($activeFilters)) {
			foreach ($activeFilters as $filterName => $filterValues) {
				if (!empty($filterValues)) {
					foreach ($filterValues as $filterValue) {
						if (!isset($filters[$filterName])) {
							$filters[$filterName] = array(
								'normal' => array(),
								'hierarchical' => array(),
								'range' => array(),
							);
						}
						if (isset($filterValue['guidance']->mode) && $filterValue['guidance']->mode === 'range') {
							$filters[$filterName]['range'][] = $filterValue['guidance']->data;
						} elseif (isset($filterValue['guidance']->mode) && $filterValue['guidance']->mode === 'hierarchical') {
							$filters[$filterName]['hierarchical'][] = $filterValue['guidance']->data;
						} else {
							$filters[$filterName]['normal'][] = $filterValue['value'];
						}
					}
				}
			}
		}

		$p13nClient = new VacP13nClient($account, $language, $this->controller->development);
		$p13nClient->echoChoice($query, $filters);
	}

	/**
	 * @return string
	 */
	protected function getAccount()
	{
		/*if ($this->controller->development) {
			return $this->configuration->cemCustomerDev;
		}*/
		return $this->configuration->cemCustomer;
	}
}
