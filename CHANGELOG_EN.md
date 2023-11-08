# 2.1.0

* Added: Added id of each of the product's categories to a product data object before sending it to Nosto service.
* Fix: Resolved the issue when "Enable Variations" toggle was not affecting product data structure that was being sent to Nosto service.

# 2.0.2

* Fix: Resolved the issue when Recommendation filter was not working as intended for some users.
* Fix: Resolved the issue when nosto widgets could cause error on the page where they were added.

# 2.0.1

* Fix: Resolved the issue with configuration namings.

# 2.0.0

* Compatibility release with shopwrae 6.5^
* Fix: Replaced usage of removed classes & files.
* Fix: Resolved the issue that some plugin users was able to encounter during data syncronization via shopware admin panel.
* Fix: Minor changes to extension configuration classes/templates ( at extension configuration page ).
* New: Job Scheduler Update - implemented compatibility with Shopawre 6.5^ versions.
* New: Job Scheduler Update - Job scheduler handlers now do extend recommended interfaces.
* New: Controller routes now have annotation declaration in new format.
* New: Some changes that was made do make the extension backward-incompatible. You can see the dependencies in composer.json file.

# 1.0.18

* Fixed the bug with added criteria to Nosto sorting method

# 1.0.17

* Minor bugfixes: Fixed issue when site visitor can encounter an error on storefront after reaching checkout and comming back to previous page.

# 1.0.16

* Added "restore cart"/"abandoned cart" functionality support. Now Nosto service will receive "restore_cart" link alongside all other card data.

# 1.0.15

* Fix: Fixed issue that some users can encounter upon "Full product sync". Error message: Countable|array int provided
* Fix: Duplicate text for nosto config option tooltip description ( adminpanel )

# 1.0.14

* Fix: Added Nosto product identifier selection
* Fix: Added all information related to cross-selling 

# 1.0.13

* Fix: Fixed ProductCloseoutFilter loading process for older versions
* Fix: Removed product main variant config loader for older version

# 1.0.12

* New: Added main product information

# 1.0.11

* Fix: Fixed tag loading limitation issue

# 1.0.10

* Fix: Added tag selection of tag values instead of custom fields

# 1.0.9

* New: Added product labelling to the custom fields of Nosto Product
* New: Added product number to the custom fields of Nosto Product

# 1.0.8

* New: Added Nosto js object on CMS pages with addSkuToCart, addProductToCart, addMultipleProductsToCart methods

# 1.0.7

* New: Added Cross-Selling synchronization
* Fix: Fixed gross price calculation for Nosto product

# 1.0.6

* New: Added inventory selection in Nosto configuration
* Fix: Fixed Nosto js issue on checkout page

# 1.0.5

* New: Added Recommended sorting option for merchandising
* Fix: Fixed Nosto configuration saving and validation

# 1.0.4

* New: Added compatibility with custom product pages
* New: Added compatibility with non-scalar custom fields
* New: Added domain selection for multi-domain shops
* Fix: Fixed Category Merchandiser account issue

# 1.0.3

* Fix: Fixed Category Merchandiser

# 1.0.2

* New: Added custom theme compatibility
* New: Required fields are marked not required if the account is not enabled
* Fix: Context is kept for background processes
* Fix: Removed all data during uninstall process
* Fix: Fixed server side generated cookies related to the permissions
* Fix: Handled empty category, product image and product url cases
* Fix: CSS removed "important" keywords
* Fix: Fixed UI for Nosto CMS-element

# 1.0.1

* New: Added api key validation in Nosto config
* New: Added cookie permissions for Nosto tracking
* New: Added compatibility with the latest versions of shopware
* New: Added translations for whole module
* Fix: Changed custom fields key to label in Nosto config

# 1.0.0

* Basic plugin functionality implementation.
