# 6.1.28
* Fix: Ensure queued variant deletes use complete product-number mappings during product sync 
* Fix: Use sales channel and language context when resolving first-available variant sync config

# 6.1.27
* Fix: Updated the cookie consent migrations to support MariaDB
* Fix: Resolved failure during plugin update due to a service error while applying the product identifier default

# 6.1.26
* Fix: Make product number default instead of ID
* Fix: Optimized how the scheduler registers on the message bus to avoid memory exhaustion during plugin activation
* Fix: "enableCache" setting configurable per sales channel and language
* New: Respect cookie consent — "Send Customer Data" mode (rely on cookie / always / never), doNotTrack when unconsented, and backend sync honors it

# 6.1.25
* Fix: Order sync routing for merchants with multiple sales channels using the same language
* Fix: Search/listing fallback handling
* Fix: Made "CachedCategoryRoute" decoration optional when the service is unavailable

# 6.1.24
* Fix: Improved scheduler loading to avoid memory issues during plugin activation and cache rebuild

# 6.1.23
* Fix: Only export categories if url and urlPath are present

# 6.1.22
* Fix: Updated the administration icon

# 6.1.21
* New: Added support for filter value frequencies and media

# 6.1.20
* New: Improved search analytics by sending the search type reason with requests
* Fix: Improvement for review counts

# 6.1.19
* Fix: (Experimental) Update Nosto Documentation MCP tool

# 6.1.18
* Fix: Use the correct currency for product sync when multi-currency is disabled
* Fix: Respect sales channel visibility so only assigned products are synced to Nosto
* Fix: Avoid errors when copying Shopware context or criteria objects with non-serializable values

# 6.1.17
* New: (Experimental) Exposing Nosto Documentation MCP tool

# 6.1.16
* Fix: Fixed an issue with syncing inactive products
* Fix: Fixed an issue with disabling filter values

# 6.1.15
* Fix: Prevent migration failures when expected tables or columns are missing
* Fix: Improve Job Scheduler migration handling to avoid crashes due to schema inconsistencies

# 6.1.14
* Fix: Add safeguards for config table access during uninstall

# 6.1.13
* Fix: Migration fixes

# 6.1.12
* Fix: Monitoring/Debug page fixes

# 6.1.11
* Fix: Nosto settings inheritance adjustments

# 6.1.10
* Fix: Fixes for inheritance

# 6.1.9
* Fix: Added autowiring support for the search controller
* New: Improved search analytics by sending the search type with requests

# 6.1.8
* Fix: Update missing pageFull() in CMS decorator

# 6.1.7
* Fix: Improve product sync performance
* New: Add multi-currency support
* Fix: Product listing pagination placement
* Fix: Price calculations when taxes are not present

# 6.1.6
* Fix: Optimize job listing page
* Fix: Run dependency migrations on plugin install/update

# 6.1.5
* Fix: Preserve post filters

# 6.1.4
* Fix: Revert handle external filters in product listing and search routes

# 6.1.3
* Fix: Updated GitHub Actions release workflow. No runtime or behavior changes.

# 6.1.2
* Fix: Handle fallback to first available variant when main product is not set
* Fix: Handle external filters in product listing and search routes
* Fix: Improve product sync performance and error handling
* Fix: Update compiled assets

# 6.1.1
* Fix: Add duplicate check before inserting products into changelog
* Fix: Dispatch missing ProductListingResultEvent in ProductSearchRoute
* Fix: Extra logging for product sync requests

# 6.1.0
* Fix: Keep filter cookies smaller by storing their data in cache
* New: Split product sync per sales channel so multiple consumers can run in parallel faster, with additional caching for reused data
* New: Add Nosto criteria titles for product synchronization queries to improve debugging
* Fix: Stop loading child product data during product sync twice
* Fix: Additional logging added for product sync flow under the feature flag
* Fix: Job Scheduler Update - Improved error handling in JobExecutionHandler and Introduced job existence checks

# 6.0.15
* Fix: support SearchPageLoader injection in Shopware version 6.7.2.0

# 6.0.14
* New: Ability to set default image url, will be used if image is not set for the product
* Fix: Do not send no result analytics for search if redirect is enabled
* Fix: Adding missing custom fields to product tagging
* Fix: Monitor cookie creation to initialise Nosto

