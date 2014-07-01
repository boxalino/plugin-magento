<?php

/**
 * Boxalino CemExport Indexer: DbMind
 *
 * @author nitro@boxalino.com
 */
class Boxalino_Export_Model_Mysql4_Dbmind_Indexer extends Boxalino_Export_Model_Mysql4_Indexer {
    private $zipFile = null;
    private $languagesFile = null;
    private $categoriesFile = null;
    private $tagsFile = null;
    private $fieldsFile = null;
    private $itemsMainFile = null;
    private $itemsFieldsFile = null;
    private $itemsCombinationsFile = null;
    private $itemsMain = null;
    private $itemsFields = null;
    private $itemsCombinations = null;
    private $groupProducts = null;
    private $groupProductsFile = null;


    protected function _construct() {
        $this->_init('boxalinocem/indexer', '');

        $this->zipFile = tempnam(sys_get_temp_dir(), 'magento_cem_csvsync');
        $this->languagesFile = tempnam(sys_get_temp_dir(), 'magento_cem_csvsync');
        $this->categoriesFile = tempnam(sys_get_temp_dir(), 'magento_cem_csvsync');
        $this->tagsFile = tempnam(sys_get_temp_dir(), 'magento_cem_csvsync');
        $this->fieldsFile = tempnam(sys_get_temp_dir(), 'magento_cem_csvsync');
        $this->itemsMainFile = tempnam(sys_get_temp_dir(), 'magento_cem_csvsync');
        $this->itemsFieldsFile = tempnam(sys_get_temp_dir(), 'magento_cem_csvsync');
        $this->itemsCombinationsFile = tempnam(sys_get_temp_dir(), 'magento_cem_csvsync');
        $this->groupProductsFile = tempnam(sys_get_temp_dir(), 'magento_cem_csvsync');
    }


    protected function _beginSync($categories, $tags, $fields) {
        // write languages
        $f = fopen($this->languagesFile, "w");
        if (!$f) {
            return FALSE;
        }
        $languages = array();
        foreach ($this->getStores() as $store) {
            $languages[] = $store->getConfig('boxalinocem/service/language');

            // write row
            $row = array();
            $row[] = $store->getConfig('boxalinocem/service/language');
            $row[] = '';
            $row[] = '';
            $row[] = '';
            if (!fputcsv($f, $row)) {
                return FALSE;
            }
        }
        fclose($f);

        // write categories
        $f = fopen($this->categoriesFile, "w");
        if (!$f) {
            return FALSE;
        }
        foreach ($categories as $category) {
            // write row
            $row = array();
            $row[] = $category['id'];
            $row[] = $category['parent'];
            $row[] = $category['position'];
            foreach ($languages as $language) {
                $row[] = isset($category['names'][$language]) ? $category['names'][$language] : '';
            }
            if (!fputcsv($f, $row)) {
                return FALSE;
            }
        }
        fclose($f);

        // write tags
        $f = fopen($this->tagsFile, "w");
        if (!$f) {
            return FALSE;
        }
        foreach ($tags as $tag) {
            // write row
            $row = array();
            $row[] = $tag['id'];
            foreach ($languages as $language) {
                $row[] = isset($tag['names'][$language]) ? $tag['names'][$language] : '';
            }
            if (!fputcsv($f, $row)) {
                return FALSE;
            }
        }
        fclose($f);

        // write fields
        $f = fopen($this->fieldsFile, "w");
        if (!$f) {
            return FALSE;
        }
        foreach ($fields as $code => $attribute) {
            // find field type & modes
            $type = $this->getAttributeType($attribute);
            $sortable = $attribute->used_for_sort_by;
            $guidable = $attribute->is_filterable || $attribute->is_filterable_in_search;
            $searchable = $attribute->is_searchable || $attribute->is_visible_in_advanced_search;

            // find field labels
            $names = array();
            foreach ($languages as $language) {
                $names[$language] = $attribute->getFrontend()->getLabel();
            }
            foreach ($attribute->getStoreLabels() as $storeId => $label) {
                $store = Mage::app()->getStore($storeId);
                $names[$store->getConfig('boxalinocem/service/language')] = $label;
            }

            // write row
            $row = array();
            $row[] = $code;
            $row[] = $type;
            $row[] = $sortable ? 'true' : 'false';
            $row[] = $guidable ? 'true' : 'false';
            $row[] = $searchable ? 'true' : 'false';
            foreach ($languages as $language) {
                $row[] = $names[$language];
            }
            if (!fputcsv($f, $row)) {
                return FALSE;
            }
        }
        fclose($f);

        // open item files
        $this->itemsMain = fopen($this->itemsMainFile, "w");
        $this->itemsFields = fopen($this->itemsFieldsFile, "w");
        $this->itemsCombinations = fopen($this->itemsCombinationsFile, "w");
        $this->groupProducts = fopen($this->groupProductsFile, "w");

        return ($this->itemsMain && $this->itemsFields && $this->itemsCombinations && $this->groupProducts);
    }

