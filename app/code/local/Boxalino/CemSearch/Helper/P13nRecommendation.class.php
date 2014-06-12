<?php
/**
 * Created by: Szymon Nosal <szymon.nosal@codete.com>
 * Created at: 12.06.14 16:36
 */

class P13nRecommendation {

    public function getRecommendation($account, $scenario = null, $lang = 'en'){

        $name = 'recommendation_widget';
//        $account = $account;
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

        $mageConfig = Mage::getStoreConfig('Boxalino_CemSearch/general');
        $entityIdFieldName = 'entity_id';

        if(isset($mageConfig['entity_id']) && $mageConfig['entity_id'] !== ''){
            $entityIdFieldName = $mageConfig['entity_id'];
        }

        $p13nClient = new BoxalinoP13nClient($account, $language, $entityIdFieldName, 'true');
        var_dump($p13nClient->getPersonalRecommendations($name, $returnFields, 0, 5, $scenario));
    }
}
