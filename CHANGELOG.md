# changelog of the boxalino magento plugin

## v2.10 refactoring and bugfixes

* Moved plugin from local to community pool to support custom overrides.
* Adding support for URL export in Magento EE.
* Improving handling of missing/empty images in the autocompletion.
* Refactoring and improving facet block.
* Exclude catalog only items in the search.
* Fixing regressions in the customer synchronization.
* Correcting order total calculation in tracking.

## v2.9 supporting configurable customer attributes

* Added support to configure additional customer attributes in export.

## v2.8 supporting more complex shop structures

* Added support for structures where store views map to languages in different 
  search indexes within one website.
* Improved handling of empty deltas and permission problems when creating ZIPs.

## v2.7 generic recommendation block and datetime properties

* Added generic p13n recommendation block that can be added to any page.
* Added support for datetime properties.
* The special\_price now is exported according to the special\_from\_date and 
  special\_to\_date properties. These two properties are now exported by default.

## v2.6 improving autocompletion

Making autocompletion more configurable and supporting more autocompletion features.

## v2.5 improving filtering and facet support

Introducing new options to configure the filtering and facets in the magento backend.

## v2.4 Magento EE support

Added support for the Magento Enterprise Edition.

## v2.3 improving compatibility and other bugfixes

Fixing special cases in the indexer and improving compatibility, i.e. for magento 1.7.

## v2.2 indexer refactoring

Refactored the data indexer to use significantly less memory.

## v2.1 stability and feature release

Various bugfixes and re-added features from the previous plugin.

## v2.0 first release of the new API based plugin

This new plugin uses the boxalino p13n thrift API instead of the CEM Frontend one.