    protected function _beginStoreSync($store) {
        return TRUE;
    }

    protected function _processStoreSync($store, $categories, $tags, $fields, $productCategories, $productTags, $productAttributes, $productCombinations, $productSubProducts) {
        $productId = $store->getWebsite()->getCode().'_'.$store->getCode().'_'.$productAttributes['entity_id'][0];
        $languageId = $store->getConfig('boxalinocem/service/language');

        // find times
        $addedTime = new Zend_Date($productAttributes['created_at'][0], 'yyyy-MM-dd HH:mm:ss');
        $changedTime = new Zend_Date($productAttributes['updated_at'][0], 'yyyy-MM-dd HH:mm:ss');

        // find prices
        $standardPrice = isset($productAttributes['price']) ? $productAttributes['price'][0] : 0;
        $discountedPrice = isset($productAttributes['special_price']) ? $productAttributes['special_price'][0] : 0;
        if (isset($productAttributes['special_from_date'])) {
            $discountFrom = new Zend_Date($productAttributes['special_from_date'][0], 'yyyy-MM-dd HH:mm:ss');
            if ($discountFrom->compare(new Zend_Date()) > 0) {
                $discountedPrice = 0;
            }
        }
        if (isset($productAttributes['special_to_date'])) {
            $discountTo = new Zend_Date($productAttributes['special_to_date'][0], 'yyyy-MM-dd HH:mm:ss');
            if ($discountTo->compare(new Zend_Date()) < 0) {
                $discountedPrice = 0;
            }
        }

        // write item
        $row = array();
        $row[] = $productId;
        $row[] = $store->getWebsite()->getCode().'_'.$store->getCode();
        $row[] = $productAttributes['entity_id'][0];
        $row[] = $standardPrice;
        $row[] = $addedTime->get('yyyy-MM-dd HH:mm:ss');
        $row[] = $changedTime->get('yyyy-MM-dd HH:mm:ss');
        $row[] = $productAttributes['_stock'][0];
        $row[] = $productAttributes['_views'][0];
        $row[] = $productAttributes['_sales'][0];
        $row[] = $discountedPrice;
        $row[] = $productAttributes['entity_id'][0];
        if (!fputcsv($this->itemsMain, $row)) {
            return FALSE;
        }

        // write item categories
        foreach ($productCategories as $category) {
            if (!isset($categories[$category])) {
                continue;
            }
            if (!isset($categories[$category]['names'][$languageId])) {
                continue;
            }

            $row = array();
            $row[] = $productId;
            $row[] = 'categories';
            $row[] = '';
            $row[] = $category;
            if (!fputcsv($this->itemsFields, $row)) {
                return FALSE;
            }
        }

        // write item tags
        foreach ($productTags as $tag) {
            if (!isset($tags[$tag])) {
                continue;
            }
            if (!isset($tags[$tag]['names'][$languageId])) {
                continue;
            }

            $row = array();
            $row[] = $productId;
            $row[] = 'tags';
            $row[] = '';
            $row[] = $tag;
            if (!fputcsv($this->itemsFields, $row)) {
                return FALSE;
            }
        }

        // write item title
        if (isset($productAttributes['name'])) {
            $row = array();
            $row[] = $productId;
            $row[] = 'title';
            $row[] = $languageId;
            $row[] = $this->normalizeText($productAttributes['name'][0]);
            if (!fputcsv($this->itemsFields, $row)) {
                return FALSE;
            }
        }

        // write item body
        if (isset($productAttributes['description'])) {
            $row = array();
            $row[] = $productId;
            $row[] = 'body';
            $row[] = $languageId;
            $row[] = $this->normalizeText(implode("\n", $productAttributes['description']));
            if (!fputcsv($this->itemsFields, $row)) {
                return FALSE;
            }
        }

        // write item fields
        foreach ($productAttributes as $code => $values) {
            if (!isset($fields[$code])) {
                continue;
            }
            foreach ($values as $value) {
                if (strlen($value) == 0) {
                    continue;
                }

                $row = array();
                $row[] = $productId;
                $row[] = $code;
                $row[] = $this->getAttributeType($fields[$code]) == 'text' ? $languageId : '';
                $row[] = $this->normalizeText($value);
                if (!fputcsv($this->itemsFields, $row)) {
                    return FALSE;
                }
            }
        }

        // write item combinations
        foreach ($productCombinations['products'] as $productCombination) {
            if (!isset($productCombination['entity_id'])) {
                continue;
            }
            $row = array();
            $row[] = $productId;
            $row[] = $store->getWebsite()->getCode().'_'.$store->getCode().'_'.$productCombination['entity_id'][0];
            $row[] = isset($productCombination['price']) ? $productCombination['price'][0] : 0;
            $row[] = $productCombination['_stock'][0];
            foreach ($productCombinations['attributes'] as $attribute) {
                $row[] = $attribute;
                $row[] = isset($productCombination[$attribute]) ? $productCombination[$attribute][0] : '';
            }
            $row[] = $productCombination['entity_id'][0];
            if (!fputcsv($this->itemsCombinations, $row)) {
                return FALSE;
            }
        }

        // write group products
        foreach ($productSubProducts as $productSubProduct) {
            $productSubProduct['id'] = $store->getWebsite()->getCode().'_'.$store->getCode().'_'.$productSubProduct['id'];
            $productSubProduct['subProduct_ID'] = $store->getWebsite()->getCode().'_'.$store->getCode().'_'.$productSubProduct['subProduct_ID'];
            $productSubProduct['subProduct_storeID'] = $languageId;
            if (!fputcsv($this->groupProducts, $productSubProduct)) {
                return FALSE;
            }
        }
        return TRUE;
    }

