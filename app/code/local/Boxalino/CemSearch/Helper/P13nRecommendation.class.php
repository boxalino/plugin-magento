<?php
/**
 * Created by: Szymon Nosal <szymon.nosal@codete.com>
 * Created at: 12.06.14 16:36
 */

class P13nRecommendation {

    public function getRecommendation($widget){

        $account = Mage::helper('Boxalino_CemSearch')->getAccount();
        $language = substr(Mage::app()->getLocale()->getLocaleCode(),0,2);
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
        $recommendation = Mage::getStoreConfig('Boxalino_Recommendation/' . $widget);

        $status = $this->getParamFromConfig($recommendation, 'status');
        if($status == 0 || $status === null){
            return null;
        }

        $name = $this->getParamFromConfig($recommendation, 'widget');

        if($name == "" || $name == null){
            return null;
        }

        $min = $this->getParamFromConfig($recommendation, 'min');
        $max = $this->getParamFromConfig($recommendation, 'max');

        if($max == null || $min > $max || $max == 0){
            return null;
        }

        $scenario =  $this->getParamFromConfig($recommendation, 'scenario');

        $p13nClient = new BoxalinoP13nClient($account, $language, $entityIdFieldName, true);

//        var_dump($name);

        return $p13nClient->getPersonalRecommendations($name, $returnFields, $min, $max, $scenario);
    }

    private function getParamFromConfig($config, $param){
        return isset($config[$param])?$config[$param]:null;
    }

}
