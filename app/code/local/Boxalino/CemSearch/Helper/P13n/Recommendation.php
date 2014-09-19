<?php

/**
 * Created by: Szymon Nosal <szymon.nosal@codete.com>
 * Created at: 12.06.14 16:36
 */
class Boxalino_CemSearch_Helper_P13n_Recommendation
{
    private $returnFields = array('id');
    private $results = array();

    public static function Instance()
    {
        static $inst = null;
        if ($inst === null) {
            $inst = new Boxalino_CemSearch_Helper_P13n_Recommendation();
        }
        return $inst;
    }

    public function getRecommendation($widgetType, $widgetName)
    {
        if(empty($this->results)) {
            $widgets = $this->prepareWidgets($widgetType);
            if (empty($widgets)) {
                return null;
            }
            $account = Mage::helper('Boxalino_CemSearch')->getAccount();
            $language = substr(Mage::app()->getLocale()->getLocaleCode(), 0, 2);
            $returnFields = $this->returnFields;

            $entity_id = Mage::getStoreConfig('Boxalino_General/search/entity_id');
            $authData['username'] = Mage::getStoreConfig('Boxalino_General/general/p13n_username');
            $authData['password'] = Mage::getStoreConfig('Boxalino_General/general/p13n_password');
            $entityIdFieldName = 'entity_id';
            if (isset($entity_id) && $entity_id !== '') {
                $entityIdFieldName = $entity_id;
            }
            $p13nClient = new Boxalino_CemSearch_Helper_P13n_Client($account, $authData, $language, $entityIdFieldName, true);
            $this->results = $p13nClient->getPersonalRecommendations($widgets, $returnFields, $widgetType);
        }
        return $this->results[$widgetName];
    }

    private function prepareWidgets($widgetType)
    {
        $widgets = array();
        $recommendations = Mage::getStoreConfig('Boxalino_Recommendation');
        foreach($recommendations as $recommendation) {
            if(
                (!empty($recommendation['min']) || $recommendation['min'] >= 0) &&
                (!empty($recommendation['max']) || $recommendation['max'] >= 0) &&
                !empty($recommendation['scenario']) &&
                ($recommendation['min'] <= $recommendation['max']) &&
                $recommendation['status'] == true) {
                if ($recommendation['scenario'] == $widgetType) {
                    $widgets[] = array('name' => $recommendation['widget'], 'min_recs' => $recommendation['min'], 'max_recs' => $recommendation['max']);
                }
            }
        }
        return $widgets;
    }
}