# 6.0.13
* Fix: "searchToken" error updated
* New: New fields added to nosto product to send price per unit to Nosto
* Fix: Preventing undefined errors for A/B Testings
* Fix: Pagination
* Fix: "ghost" variant for sw-button-process

# 6.0.12
* Fix: Order tracking improvements, for Search and CM2 Analytics

# 6.0.11
* Fix: Refactor initTags to reuse loaded tags instead of tag field keys for value retrieval

# 6.0.10
* New: Check inventory quantity for selection of the cheapest product

# 6.0.9
* Fix: Make sync batch size configurable
* Fix: Get tags by ids directly from db

# 6.0.8
* Fix: Handle cache in navigation pages only if Category Merchandising is enabled

# 6.0.7
* Fix: Missing/inconsistent Search and Category Filters
* Fix: Excessive logging
* Fix: Product tagging for products without variants

# 6.0.6
* Fix: nostoCookieFilter causing server errors and exceeding cookie length limit
* Fix: Update nosto filters on CM2

# 6.0.5
* Fix: Add missing translation for customFields array
* Fix: Pass Associations to options.group
* Fix: Fix sync issues for FTP
* Fix: Update searchAnalytics to use new Graphql mutation and add AB testing tracking
* Fix: Add ability to cancel a job from the UI

# 6.0.3
* Fix: Add translate="no" for tagging
* Fix: nostoCookieFilter causing server errors and exceeding cookie length limit
* Fix: Prevent trigger of search fallback mechanism when Nosto returns 0 products
* Fix: Conflict with TrustedShops plugin due to missing null-check
* Fix: UTM tag only applied on page refresh (search)
* Fix: Nosto Job Listing – long loading times
* New: Monitoring/Debug page improvements

# 6.0.2
* Fix: Null value in category tree
* Fix: Nosto Scheduler job failed or already running
* Fix: Composer install requires manual backend build
* Fix: Stuck product sync
* New: Search/CM preview with nostodebug
* New: Export search keywords from SW to Nosto

# 6.0.1
* New: Sending additional data for products and variants to Nosto
* New: Migration from Vuex to Pinia
* Fix: Product tagging fix for crawler and Nosto Personalisation
* Fix: Improve information and status for sync jobs
* Fix: Add domain URL validation and update label and help text
* Fix: Reduced redundant search calls by fetching filters once

# 6.0.0
* New: Shopware 6.7 Compatability
* New: Ability to disable and adjust caching for Search and Category Pages, Improving analytics and Nosto Personalisation
* New: Ability to open Product Details page if only 1 search result is given
* New: Ability to use Shopware visibility to filter search/category merchandising products (Make sure full product sync is done beforehand)
* Fix: Fixed an issue with Nosto responses including data and error to just fail
* Fix: Category merchandising using SEO category names
* Fix: Use default category name if translation is not provided
* Fix: Search issues if elastic search is used by default
* Fix: Send Search analytics if no result was given
* Fix: Removal of Feature flag to send search analytics (analytics are always sent)

# 5.1.4
* New: Synchronization for category urls, and improved category tagging


# 5.1.3
* Fix: Fixed an issue where two redundant options were shown Recommendation and Top Results for search/category sort
* Fix: Fixed an issue with Search errors when cookie consent is not accepted
* Fix: Enhanced error handling for product synchronisation

# 5.1.2
* Fix: Fixed an issue where the Nosto analytics tracking route name was incorrect, causing failed requests.

# 5.1.1
* Fix: Fixed an issue where Nosto has been loaded on the PLP, although the configuration was disabled
* New: We added a cookie check for the Search Analytics
* New: A new API on the plugin is available to support Multi Currency from Shopware
* Fix: Fixed an issue with our fallback mechanism for the plugin versions >= 5.0.0.

