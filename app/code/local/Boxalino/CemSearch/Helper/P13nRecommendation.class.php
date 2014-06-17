<?php
/**
 * Created by: Szymon Nosal <szymon.nosal@codete.com>
 * Created at: 12.06.14 16:36
 */

class P13nRecommendation {

    public function getRecommendation($widget, $scenario = null, $lang = 'en'){

        $name = 'recommendation_widget';
        $account = $this->getAccount();
        $language = $lang;
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

        Mage::helper('Boxalino_CemSearch')->__loadClass('P13nClient');
        Mage::helper('Boxalino_CemSearch')->__loadClass('AbstractThrift', true, null);
        Mage::helper('Boxalino_CemSearch')->__loadClass('P13n', true, null);
        Mage::helper('Boxalino_CemSearch')->__loadClass('Utils', true, null);

        $entity_id = Mage::getStoreConfig('Boxalino_CemSearch/general/entity_id');
        $entityIdFieldName = 'entity_id';

        if(isset($entity_id) && $entity_id !== ''){
            $entityIdFieldName = $entity_id;
        }

        $name = Mage::getStoreConfig('Boxalino_CemSearch/recommendation/' . $widget);
//        var_dump($name);
        if($name == "" || $name == null){
            return array();
        }

        $p13nClient = new BoxalinoP13nClient($account, $language, $entityIdFieldName, true);

        return $p13nClient->getPersonalRecommendations($name, $returnFields, 0, 6, $scenario);
    }

    /**
     * @return string
     */
    protected function getAccount()
    {

        $isDev = Mage::getStoreConfig('Boxalino_CemSearch/backend/account_dev');
        $account = Mage::getStoreConfig('Boxalino_CemSearch/backend/account');

        if ($isDev) {
            return $account . '_dev';
        }
        return $account;
    }
}
