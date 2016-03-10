# boxalino Magento plugin

## Introduction

Welcome to the Boxalino Magento 1 plugin.

The Boxalino plugin for Magento enables you to easily and quickly benefit from all the functionalities of Boxalino Intelligence:

1. Boxalino Intelligent Search with auto-correction and sub-phrases relaxation
2. Faceted search with advanced multi-type facets, including the capacity to create smart facets based on unstructured textual content with our text-mining capacities and soft-facets to boost the best matching products with our unique smart-scoring algorithms.
3. Boxalino Autocomplete with advances textual and product suggestion while you are typing (even if you type it wrong)
4. Boxalino Recommendations for real-time personalized product suggestions
5. Boxalino Optimization platform to improve step-by-step your online sales performance based on our revolutionary self-learning technology based on statistical testing of marketing strategies (a/b testing, self-learning clusters, and much more)

The Boxalino plugin for Magento pre-integrates the most important key technical components of Boxalino:

1. Data export (including products, customers and transaction exports for multi-shops with dev and prod account and supporting on-demand quick delta synchronizations)
2. Boxalino tracker (pre-integration of Boxalino JavaScript tracker, our own tracker which follows strictly the Google Analytics model).
3. Search, Autocomplete and layered navigation (faceted navigation) with all intelligence functionalities pre-integrated (auto-correction, sub-phrases relaxation, etc.)
4. Similar and Complementary recommendations on product page and cross-selling on basket (cart) page

In addition, it is very easy to extend this installation basis to benefit from the following additional possibilities:

1. Layered navigation, to let Boxalino manage the entire product navigation on your web-site
2. Recommendations everywhere (easy to extend recommendations widgets on the home page, category pages, landing pages, content pages, etc.).
3. Quick-finder to enable new ways to find product with simple criteria and soft-facets with our unique smart-scoring capacities.
4. Personalized newsletter & trigger personalized mail (use the base of data export and tracking from our plugin to simply integrate personalized product recommendations in your e-mail marketing activities)
5. Advanced reporting to integrate any of your online behaviors in other Business Intelligence and Data Mining projects with our flexible Reporting API functionalities

If you need more information on any of these topics, please don't hesitate to contact Boxalino at sales@boxalino.com. We will be glad to assist you!

N.B.: This project is for Magento 1, in case you need our plugin for Magento 2, please go to https://github.com/boxalino/plugin-magento2)

## Installation

1. Download and unzip the archive.
2. Go to the directory you just unzipped the plugin into.
3. Copy all files and directories into main Magento directory.
4. Set chmod for Boxalino directory and files:
    * chmod 755 -R app/code/local/Boxalino
    * chmod 755 app/design/frontend/base/default/layout/boxalino.xml
    * chmod 755 -R app/design/frontend/base/default/template/boxalino/*
    * chmod 755 app/etc/modules/Boxalino_*.xml
    * chmod 755 skin/frontend/base/default/css/boxalinoCemSearch.css
    * chmod 755 skin/frontend/base/default/js/boxalinoAutocomplete.js
    * chmod 755 skin/frontend/base/default/js/jquery-1.10.2.min.js
    * chmod 755 skin/frontend/base/default/js/jquery-noConflict.js
5. Clear the cache:
    * System > Cache Management - Flush Magento Cache
    * System > Cache Management - Flush Cache Storage
6. Update the administrator role:
    * System > Permissions > Roles > Administrators - Save Role
7. Follow the [documentation PDF](https://boxalino.zendesk.com/hc/en-gb/articles/203757896-Getting-started-with-the-boxalino-plugin-for-Magento) to create your first product index, needed to enable search & recommendations.
8. Set up a an indexing cronjob, running at least one full index per day. Use the delta indexer if you want to update more than once per hour.

## Documentation

The latest documentation PDF can be found on the [Magento plugin's support page](https://boxalino.zendesk.com/hc/en-gb/articles/203757896-Getting-started-with-the-boxalino-plugin-for-Magento).
