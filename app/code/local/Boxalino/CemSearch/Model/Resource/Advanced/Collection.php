<?php
/**
 * Created by: Szymon Nosal <szymon.nosal@codete.com>
 * Created at: 06.06.14 11:36
 */

/**
 * Collection Advanced
 *
 * @category    Mage
 * @package     Mage_CatalogSearch
 * @author      Magento Core Team <core@magentocommerce.com>
 */
class Boxalino_CemSearch_Model_Resource_Advanced_Collection extends Mage_Catalog_Model_Resource_Product_Collection
{
    /**
     * Add products id to search
     *
     * @param array $ids
     * @return Mage_CatalogSearch_Model_Resource_Advanced_Collection
     */
    public function addIdFromBoxalino($ids)
    {

        $this->addFieldToFilter('entity_id', array('in' => $ids));

        return $this;
    }

}