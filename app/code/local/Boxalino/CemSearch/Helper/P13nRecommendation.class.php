<?php
/**
 * Created by: Szymon Nosal <szymon.nosal@codete.com>
 * Created at: 12.06.14 16:36
 */

class P13nRecommendation {

    public function getRecommendation($widget){

        $account = $this->getAccount();
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
        $recommendation = Mage::getStoreConfig('Boxalino_CemSearch/recommendation');

        if(isset($recommendation[$widget . '_status']) && $recommendation[$widget . '_status'] == 0){
            return null;
        }

        $name = isset($recommendation[$widget . '_widget'])?$recommendation[$widget . '_widget']:null;

        if($name == "" || $name == null){
            return null;
        }

        $min = isset($recommendation[$widget . '_min'])?$recommendation[$widget . '_min']:null;
        $max = isset($recommendation[$widget . '_max'])?$recommendation[$widget . '_max']:null;

        if($max == null || $min > $max){
            return null;
        }

        if($widget == 'related' || $widget == 'upsell'){
            $scenario = 'product';
        } elseif ($widget == 'cart'){
            $scenario = 'basket';
        } else{
            $scenario = null;
        }

        $p13nClient = new BoxalinoP13nClient($account, $language, $entityIdFieldName, true);

        return $p13nClient->getPersonalRecommendations($name, $returnFields, $min, $max, $scenario);
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
