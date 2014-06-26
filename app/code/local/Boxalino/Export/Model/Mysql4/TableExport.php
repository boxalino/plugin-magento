<?php

/**
 * Boxalino Table Export
 *
 * @author nitro@boxalino.com
 */
class Boxalino_Export_Model_Mysql4_TableExport extends Mage_Core_Model_Mysql4_Abstract {
	protected function _construct() {
		$this->_init('boxalinocem/tableexport', '');
	}


	public function select($table, $order = NULL, $offset = 0, $count = 30) {
		$rows = array();
		$select = $this->_getReadAdapter()->select()
			->from(array('t' => strpos($table, '/') > 0 ? $this->getTable($table) : $table))
			->order($order)
			->limit($count, $offset);
		$query = $this->_getReadAdapter()->query($select);
		while ($row = $query->fetch()) {
			$rows[] = $row;
		}
		return $rows;
	}
}
