<?php

class Boxalino_CemSearch_Helper_Data extends Mage_Core_Helper_Data
{
    private $additionalFields = null;
    private $searchAdapter = null;

    public function __construct()
    {
        include_once(Mage::getModuleDir('', 'Boxalino_CemSearch') . '/Lib/vendor/Thrift/HttpP13n.php');
        spl_autoload_register(array('Boxalino_CemSearch_Helper_Data', '__loadClass'), TRUE, TRUE);
    }

    public static function __loadClass($name)
    {
        if (strpos($name, 'Thrift\\') !== false) {
            try {
                include_once(Mage::getModuleDir('', 'Boxalino_CemSearch') . '/Lib/vendor/' . str_replace('\\', '/', $name) . '.php');
            } catch (Exception $e) {
                Mage::throwException($e->getMessage());
            }
        }
    }

    public function getBasketAmount()
    {
        $checkout = Mage::getSingleton('checkout/session');
        $quote = $checkout->getQuote();
        $amount = 0;
        if ($quote) {
            foreach ($quote->getAllVisibleItems() as $item) {
                $amount += $item->getQty() * $item->getPrice();
            }
        }
        return $amount;
    }

    public function getBasketItems()
    {
        $items = array();
        $checkout = Mage::getSingleton('checkout/session');
        $quote = $checkout->getQuote();
        if ($quote) {
            foreach ($quote->getAllVisibleItems() as $item) {
                $items[] = $item->product_id;
            }
        }
        return $items;
    }

    public function getBasketContent()
    {
        $checkout = Mage::getSingleton('checkout/session');
        $quote = $checkout->getQuote();
        $items = array();
        if ($quote) {
            foreach ($quote->getAllVisibleItems() as $item) {
                $items[] = array(
                    'id' => $item->product_id,
                    'name' => $item->getProduct()->getName(),
                    'quantity' => $item->getQty(),
                    'price' => $item->getPrice(),
                    'widget' => $this->isProductFacilitated($item->product_id)
                );
            }
        }
        return @json_encode($items);
    }

    public function isSalesTrackingEnabled()
    {
        $trackSales = Mage::getStoreConfig('Boxalino_General/tracker/analytics');
        return ($trackSales == 1);
    }

    public function isAnalyticsEnabled()
    {
        return (bool)Mage::getStoreConfig('Boxalino_General/tracker/analytics');
    }

    public function reportSearch($term, $filters = null)
    {
        if ($this->isAnalyticsEnabled()) {
            $logTerm = addslashes($term);
            $script = "_bxq.push(['trackSearch', '" . $logTerm . "', " . json_encode($filters) . "]);" . PHP_EOL;
            return $script;
        } else {
            return '';
        }
    }

    public function reportProductView($product)
    {
        if ($this->isAnalyticsEnabled()) {
            $script = "_bxq.push(['trackProductView', '" . $product . "'])" . PHP_EOL;
            return $script;
        } else {
            return '';
        }
    }

    public function reportAddToBasket($product, $count, $price, $currency)
    {
        if ($this->isAnalyticsEnabled()) {
            $script = "_bxq.push(['trackAddToBasket', '" . $product . "', " . $count . ", " . $price . ", '" . $currency . "']);" . PHP_EOL;
            return $script;
        } else {
            return '';
        }
    }

    public function reportCategoryView($categoryID)
    {
        if ($this->isAnalyticsEnabled()) {
            $script = "_bxq.push(['trackCategoryView', '" . $categoryID . "'])" . PHP_EOL;
            return $script;
        } else {
            return '';
        }
    }

    public function reportLogin($customerId)
    {
        if ($this->isAnalyticsEnabled()) {
            $script = "_bxq.push(['trackLogin', '" . $customerId . "'])" . PHP_EOL;
            return $script;
        } else {
            return '';
        }
    }

    /**
     * @param $products array example:
     *      <code>
     *          array(
     *              array('product' => 'PRODUCTID1', 'quantity' => 1, 'price' => 59.90),
     *              array('product' => 'PRODUCTID2', 'quantity' => 2, 'price' => 10.0)
     *          )
     *      </code>
     * @param $orderId string
     * @param $price number
     * @param $currency string
     */
    public function reportPurchase($products, $orderId, $price, $currency)
    {
        $trackSales = Mage::getStoreConfig('Boxalino_General/tracker/track_sales');

        $productsJson = json_encode($products);
        if ($trackSales == 1) {
            $script = "_bxq.push([" . PHP_EOL;
            $script .= "'trackPurchase'," . PHP_EOL;
            $script .= $price . "," . PHP_EOL;
            $script .= "'" . $currency . "'," . PHP_EOL;
            $script .= $productsJson . "," . PHP_EOL;
            $script .= $orderId . "" . PHP_EOL;
            $script .= "]);" . PHP_EOL;
            return $script;
        } else {
            return '';
        }
    }

    public function getLoggedInUserId()
    {
        if (Mage::getSingleton('customer/session')->isLoggedIn()) {
            $customerData = Mage::getSingleton('customer/session')->getCustomer();
            return $customerData->getId();
        } else {
            return null;
        }
    }

