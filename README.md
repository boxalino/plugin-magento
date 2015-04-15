# boxalino Magento plugin

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
7. Follow the documentation to create your first product index, needed to enable the search & recommendations
8. Set up a an indexing cronjob, running at least one full index per day. Use the delta indexer if you want to update more than once per hour.

## Documentation

The latest documentation PDF can be found at:
https://boxalino.zendesk.com/hc/en-gb/articles/203757896-Getting-started-with-the-boxalino-plugin-for-Magento
