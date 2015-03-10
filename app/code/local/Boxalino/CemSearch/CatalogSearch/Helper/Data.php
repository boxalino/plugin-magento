<?php

class Boxalino_CemSearch_CatalogSearch_Helper_Data extends Mage_CatalogSearch_Helper_Data
{

    protected $_urlForSearch;

    public function setUrlForSearch($queryText)
    {
        $this->_urlForSearch = $queryText;
    }

    public function getUrlForSearch()
    {

        if (strlen($this->_urlForSearch) == 0) {
            return $this->getEscapedQueryText();
        }

        return $this->_urlForSearch;
    }

    public function setQueryText($text)
    {
        $this->_queryText = $text;
    }
}
