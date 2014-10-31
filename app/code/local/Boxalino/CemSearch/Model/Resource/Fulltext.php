<?php
require_once "Mage/CatalogSearch/Model/Resource/Fulltext.php";

class Boxalino_CemSearch_Model_Resource_Fulltext extends Mage_CatalogSearch_Model_Resource_Fulltext
{

    public function prepareResult($object, $queryText, $query)
    {

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

        if (isset($_GET['cat'])) {
            $p13n->addFilterCategory($_GET['cat']);
        }
        $p13n->search();
        $entity_ids = $p13n->getEntitiesIds();
        unset($p13n); // !!!!!

        $adapter = $this->_getWriteAdapter();

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