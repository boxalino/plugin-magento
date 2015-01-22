<?php
require_once "Mage/CatalogSearch/Model/Resource/Fulltext.php";

class Boxalino_CemSearch_Model_Resource_Fulltext extends Mage_CatalogSearch_Model_Resource_Fulltext
{

    public function prepareResult($object, $queryText, $query)
    {

        $session = Mage::getSingleton("core/session");
        $session->unsetData('relax_products');
        $session->unsetData('relax_products_extra');

        if (Mage::getStoreConfig('Boxalino_General/general/enabled', 0) == 0) {
            return parent::prepareResult($object, $queryText, $query);
        }

        $storeConfig = Mage::getStoreConfig('Boxalino_General/general');

        $p13nConfig = new Boxalino_CemSearch_Helper_P13n_Config(
            $storeConfig['host'],
            Mage::helper('Boxalino_CemSearch')->getAccount(),
            $storeConfig['p13n_username'],
            $storeConfig['p13n_password'],
            $storeConfig['domain']
        );
        $p13nSort = new Boxalino_CemSearch_Helper_P13n_Sort();
        $p13nSort->push('score', true);   // score / discountedPrice / title_en
        $p13n = new Boxalino_CemSearch_Helper_P13n_Adapter($p13nConfig);

        $generalConfig = Mage::getStoreConfig('Boxalino_General/search');
        $lang = substr(Mage::app()->getLocale()->getLocaleCode(), 0, 2);

        $limit = $generalConfig['quick_search_limit'] == 0 ? 1000 : $generalConfig['quick_search_limit'];

        $p13n->setupInquiry(
            $generalConfig['quick_search'],
            $query->getQueryText(),
            $lang,
            array($generalConfig['entity_id'], 'categories'),
            $p13nSort,
            0, $limit
        );

        $p13n->setWithRelaxation(true);

        if (isset($_GET['cat'])) {
            $p13n->addFilterCategory($_GET['cat']);
        }
        $p13n->search();
        $entity_ids = $p13n->getEntitiesIds();
        $p13n->prepareAdditionalDataFromP13n();

        //prepare suggestion
        $relaxations = array();
        $searchRelaxation = $p13n->getChoiceRelaxation();
        $suggestionConfig = Mage::getStoreConfig('Boxalino_General/search_suggestions');

        if($suggestionConfig['enabled'] && (count($entity_ids) <= $suggestionConfig['min'] || count($entity_ids) >= $suggestionConfig['max']) ) {

            foreach ($searchRelaxation->suggestionsResults as $suggestion) {

                $relaxations[] = array(
                    'hits' => $suggestion->totalHitCount,
                    'text' => $suggestion->queryText,
                    'href' => urlencode($suggestion->queryText)
                );

            }

            if($suggestionConfig['sort']) {
                function cmp($a, $b)
                {
                    if ($a['hits'] == $b['hits']) {
                        return 0;
                    }
                    return ($a['hits'] > $b['hits']) ? -1 : 1;
                }

                usort($relaxations, "cmp");
            }

        }

//        self::$relaxations = $relaxations;
        $session->setData("relax", $relaxations);

        $adapter = $this->_getWriteAdapter();

        $this->resetSearchResults($query);

//        if($entity_ids === null || count($entity_ids) < 1){
//            return $this;
//        }

        //relaxation
        // change, 3 as param from configuration
        $relaxations_extra = array();
        $relaxationConfig = Mage::getStoreConfig('Boxalino_General/search_relaxation');

        if(($entity_ids === null || count($entity_ids) <= 3) && count($relaxations) > 0 && $relaxationConfig['enabled']){


            //display currently products
            $session = Mage::getSingleton("core/session");
            $session->setData("relax_products", $entity_ids);

            for($i=0; $i<$relaxationConfig['relaxations'];$i++) {
                //prepare new search
                $p13n->setupInquiry(
                    $generalConfig['quick_search'],
                    $relaxations[$i]['text'],
                    $lang,
                    array($generalConfig['entity_id'], 'categories'),
                    $p13nSort,
                    0, $limit
                );

                $p13n->setWithRelaxation(false);

                if (isset($_GET['cat'])) {
                    $p13n->addFilterCategory($_GET['cat']);
                }
                $p13n->search();
                $entity_ids = $p13n->getEntitiesIds();
                $p13n->prepareAdditionalDataFromP13n();

                $relaxations_extra[$relaxations[$i]['text']] = $entity_ids;

            }

            //display currently products
            $session->setData("relax_products_extra", $relaxations_extra);

        }

        unset($p13n); // !!!!!
        $this->resetSearchResults($query);

        if($entity_ids === null || count($entity_ids) < 1){
            return $this;
        }

        if (!$query->getIsProcessed() || true) {

            $searchType = $object->getSearchType($query->getStoreId());

            $preparedTerms = Mage::getResourceHelper('catalogsearch')
                ->prepareTerms($queryText, $query->getMaxQueryWords());

            $bind = array();
            $like = array();
            $likeCond = '';
            if ($searchType == Mage_CatalogSearch_Model_Fulltext::SEARCH_TYPE_LIKE
                || $searchType == Mage_CatalogSearch_Model_Fulltext::SEARCH_TYPE_COMBINE
            ) {
                $helper = Mage::getResourceHelper('core');
                $words = Mage::helper('core/string')->splitWords($queryText, true, $query->getMaxQueryWords());
                foreach ($words as $word) {
                    $like[] = $helper->getCILike('s.data_index', $word, array('position' => 'any'));
                }
                if ($like) {
                    $likeCond = '(' . join(' OR ', $like) . ')';
                }
            }
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
                $where = Mage::getResourceHelper('catalogsearch')
                    ->chooseFulltext($this->getMainTable(), $mainTableAlias, $select);
            }

            if ($likeCond != '' && $searchType == Mage_CatalogSearch_Model_Fulltext::SEARCH_TYPE_COMBINE) {
                $where .= ($where ? ' OR ' : '') . $likeCond;
            } elseif ($likeCond != '' && $searchType == Mage_CatalogSearch_Model_Fulltext::SEARCH_TYPE_LIKE) {
                $select->columns(array('relevance' => new Zend_Db_Expr(0)));
                $where = $likeCond;
            }

            $where = '( `e`.`entity_id` IN (' . implode(',', $entity_ids) . ') )';
            if (count($entity_ids) > 0) {
                $select->where($where);
            }

            $sql = $adapter->insertFromSelect($select,
                $this->getTable('catalogsearch/result'),
                array(),
                Varien_Db_Adapter_Interface::INSERT_ON_DUPLICATE);
            $adapter->query($sql, $bind);
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
}