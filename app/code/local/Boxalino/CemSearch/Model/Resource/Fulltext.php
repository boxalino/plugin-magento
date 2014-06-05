<?php
	require_once "Mage/CatalogSearch/Model/Resource/Fulltext.php";

	class Boxalino_CemSearch_Model_Resource_Fulltext extends Mage_CatalogSearch_Model_Resource_Fulltext{

        public function resetSearchResults($query = null)
        {
            $adapter = $this->_getWriteAdapter();
            $adapter->update($this->getTable('catalogsearch/search_query'), array('is_processed' => 0));

            $adapter->delete($this->getTable('catalogsearch/result'), 'query_id=' . $query->getId());

            Mage::dispatchEvent('catalogsearch_reset_search_result');

            return $this;
        }

        public function prepareResult($object, $queryText, $query){

			echo 'your prepareResult has been rewrited !! <pre>';
			//die;


			//print_r($query);
			echo '</pre>';

			$adapter = $this->_getWriteAdapter();

            var_dump($query);
            $this->resetSearchResults($query);
			if (!$query->getIsProcessed() || true) {

				$searchType = $object->getSearchType($query->getStoreId());

				$preparedTerms = Mage::getResourceHelper('catalogsearch')
					->prepareTerms($queryText, $query->getMaxQueryWords());

				$bind = array();
				$like = array();
				$likeCond  = '';
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
					->where($mainTableAlias.'.store_id = ?', (int)$query->getStoreId());

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
					$select->columns(array('relevance'  => new Zend_Db_Expr(0)));
					$where = $likeCond;
				}


				$entity_ids = array(402, 403, 405, 404);
				//$entity_ids = array(9999999);

				$where  = '( `e`.`entity_id` IN ('.implode(',',$entity_ids).') )';
				if ( count($entity_ids) > 0) {
					echo 'ok';
					$select->where($where);
				}
				echo '<pre>';

//				print_r($select);
				echo '</pre>';

				$sql = $adapter->insertFromSelect($select,
					$this->getTable('catalogsearch/result'),
					array(),
					Varien_Db_Adapter_Interface::INSERT_ON_DUPLICATE);
				$adapter->query($sql, $bind);

				//$query->setIsProcessed(1);
			}

			return $this;

		}
	}