    protected function _endStoreSync($store) {
        return TRUE;
    }

    protected function _endSync() {
        $this->_close();

        // compress csv files
        $zip = new ZipArchive();
        if (!$zip->open($this->zipFile, ZIPARCHIVE::CREATE) ||
            !$zip->addFile($this->languagesFile, 'languages.csv') ||
            !$zip->addFile($this->categoriesFile, 'categories.csv') ||
            !$zip->addFile($this->tagsFile, 'tags.csv') ||
            !$zip->addFile($this->fieldsFile, 'fields.csv') ||
            !$zip->addFile($this->itemsMainFile, 'items.csv') ||
            !$zip->addFile($this->itemsFieldsFile, 'items-fields.csv') ||
            !$zip->addFile($this->itemsCombinationsFile, 'items-combinations.csv') ||
            !$zip->addFile($this->groupProductsFile, 'group-products.csv') ||
            !$zip->close()) {
            return FALSE;
        }

        //prepare accounts
        $accounts = array();
        foreach(Mage::app()->getWebsites() as $website) {
            $isDev = Mage::getStoreConfig('Boxalino_CemSearch/backend/account_dev');
            $account = Mage::app()->getWebsite($website->getId())->getConfig('Boxalino_CemSearch/backend/account');
            $accounts[] = $account;
        }

        //prepare url
        $url = Mage::getStoreConfig('Boxalino_CemSearch/backend/account_dev') ? Mage::getStoreConfig('boxalinocem/synchronization/url') .'/dev' : Mage::getStoreConfig('boxalinocem/synchronization/url');

        // upload csv files
        foreach($accounts as $account) {
            if (strlen($url) > 0) {
                $client = new CEM_HttpClient();
                if (($code = $client->post(
                        $url,
                        'multipart/form-data',
                        array(
                            'iframeAccount' => $account,
                            'accessCode' => Mage::getStoreConfig('boxalinocem/synchronization/access_code'),
                            'data' => '@' . $this->zipFile . ';type=application/zip'
                        )
                    )) != 200
                ) {
                    Mage::throwException(
                        Mage::helper('boxalinocem')->__('Synchronization server failure (code=%s)!', $code)
                    );
                    return FALSE;
                }
            }
        }
        return TRUE;
    }

    protected function _clearSync() {
//		header('Content-Type: application/zip');
//		echo(file_get_contents($this->zipFile));

        $this->_close();

        // delete temporary files
        @unlink($this->zipFile);
        @unlink($this->languagesFile);
        @unlink($this->categoriesFile);
        @unlink($this->tagsFile);
        @unlink($this->fieldsFile);
        @unlink($this->itemsMainFile);
        @unlink($this->itemsFieldsFile);
        @unlink($this->itemsCombinationsFile);
        @unlink($this->groupProductsFile);

//		exit;
    }

    private function _close() {
        // close item files
        if ($this->itemsMain != null) {
            fclose($this->itemsMain);
            $this->itemsMain = null;
        }
        if ($this->itemsFields != null) {
            fclose($this->itemsFields);
            $this->itemsFields = null;
        }
        if ($this->itemsCombinations != null) {
            @fclose($this->itemsCombinations);
            $this->itemsCombinations = null;
        }
        if ($this->groupProducts != null) {
            @fclose($this->groupProducts);
            $this->groupProducts = null;
        }
    }
}