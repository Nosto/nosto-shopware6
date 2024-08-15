# 3.3.4

* Fix: In Stock items would not show for SKU's.
* Feature: In rare cases where Nosto Search API is not responsive we will fall back to your native Shopware Search settings.

# 3.3.3
* Feature: Added a configuration option to determine if data for abandoned carts should be stored in the relevant table.
* Fix: The pagination disappeared when the sort order has been changed
* Change: Improved German Translations for the Nosto Job-Scheduler

# 3.3.2
* Fix: Fixed an issue where CM2 sorting was not applied correctly due to changes in the search query handling.

# 3.3.1
* Fix: Improving the performance of the data stored for abandoned carts
* Fix: Now showing pagination on sort order change using Category Merchandising
  
# 3.3.0
* New: Added new scheduled clean up job for removing old Nosto cart data
* Fix: Search and Category Merchandising interference with other plugins on a search page
* Fix: Not recognising categories in tagging
  
# 3.2.0
* New: Add parent categories for Category Merchandising 2 to the product sync

# 3.1.2
* Fix: Search and Category Merchandising 2 product sorting by a product number 

# 3.1.1
* Fix: Search not working when configuring the product number as Nosto identifier in the plugin configuration
* Fix: Product sync errors for products on clearance
* Fix: Issue with the "Main Product" storefront configuration for products with variants

# 3.1.0
* New: Added support for more storefront configuration options

# 3.0.0
* Be aware, that this version only supports the shop versions starting from v6.5.4
* Feature: Support for native Nosto Search and Category Merchandising 2
* Feature: Possibility to add language specific plugin configuration
* Feature: Configuration to exclude products within specific categories
* Feature: The product sync now considers the storefront presentation of each product
* Change: Changed to an OpenSource license

# 2.5.1
* Fix: Fixed few wording/typos
* Fix: Fixed an issue that some customer may encounter after changing store language.
* Fix: Full catalog sync and scheduled sync now may utilize more than one worker.
* Fix: Fixed an issue where brand image can be removed by Nosto crawler ( but was added via api/sync ).
* Fix: Fixed an issue where product images were not matching the order in which they are in Shopware.

# 2.5.0
* New: Added config option in plugin configuration page that now allows to specify in days for how long to store old processed scheduled jobs ( Nosto plugin schuled jobs ).
* Fix: Fixed an issue with variants product output in product recommendations vs. merchandising
* Fix: Improved performance of Full catalog sync operation ( "Full Catalog Sync" button in Nosto Grid page at adminpanel ). This should resolve the issue for customers who have large amounts of products on their website and are having issue with "not all product appear in Nosto Admin panel".

# 2.4.3
* Fix: Fixed an issue where newly added products ( to dynamic groups or manual ) were not shown at storefront.
* Fix: Product manufacturer data that is sent to Nosto will now include a "brand-image-url" variable that can be used in Nosto templates if the image is available.

# 2.4.2
* Fix: Removed div class nosto-integration-block wrapping the nosto elements

# 2.4.1
* Fix: Fixed the issue where analytics totals data was tracked worngly in Nosto Dashboard 
* Fix: Fixed an issue which caused Nosto crawler to discontinue products sporadically 

# 2.4.0
* Feature: Added feature support for "Hide Products After Clearance"
* Fix: Fixed issue where products were in stock on-site but OOS in Nosto

# 2.3.1

* Fix: Added logic to "Full Catalog Sync" that will resolve variant product issues ( it will discountinue products based on store-front representaion of variant product configuration )
* Fix: An issue when some products became discontinued in Nosto Merchandising and Catalog.
* Fix: The products are not switched after changing the positions of the products
* Fix: The issue when product tags/custom fields can't be synced

# 2.3.0

* Fix: Resolved the issue with assigning a dynamic group of merchandising products to categories.
* Feature: Added functionality for accounting categories with dynamic product groups.
* Feature: Added a new GraphQL API to collect the list of categories in order to better support our Category Merchandising product.

# 2.2.1

* Fix: Resolved the issue that some customers may have upon nosto recommendation/merchandising page crashing if the product identifier was set to "Product Number".

# 2.2.0

* Feature: The Nosto cookies are 1st Party Cookies. The plugin sets the cookies as essential and always loads them instead of rating is as something and loading them optionally after the user's selection

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
