<?php

require_once 'Mage/CatalogSearch/Model/Resource/Fulltext.php';

class Boxalino_CemSearch_Model_Resource_Fulltext extends Mage_CatalogSearch_Model_Resource_Fulltext
{

    public function prepareResult($object, $queryText, $query)
    {

        $session = Mage::getSingleton('core/session');
        $session->unsetData('relax');
        $session->unsetData('relax_products');
        $session->unsetData('relax_products_extra');

        if (Mage::getStoreConfig('Boxalino_General/general/enabled') == 0) {
            return parent::prepareResult($object, $queryText, $query);
        }

        if($queryText == ''){
            return $this;
        }

        $searchAdapter = Mage::helper('Boxalino_CemSearch')->getSearchAdapter();
        $entity_ids = $searchAdapter->getEntitiesIds();

        //prepare suggestion
        $relaxations = array();
        $searchRelaxation = $searchAdapter->getChoiceRelaxation();
        $suggestionConfig = Mage::getStoreConfig('Boxalino_General/search_suggestions');

        if (
            $suggestionConfig['enabled'] &&
            is_object($searchRelaxation) &&
            is_array($searchRelaxation->suggestionsResults) &&
            count($searchRelaxation->suggestionsResults) > 0 &&
            (
                count($entity_ids) <= $suggestionConfig['min'] ||
                count($entity_ids) >= $suggestionConfig['max']
            )
        ) {
            Boxalino_CemSearch_Model_Logger::saveFrontActions('prepareResult', 'suggestions detected');

            foreach ($searchRelaxation->suggestionsResults as $suggestion) {
                $relaxations[] = array(
                    'hits' => $suggestion->totalHitCount,
                    'text' => $suggestion->queryText,
                    'href' => urlencode($suggestion->queryText)
                );
            }

            if ($suggestionConfig['sort']) {
                usort($relaxations, array($this, 'cmp'));
            }
        }

        $session->setData('relax', array_slice($relaxations, 0, $suggestionConfig['display']));
        Boxalino_CemSearch_Model_Logger::saveFrontActions('prepareResult relax', $session->getData('relax'));


        $this->resetSearchResults($query);

        //relaxation
        $relaxations_extra = array();
        $relaxationConfig = Mage::getStoreConfig('Boxalino_General/search_relaxation');

        if (
            (
                $entity_ids === null ||
                count($entity_ids) <= $relaxationConfig['max']
            ) &&
            is_object($searchRelaxation) &&
            ( count($searchRelaxation->subphrasesResults) > 0) &&
            $relaxationConfig['enabled']
        ) {

            Boxalino_CemSearch_Model_Logger::saveFrontActions('prepareResult', 'relaxations detected');

            //display current products
            $session = Mage::getSingleton('core/session');
            $session->setData('relax_products', $entity_ids);

            if (count($searchRelaxation->subphrasesResults) > 0) {
                if (count($relaxations) == 0) {
                    $relaxations_extra = array();
                }

                foreach ($searchRelaxation->subphrasesResults as $subphrase) {

                    if (count($relaxations_extra) >= $relaxationConfig['relaxations']) {
                        continue;
                    }

                    $relaxations_extra[$subphrase->queryText] = array();
                    foreach ($subphrase->hits as $hit) {
                        $relaxations_extra[$subphrase->queryText][] = $hit->values['id'][0];
                        if (count($relaxations_extra[$subphrase->queryText]) >= $relaxationConfig['products']) {
                            break;
                        }
                    }

                }

            }

            //display currently products
            $session->setData('relax_products_extra', $relaxations_extra);
            Boxalino_CemSearch_Model_Logger::saveFrontActions('prepareResult relax_products_extra', $session->getData('relax_products_extra'));

            $this->resetSearchResults($query);

            return $this;

        } elseif (
            count($entity_ids) == 0 &&
            is_object($searchRelaxation) &&
            count($searchRelaxation->subphrasesResults) == 0 &&
            count($relaxations) > 0) {
            Boxalino_CemSearch_Model_Logger::saveFrontActions('prepareResult', 'no relaxations');

            $q = $relaxations[0];
            $this->resetSearchResults($query);

            /**
             * Magento EE works peculiarly.
             * Magento EE loads facets before execute search one more time.
             * Magento CE works correctly.
             */
            try {
                if (Mage::getEdition() != 'Community') {

                    $params = $_GET;
                    $params['q'] = $q['text'];
                    $paramString = http_build_query($params);

                    $currentUrl = urldecode(Mage::helper('core/url')->getCurrentUrl());
                    $currentUrl = substr($currentUrl, 0, strpos($currentUrl, '?'));

                    header('Location: ' . $currentUrl . '?' . $paramString);
                    exit();
                }
            }catch (Exception $e){

            }


            Mage::helper('Boxalino_CemSearch')->resetSearchAdapter();

            Mage::helper('catalogsearch')->setQueryText($q['text']);

            $searchAdapter = Mage::helper('Boxalino_CemSearch')->getSearchAdapter();
            $entity_ids = $searchAdapter->getEntitiesIds();

            $session->unsetData('relax');
            $session->unsetData('relax_products');
            $session->unsetData('relax_products_extra');

        }

        $this->resetSearchResults($query);

        if ($entity_ids === null || count($entity_ids) < 1) {
            return $this;
        }

        $adapter = $this->_getWriteAdapter();

        if (!$query->getIsProcessed() || true) {

            $searchType = $object->getSearchType($query->getStoreId());

            $preparedTerms = Mage::getResourceHelper('catalogsearch')
                ->prepareTerms($queryText, $query->getMaxQueryWords());

            $bind = array();
            $like = array();
            $mainTableAlias = 's';
            $fields = array(
                'query_id' => new Zend_Db_Expr($query->getId()),
                'product_id',
            );
            $select = $adapter->select()
                ->from(array($mainTableAlias => $this->getMainTable()), $fields)
                ->joinInner(array('e' => $this->getTable('catalog/product')),
                    'e.entity_id = s.product_id',
                    array())
                ->where($mainTableAlias . '.store_id = ?', (int)$query->getStoreId());

            if ($searchType == Mage_CatalogSearch_Model_Fulltext::SEARCH_TYPE_FULLTEXT
                || $searchType == Mage_CatalogSearch_Model_Fulltext::SEARCH_TYPE_COMBINE
            ) {
                $bind[':query'] = implode(' ', $preparedTerms[0]);
            }

            if (count($entity_ids) > 0) {
                $select->where('(e.entity_id IN (' . implode(',', $entity_ids) . '))');
            }

            // enforce boxalino ranking
            $select->order(new Zend_Db_Expr('FIELD(e.entity_id,' . implode(',', $entity_ids).')'));

            if (
                $searchType == Mage_CatalogSearch_Model_Fulltext::SEARCH_TYPE_LIKE ||
                $searchType == Mage_CatalogSearch_Model_Fulltext::SEARCH_TYPE_FULLTEXT ||
                $searchType == Mage_CatalogSearch_Model_Fulltext::SEARCH_TYPE_COMBINE
            ) {
                $innerSelect = (string) $select;
                $select = $adapter->select()
                    ->from(array(
                        'a' => new Zend_Db_Expr('(' . $innerSelect . ')')
                    ), array(
                        'query_id',
                        'product_id'
                    ))
                    ->join(array(
                        'b' => new Zend_Db_Expr('(SELECT @s:= 0)')
                    ), '', array(
                        'relevance' => new Zend_Db_Expr('@s:=@s+1')
                    ))
                    ->where('1=1'); // added to avoid collision with appended ON DUPLICATE
            }

            Boxalino_CemSearch_Model_Logger::saveFrontActions('Fulltext_PrepareResult', 'storing catalogsearch/result for entities with id: ' . implode(', ', $entity_ids));
            $sql = $adapter->insertFromSelect($select,
                $this->getTable('catalogsearch/result'),
                array(),
                Varien_Db_Adapter_Interface::INSERT_ON_DUPLICATE);

            $adapter->query($sql, $bind);

            $query->setIsProcessed(1);
        }

        return $this;

    }

    public function resetSearchResults($query = null)
    {
        $adapter = $this->_getWriteAdapter();
        $adapter->update($this->getTable('catalogsearch/search_query'), array('is_processed' => 0));

        if (!is_null($query)) {
            $adapter->delete($this->getTable('catalogsearch/result'), 'query_id=' . $query->getId());
        }
        Mage::dispatchEvent('catalogsearch_reset_search_result');

        return $this;
    }

    private function cmp($a, $b)
    {
        if ($a['hits'] == $b['hits']) {
            return 0;
        }
        return ($a['hits'] > $b['hits']) ? -1 : 1;
    }
}
