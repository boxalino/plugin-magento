BoxalinoMagento
===============



1. Installation
Download and unzip the archive.
Go to the directory you just unzipped the plugin into, and copy all files and directories into main Magento directory.
Chmod ???

2. Configuration

The configuration is available in System > Configuration  and Boxalino Extensions in Magento admin panel.
1. Boxalino Configuration

Boxalino Configuration

Host
Boxalino host
Dev environment
If true, then using is dev account
Account
Account name in Boxalino
Username
User account to access to API
Password
User password to access to API
Domain
Shop domain
IndexId
?

General Configuration
Quick search
Widget name for quick search. It’s create in Boxalino
Quick search limit
Max number of products, returned from Boxalino for quick search.
Advanced search
Widget name for advanced search. It’s create in Boxalino
Advanced search limit
Max number of products, returned from Boxalino for advanced search.
Autocomplete
Widget name for autocomplete search. It’s create in Boxalino
Autocomplete limit
Max number of words, returned from Boxalino for autocomplete search.
Autocomplete products limit
Max number of products, returned from Boxalino for autocompelte search.
Id field name
Product id field name (default: entity_id)

Trackick

Enable plugin
Tracking plugin status.
Enable Sales Tracking
Sales trackicj plugin staus.
Enable Analytics
Analytisc plugin status
Character encoding
Character encoding for page (default: UTF-8)


2. Boxalino Recommendation configuration

Cart Configuration

Widget enabled
Plugin status
Widget name
Widget name for cart recommendation. It’s create in Boxalino
Minimum recommendations
Minimum number of products, returned from Boxalino for cart recommendation.
Maximum recommendations
Maximum number of products, returned from Boxalino for cart recommendation.
Scenario
Scenario name. Recommended for cart: basket

Related Configuration

Widget enabled
Plugin status
Widget name
Widget name for related recommendation. It’s create in Boxalino
Minimum recommendations
Minimum number of products, returned from Boxalino for related recommendation.
Maximum recommendations
Maximum number of products, returned from Boxalino for related recommendation.
Scenario
Scenario name. Recommended for related: product


Upsell Configuration

Widget enabled
Plugin status
Widget name
Widget name for upsell recommendation. It’s create in Boxalino
Minimum recommendations
Minimum number of products, returned from Boxalino for upsell recommendation.
Maximum recommendations
Maximum number of products, returned from Boxalino for upsell recommendation.
Scenario
Scenario name. Recommended for upsell: product


3. Boxalino Exporter Configuration

Service Configuration
Router URL - info
Router cluster - info


Display debug output
Display debug when something went wrong with export.

Data Synchronization

Bridge URL
Place where data are send after reindex
http://di1.bx-cloud.com/frontend/dbmind/en/dbmind/files/csv/push/
Access Code
Data connector access code filled to DI
Export Categories
If yes, then categories will be exported to Boxalino
Export Tags
If yes, then tags will be exported to Boxalino
Product Image Sizes
Eg: standard:300x300
Product Thumbnail SIzes
Eg: standard:70x70,list:235x135r-90
Additional Attributes (optional)
Attributes which should be exported with normal attributes (coma-separated list)
Maximum amount of product to export
Maximum amount of products to export to Boxalino. (0 = all)

All fields (unless otherwise stated) are mandatory. Some fields have default parameter.
