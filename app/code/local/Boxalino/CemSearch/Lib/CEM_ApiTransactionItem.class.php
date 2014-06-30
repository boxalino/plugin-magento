<?php

/** @addtogroup cem
 *
 * @{
 */

/**
 * @internal
 *
 * Boxalino CEM client library in PHP.
 *
 * (C) 2009-2012 - Boxalino AG
 */


/**
 * Transaction item (product)
 *
 * @author nitro@boxalino.com
 */
class CEM_ApiTransactionItem {
	/**
	 * Item identifier
	 */
	public $id = '';

	/**
	 * Item price
	 */
	public $price = 0;

	/**
	 * Item quantity
	 */
	public $quantity = 1;

	/**
	 * Item name
	 */
	public $name = '';

	/**
	 * Item widget
	 */
	public $widget = '';


	/**
	 * Constructor
	 *
	 * @param $id item identifier (required)
	 * @param $price item price (required)
	 * @param $quantity item quantity, defaults to 1 (optional)
	 * @param $name item name (optional)
	 * @param $widget facilitator widget (optional)
	 */
	public function __construct($id, $price, $quantity = 1, $name = FALSE, $widget = FALSE) {
		$this->id = $id;
		$this->price = $price;
		$this->quantity = $quantity;
		$this->name = $name;
		$this->widget = $widget;
	}
}

/**
 * @}
 */

?>