# 5.1.0
* New: Synchronization for new custom fields in Nosto is now supported (release-date, mfg-part-number, gtin-ean)
* Bug: Search Analytics - we fixed a bug where the click event got not triggered on product links
* Bug: A bug has been fixed which introduced redundant sort options
* New: Introduced support for Shopware’s default search sorting
* Fix: Resolved an issue affecting filter styles on category pages caused from the plugin
* Fix: Resolved an issue where error messages in logs were unclear, improving log readability and debugging.
* Fix: Resolved incorrect product mapping in Shopware orders by ensuring main product IDs are correctly assigned while preserving variant relationships
* Change: Refined tagging and order tracking logic, aligning with product creation behavior, and adjustments for storefront presentation
* Change: Updated the handling of selected variants in storefront presentation to ensure stock status, activity, and clearance sale settings are correctly synchronized with the Nosto product catalog.
* Change: Adjusted the Nosto synchronization logic to align with Shopware storefront behavior for the "Display single product (Main product)" option
* Change: Adjusted the Nosto synchronization logic to align with Shopware storefront behavior for the "Expand property values in product listings (No property selected)" option

# 5.0.2
* Fix: Fixed product click tracking to use the correct route and attributes for accurate analytics

# 5.0.1
* Fix: We added a Timeout for Category Merchandising and a user agent to be passed for search analytics

# 5.0.0
* Feature: Search & Category analytics are now available.
* New: It is now possible to send multiple property values instead of the last value
* New: Added X-Nosto-Integration header with plugin version in search requests
* New: We added a fallback Mechanism for Search and Category Page in case the service is not available
* Fix: Fixed to send orders correctly to Nosto and how they are reflecting in the performance metrics
* Fix: Inactive products where not synced to Nosto
* Fix: "Call to a member function getId() on null" during product sync has been fixed
* Fix: Fatal Error when adding and removing Price Rule due to product discontinued
* Fix: Fixed an issue where StockFieldOptions::tryForm() are called with "null" argument in the product synchronization
* Fix: An issue with Search Result Page and Autocomplete having different results has been resolved

# 4.2.9
* Fix: A bug related to incorrect productID in cart tagging and cart events has been resolved.
* New: Sync first available variant as a product, if product is on clearance and out of stock
* Fix: Fixed a null reference error in the Nosto Integration plugin by ensuring proper handling of null SalesChannelProductEntity objects.

# 4.2.8
* Fix: Fixed Product Tagging errors
* Fix: Changelog scheduled updates not triggering

# 4.2.7
* Fix: Fixed an Issue where the Cookie-Error was shown in the Console
* Fix: Aligned Filter Styling for Search and Category PLP's
* Fix: Fixed an issue where Sales has not been tracked correctly in the Nosto Backend
* Change: Categories without Products show a more accurate message for the User.
* Fix: Fixed an issue with displaying the number of results from a search

# 4.2.6
* Feature: Adding possibility to disable cookie consent requirement
* Fix: Resolved issue where pagination disappeared when the sort order has been changed
* Fix: Increased timeout when sending product data to Nosto to have less false positive sync issues

# 4.2.5
* Fix: Include product number in the order tagging to account for setups where the product number is being used as the product identifier instead of the product ID.
* Fix: Add a warning when the nosto identifier is changed
* Fix: Refactor product builder when building product variations

# 4.2.4
* Fix: In Stock items would not show for SKU's
* Feature: In rare cases where Nosto Search API is not responsive we will fall back to your native Shopware Search settings.

# 4.2.3
* Feature: Added a configuration option to determine if data for abandoned carts should be stored in the relevant table.
* Fix: The pagination disappeared when the sort order has been changed
* Change: Improved German Translations for the Nosto Job-Scheduler

# 4.2.2
* Fix: Fixed an issue where CM2 sorting was not applied correctly due to changes in the search query handling.

# 4.2.1
* Fix: Improving the performance of the data stored for abandoned carts
* Fix: Now showing pagination on sort order change using Category Merchandising

# 4.2.0
* New: Added new scheduled clean up job for removing old Nosto cart data
* Fix: Search and Category Merchandising interference with other plugins on a search page
* Fix: Not recognising categories in tagging

# 4.1.0
* New: Add parent categories for Category Merchandising 2 to the product sync

# 4.0.0
* Be aware, that this version only supports the shop versions starting from v6.6.0
* Fix: Replaced usage of removed classes & files.
* New: Controller routes now use attribute declaration.
* Change: Extension configuration upgraded to Vue3

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