    public function getAccount()
    {
        $isDev = Mage::getStoreConfig('Boxalino_General/general/account_dev');
        $account = Mage::getStoreConfig('Boxalino_General/general/di_account');

        if ($isDev) {
            return $account . '_dev';
        }
        return $account;
    }

    public function getFiltersValues($params)
    {
        $filters = new stdClass();
        if (isset($params['cat'])) {
            $filters->filter_hc_category = '';
            $category = Mage::getModel('catalog/category')->load($params['cat']);
            $categories = explode('/', $category->getPath());
            foreach ($categories as $cat) {
                $name = $category = Mage::getModel('catalog/category')->load($cat)->getName();
                if (strpos($name, '/') !== false) {
                    $name = str_replace('/', '\/', $name);
                }
                $filters->filter_hc_category .= '/' . $name;

            }
            unset($params['cat']);
        }

        if (isset($params['price'])) {
            $prices = explode('-', $params['price']);
            if (!empty($prices[0])) {
                $filters->filter_from_incl_price = $prices[0];
            }
            if (!empty($prices[1])) {
                $filters->filter_to_incl_price = $prices[1];
            }
            unset($params['price']);
        }
        if (isset($params)) {
            foreach ($params as $param => $values) {
                $getAttribute = Mage::getModel('catalog/product')->getResource()->getAttribute($param);
                if ($getAttribute !== false) {
                    $values = html_entity_decode($values);
                    preg_match_all('!\d+!', $values, $matches);
                    if (is_array($matches[0])) {
                        $attrValues = array();
                        foreach ($matches[0] as $id) {
                            $paramName = 'filter_' . $param;
                            $attribute = $attribute = $getAttribute->getSource()->getOptionText($id);
                            $attrValues[] = $attribute;
                        }
                        $filters->$paramName = $attrValues;
                    }
                }
            }
        }
        return $filters;
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

    public function getAdditionalFieldsFromP13n()
    {
        if ($this->additionalFields == null) {
            $this->additionalFields = explode(',', Mage::getStoreConfig('Boxalino_General/general/additional_fields'));
        }
        return !empty($this->additionalFields) ? $this->additionalFields : array();
    }

    public function getSearchAdapter()
    {

        if ($this->searchAdapter === null) {
            $storeConfig = Mage::getStoreConfig('Boxalino_General/general');
            $request = Mage::app()->getFrontController()->getRequest();

            $p13nConfig = new Boxalino_CemSearch_Helper_P13n_Config(
                $storeConfig['host'],
                Mage::helper('Boxalino_CemSearch')->getAccount(),
                $storeConfig['p13n_username'],
                $storeConfig['p13n_password'],
                $storeConfig['domain']
            );
            $p13nSort = new Boxalino_CemSearch_Helper_P13n_Sort();

            $field = '';
            $dir = '';
            $order = $request->getParam('order');
            if(isset($order)){
                if($order == 'name'){
                    $field = 'title';
                } elseif($order == 'price'){
                    $field = 'discountedPrice';
                }
            }
            $dirOrder = $request->getParam('dir');
            if($dirOrder){
                $dir = $dirOrder == 'asc' ? false : true;
            } else{
                $dir = false;
            }

            if($field !== '' && $dir !== ''){
                $p13nSort->push($field, $dir);
            }

            $this->searchAdapter = new Boxalino_CemSearch_Helper_P13n_Adapter($p13nConfig);

            $categoryId = $request->getParam('bx_category_id');
            if (empty($categoryId)) {
                /* @var $category Mage_Catalog_Model_Category */
                $category = Mage::registry('current_category');
                if (!empty($category)) {
                    $_REQUEST['bx_category_id'][0] = $category->getId();
                }
                // GET param 'cat' may override the current_category,
                // i.e. when clicking on subcategories in a category page
                $cat = $request->getParam('cat');
                if (!empty($cat)) {
                    $_REQUEST['bx_category_id'][0] = $cat;
                }
            }

            $generalConfig = Mage::getStoreConfig('Boxalino_General/search');
			
			if ($generalConfig['quick_search_limit'] == 0) {

				//find default value instead

				$storeConfig = Mage::getStoreConfig('catalog/frontend');

				$storeDisplayMode = $storeConfig['list_mode'];

				//we may get grid-list, list-grid, grid or list

				$storeMainMode = explode('-', $storeDisplayMode);

				$storeMainMode = $storeMainMode[0];

				$limit = $storeConfig[$storeMainMode . '_per_page'];

			} else {

				$limit = $generalConfig['quick_search_limit'];

			}
			
			$pageSize = (int) $request->getParam(
                'limit',
                $limit
            );
			
            $offset = abs(((int) $request->getParam('p', 1)) - 1) * $pageSize;

            $this->searchAdapter->setupInquiry(
                empty($generalConfig['quick_search']) ? 'search' : $generalConfig['quick_search'],
                Mage::helper('catalogsearch')->getQueryText(),
                substr(Mage::app()->getLocale()->getLocaleCode(), 0, 2),
                array($generalConfig['entity_id'], 'categories'),
                $p13nSort, $offset, $pageSize
            );

            $this->searchAdapter->search();
            $this->searchAdapter->prepareAdditionalDataFromP13n();
        }
        return $this->searchAdapter;
    }

    public function resetSearchAdapter()
    {
        $this->searchAdapter = null;
    }
}
