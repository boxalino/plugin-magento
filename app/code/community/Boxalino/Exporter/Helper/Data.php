<?php

class Boxalino_Exporter_Helper_Data extends Mage_Core_Helper_Data
{
    const URL_XML = '/frontend/dbmind/en/dbmind/api/data/source/update';
    const URL_XML_DEV = '/frontend/dbmind/en/dbmind/api/data/source/update?dev=true';
    const URL_ZIP = '/frontend/dbmind/en/dbmind/api/data/push';
    const URL_ZIP_DEV = '/frontend/dbmind/en/dbmind/api/data/push?dev=true';
    public $exportServer = '';
    public $XML_DELIMITER = ',';
    public $XML_ENCLOSURE = '"';
    public $XML_ENCLOSURE_TEXT = "&quot;"; // it's $XML_ENCLOSURE
    public $XML_NEWLINE = '\n';
    public $XML_ESCAPE = '\\\\';
    public $XML_ENCODE = 'UTF-8';
    public $XML_FORMAT = 'CSV';
    protected $_attributesWithIds = array();
    protected $_allTags = array();
    protected $_countries = array();
    protected $_languages = array(
        'en',
        'fr',
        'de',
        'it',
        'es',
        'zh',
        'cz',
        'ru',
    );

    /**
     * @param $language
     * @return bool
     */
    public function isAvailableLanguages($language)
    {
        if (array_search($language, $this->_languages) !== false) {
            return true;
        }
        return false;
    }

    public function getExportServer()
    {
        if (empty($this->exportServer)) {
            $this->exportServer = Mage::getStoreConfig('boxalinoexporter/export_data/export_server');
        }

        return $this->exportServer;
    }

    /**
     * @return array Return array with attributes which have connect optionId = optionValue
     */
    public function attributesWithIds()
    {
        if (empty($this->_attributesWithIds)) {
            $attributes = Mage::getResourceModel('eav/entity_attribute_collection')->getData();
            foreach ($attributes as $attribute) {
                if ($attribute['frontend_input'] == 'select' || $attribute['frontend_input'] == 'multiselect') {
                    $this->_attributesWithIds[] = $attribute['attribute_code'];
                }
            }
            $attributes = null;
        }
        return $this->_attributesWithIds;
    }

    /**
     * @return array Array of all tags array('tag_id' => 'value');
     */
    public function getAllTags()
    {
        if (empty($this->_allTags)) {
            $tagsModel = Mage::getModel('tag/tag');
            $tags = $tagsModel->getCollection()->addStatusFilter($tagsModel->getApprovedStatus())->getData();
            foreach ($tags as $tag) {
                $this->_allTags[$tag['tag_id']] = $tag['name'];
            }
            $tags = null;
            $tagsModel = null;
        }

        return $this->_allTags;
    }

    /**
     * @return string URL to normal data sync
     * @param $dev
     */
    public function getZIPSyncUrl($dev = false)
    {
        $exportServer = $this->getExportServer();
        if ($dev) {
            return $exportServer . self::URL_ZIP_DEV;
        } else {
            return $exportServer . self::URL_ZIP;
        }
    }

    /**
     * @return string URL to delta sync
     * @param $dev
     */
    public function getXMLSyncUrl($dev = false)
    {
        $exportServer = $this->getExportServer();
        if ($dev) {
            return $exportServer . self::URL_XML_DEV;
        } else {
            return $exportServer . self::URL_XML;
        }

    }

    public function getError($responseBody)
    {
        $htmlTagsToReplace = array('body', 'p', 'br');
        $startPosition = strpos($responseBody, '<p>');
        $endPosition = strpos($responseBody, '&lt;br&gt;') + 3;
        $error = html_entity_decode(substr($responseBody, $startPosition, $endPosition));
        foreach ($htmlTagsToReplace as $tag) {
            $error = str_replace('<' . $tag . '>', PHP_EOL, $error);
        }
        return $error;
    }

    public function getCountry($countryCode)
    {

        if (!isset($this->_countries[$countryCode])) {
            $country = Mage::getModel('directory/country')->loadByCode($countryCode);
            $this->_countries[$countryCode] = $country;
        }

        return $this->_countries[$countryCode];
    }

    /**
     * Modifies a string to remove all non ASCII characters and spaces.
     */
    public function sanitizeFieldName($text)
    {

        $maxLength = 50;
        $delimiter = "_";

        // replace non letter or digits by -
        $text = preg_replace('~[^\\pL\d]+~u', $delimiter, $text);

        // trim
        $text = trim($text, $delimiter);

        // transliterate
        if (function_exists('iconv')) {
            $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        }

        // lowercase
        $text = strtolower($text);

        // remove unwanted characters
        $text = preg_replace('~[^_\w]+~', '', $text);

        if (empty($text)) {
            return null;
        }

        // max $maxLength (50) chars
        $text = substr($text, 0, $maxLength);
        $text = trim($text, $delimiter);

        return $text;
    }

    public function escapeString($string)
    {
        return htmlspecialchars(trim(preg_replace('/\s+/', ' ', $string)));
    }

    public function delTree($dir)
    {
        if (!file_exists($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            if (is_dir("$dir/$file")) {
                self::delTree("$dir/$file");
            } else if (file_exists("$dir/$file")) {
                @unlink("$dir/$file");
            }
        }
        return rmdir($dir);
    }

    public function rewrittenProductUrl($productId, $storeId)
    {
        $coreUrl = Mage::getModel('core/url_rewrite');
        $coreUrl->setStoreId($storeId);
        $coreUrl->loadByIdPath(sprintf('product/%d', $productId));
        return $coreUrl->getRequestPath();
    }

}
