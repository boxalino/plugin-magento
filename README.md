# boxalino Magento


## Installation

1. Download and unzip the archive.
2. Go to the directory you just unzipped the plugin into
3. Copy all files and directories into main Magento directory.
4. In admin panel:
  * System > Cache Management > Flush Magento Cache
  * System > Cache Management > Flush Cache Storage
  * System > Permissions > Roles > Administrators > Save Role 
5. Set chmod like:

<pre>
chmod 755 -R app/code/local/Boxalino/
chmod 755 app/design/frontend/base/default/layout/boxalino.xml
chmod 755 app/design/frontend/base/default/template/boxalino/catalogsearch/form.mini.phtml
chmod 755 app/design/frontend/base/default/template/boxalino/head.phtml
chmod 755 app/etc/modules/Boxalino_CemSearch.xml
chmod 755 app/etc/modules/Boxalino_Export.xml
chmod 755 skin/frontend/base/default/css/boxalinoCemSearch.css
chmod 755 skin/frontend/base/default/js/boxalinoAutocomplete.js
chmod 755 skin/frontend/base/default/js/jquery-1.10.2.min.js
chmod 755 skin/frontend/base/default/js/jquery-noConflict.js
</pre>

## Configuration

The configuration is available in **System > Configuration** and **Boxalino Extensions** in Magento admin panel.

### Boxalino Configuration

#### Boxalino Configuration

+ **Host** - Eg: cdn.bx-cloud.com
+ **Dev environment** - If true, then using is dev account
+ **Account** - Account name in Boxalino
+ **Username** - User account to access to API
+ **Password** - User password to access to API
+ **Domain** - Actual shop domain
+ **IndexId** - ???

#### General Configuration
+ **Quick search** - Widget name for quick search. It’s create in Boxalino
+ **Quick search limit** - Max number of products, returned from Boxalino for quick search.
+ **Advanced search** - Widget name for advanced search. It’s create in Boxalino
+ **Advanced search limit** - Max number of products, returned from Boxalino for advanced search.
+ **Autocomplete** - Widget name for autocomplete search. It’s create in Boxalino
+ **Autocomplete limit** - Max number of words, returned from Boxalino for autocomplete search.
+ **Autocomplete products limit** - Max number of products, returned from Boxalino for autocompelte search.
+ **Id field name** - Product id field name (default: entity_id)

#### Tracking

+ **Enable plugin** - Tracking plugin status.
+ **Enable Sales Tracking** - Sales trackicj plugin staus.
+ **Enable Analytics** - Analytisc plugin status
+ **Character encoding** - Character encoding for page (default: UTF-8)


### Boxalino Recommendation

#### Cart Configuration

+ **Widget enabled** - Plugin status
+ **Widget name** - Widget name for cart recommendation. It’s create in Boxalino
+ **Minimum recommendations** - Minimum number of products, returned from Boxalino for cart recommendation.
+ **Maximum recommendations** - Maximum number of products, returned from Boxalino for cart recommendation.
+ **Scenario** - Scenario name. Recommended for cart: basket

#### Related Configuration

+ **Widget enabled** - Plugin status
+ **Widget name** - Widget name for related recommendation. It’s create in Boxalino
+ **Minimum recommendations** - Minimum number of products, returned from Boxalino for related recommendation.
+ **Maximum recommendations** - Maximum number of products, returned from Boxalino for related recommendation.
+ **Scenario** - Scenario name. Recommended for related: product

#### Upsell Configuration

+ **Widget enabled** - Plugin status
+ **Widget name** - Widget name for upsell recommendation. It’s create in Boxalino
+ **Minimum recommendations** - Minimum number of products, returned from Boxalino for upsell recommendation.
+ **Maximum recommendations** - Maximum number of products, returned from Boxalino for upsell recommendation.
+ **Scenario** - Scenario name. Recommended for upsell: product


### Boxalino Exporter

#### Service Configuration

+ **Display debug output** - Display debug when something went wrong with export.

#### Data Synchronization

+ **Bridge URL** - Place where data are send after reindex (Eg: http://di1.bx-cloud.com/frontend/dbmind/en/dbmind/files/csv/push/)
+ **Access Code** - Data connector access code filled to DI
+ **Export Categories** - If yes, then categories will be exported to Boxalino
+ **Export Tags** - If yes, then tags will be exported to Boxalino
+ **Product Image Sizes** - Eg: standard:300x300
+ **Product Thumbnail Sizes** - Eg: standard:70x70,list:235x135r-90
+ **Additional Attributes (optional)** - Attributes which should be exported with normal attributes (coma-separated list)
+ **Maximum amount of product to export** - Maximum amount of products to export to Boxalino. (0 = all)


All fields (unless otherwise stated) are mandatory. Some fields have default parameter.


## Export

Export is available in **System > Index Management**.
To export data, please click on **Reindex Data**, next to Boxalino Export index.

When export is complete correctly, will appear message “Boxalino Export Index index was rebuilt.”, and all data are available in Data Intelligence.

## How It Works

[screencast]
