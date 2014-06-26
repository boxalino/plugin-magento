<?php

class Boxalino_CemExport_DataController extends Mage_Core_Controller_Front_Action {
	public function transactionsAction() {
		try {
			$this->getResponse()->setHeader('content-type', 'text/plain; charset=UTF-8', true);
			$this->getResponse()->sendHeaders();

			if ($this->getRequest()->getParam('accessCode') != Mage::getStoreConfig('boxalinocem/synchronization/access_code')) {
				return;
			}

			$pageOffset = max(1, intval($this->getRequest()->getParam('offset')));
			$pageSize = max(1, min(intval($this->getRequest()->getParam('size')), 10000));

			$collection = Mage::getModel('sales/order')->getCollection();
			//$collection->addAttributeToFilter('store_id', $store->getId());
			//$collection->addAttributeToSelect(array_keys($attributes->asArray()));
			$collection->setOrder('entity_id', 'asc');
			$collection->setPage($pageOffset, $pageSize);

			$f = fopen('php://output', 'a');
			if (fputcsv($f, array('id', 'time', 'user', 'store', 'product', 'sku', 'quantity', 'price'))) {
				foreach ($collection as $transaction) {
					foreach ($transaction->getItemsCollection() as $item) {
						$store = Mage::app()->getStore($item->store_id);
						$container = $store->getWebsite()->getCode().'_'.$store->getCode();

						$date = new Zend_Date($transaction->created_at, 'yyyy-MM-dd HH:mm:ss');
						$row = array();
						$row[] = $transaction->entity_id;
						//$row[] = $transaction->getRealOrderId();
						$row[] = $date->get('yyyy-MM-dd HH:mm:ss');
						$row[] = $transaction->customer_id;
						$row[] = $container;
						$row[] = $container.'_'.$item->product_id;
						$row[] = $item->sku;
						$row[] = $item->qty_ordered;
						$row[] = $item->price;
						fputcsv($f, $row);
					}
				}
			}
			fclose($f);
		} catch (Exception $e) {
			if (Mage::getStoreConfig('boxalinocem/frontend/debug') == 1) {
				echo($e);
			}
		}
	}

	public function tableAction() {
		try {
			$this->getResponse()->setHeader('content-type', 'text/plain; charset=UTF-8', true);
			$this->getResponse()->sendHeaders();

			if ($this->getRequest()->getParam('accessCode') != Mage::getStoreConfig('boxalinocem/synchronization/access_code')) {
				return;
			}

			$table = $this->getRequest()->getParam('table');
			$order = $this->getRequest()->getParam('order');
			$offset = max(1, intval($this->getRequest()->getParam('offset')));
			$count = max(1, min(intval($this->getRequest()->getParam('count')), 10000));
			$export = Mage::getResourceModel('boxalinocem/TableExport');

			$f = fopen('php://output', 'a');
			$first = TRUE;
			foreach ($export->select($table, $order, $offset, $count) as $row) {
				if ($first) {
					$first = FALSE;
					fputcsv($f, array_keys($row));
				}
				fputcsv($f, array_values($row));
			}
			fclose($f);
		} catch (Exception $e) {
			if (Mage::getStoreConfig('boxalinocem/frontend/debug') == 1) {
				echo($e);
			}
		}
	}
}
