# boxalino Magento

## Installation

1. Download and unzip the archive.
2. Go to the directory you just unzipped the plugin into
3. Copy all files and directories into main Magento directory.
4. Set chmod for Boxalino directory and files:
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
5. Clear the cache:
   * System > Cache Management - Flush Magento Cache
   * System > Cache Management - Flush Cache Storage
6. Update the administrator role:
  * System > Permissions > Roles > Administrators - Save Role

## Configuration

The configuration is available in **System > Configuration** in Magento admin panel.

### Boxalino Configuration

#### Boxalino Configuration

+ **Host** - URL of Boxalino server. Eg: cdn.bx-cloud.com
+ **Dev environment** - If true, then development account is used
+ **Account** - Customer name in Boxalino
+ **Username** - User account to access to API
+ **Password** - User password to access to API
+ **Domain** - Actual shop domain

#### General Configuration
+ **Quick search** - Widget name for quick search. In is created in Boxalino
+ **Quick search limit** - Maximum number of products returned from Boxalino for quick search.
+ **Advanced search** - Widget name for advanced search. In is created in Boxalino
+ **Advanced search limit** - Maximum number of products returned from Boxalino for advanced search.
+ **Autocomplete** - Widget name for autocomplete search. In is created in Boxalino
+ **Autocomplete limit** - Maximum number of words, returned from Boxalino for autocomplete search.
+ **Autocomplete products limit** - Maximum number of products returned from Boxalino for autocompelte search.
+ **Id field name** - Product id field name (default: entity_id)

#### Tracking

+ **Enable plugin** - Tracking plugin status.
+ **Enable Sales Tracking** - Sales tracking plugin status.
+ **Enable Analytics** - Analytics plugin status
+ **Character encoding** - Character encoding for page (default: UTF-8)


### Boxalino Recommendation

#### Cart Configuration

+ **Widget enabled** - Plugin status
+ **Widget name** - Widget name for cart recommendation. In is created in Boxalino
+ **Minimum recommendations** - Minimum number of products returned from Boxalino for cart recommendation.
+ **Maximum recommendations** - Maximum number of products returned from Boxalino for cart recommendation.
+ **Scenario** - Scenario name. Recommended scenario for cart: basket

#### Related Configuration

+ **Widget enabled** - Plugin status
+ **Widget name** - Widget name for related recommendation. In is created in Boxalino
+ **Minimum recommendations** - Minimum number of products returned from Boxalino for related recommendation.
+ **Maximum recommendations** - Maximum number of products returned from Boxalino for related recommendation.
+ **Scenario** - Scenario name. Recommended scenario for related: product

#### Upsell Configuration

+ **Widget enabled** - Plugin status
+ **Widget name** - Widget name for upsell recommendation. In is created in Boxalino
+ **Minimum recommendations** - Minimum number of products returned from Boxalino for upsell recommendation.
+ **Maximum recommendations** - Maximum number of products returned from Boxalino for upsell recommendation.
+ **Scenario** - Scenario name. Recommended scenario for upsell: product


### Boxalino Exporter

#### Service Configuration

+ **Display debug output** - Display debug when something went wrong with export.
+ **Language Code** - shop language using in export data.


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

When export is completed correctly, message will appear: “Boxalino Export Index index was rebuilt.”. After it all data are available in Data Intelligence.

After the export is done, you may be required to take some actions in the boxalino's administration panel to see products immediately otherwise they will be added in daily synchronization.
To force synchronization immediately, execute **main_sync** and **generate_optimization** tasks.


## How It Works

[screencast]
