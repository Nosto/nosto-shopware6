# Table of contents

1. [Personalization for Shopware](#personalization)
    1. [Getting started](#getting-started)
        1. [How it works](#how-it-works)
2. [Installation](#installation)
    1. [Community](#installation-store)
    2. [Zip archive](#installation-zip)
3. [Configuration](#configuration)
    1. [Account Settings Overview](#configuration-account-settings)
    2. [General Settings Overview](#configuration-general-settings)
    3. [Tags Assignment Overview](#configuration-tag-assignment)
    4. [Feature Flags Overview](#configuration-features-flags)
4. [Uninstallation](#uninstallation)
5. [Nosto Plugin Job Scheduling](#job-scheduling)
   1. [Features of Job Scheduling Dashboard](#job-scheduling-features)
   2. [Views of Job Scheduling Dashboard](#job-scheduling-view)
      1. [Listing View](#job-scheduling-view-listing)
      2. [Grouped View](#job-scheduling-view-group)
      3. [Chart View](#job-scheduling-view-chart)
   3. [Auto Load](#job-scheduling-auto-load)
6. [Dependencies](#dependencies)

###Listing View <a name="job-scheduling-view-listing"></a>
###Grouped view <a name="job-scheduling-view-group"></a>
### Chart view <a name="job-scheduling-view-chart"></a>

# Personalization for Shopware

Increase your conversion rate and average order value by delivering your customers personalized product recommendations
throughout their shopping journey.

Nosto allows you to deliver every customer a personalized shopping experience through recommendations based on their
unique user behavior - increasing conversion, average order value and customer retention as a result.

[https://nosto.com](https://nosto.com/)

## Getting started <a name="getting-started"></a>

### How it works <a name="how-it-works"></a>

The plugin adds new block category called Nosto Components and a block called Nosto in Shopping Experiences. This
element requires element id which can be found in Placements of Campaigns section of your Nosto Admin. The element can
be put in any CMS-page of you shop and the plugin automatically adds product recommendation elements to the
corresponding location when it's configured and active for the sales channel. Basically, cms-element is an empty "div"
placeholder element and this "div" are automatically populated with product recommendations from your shop.

This is possible by mining data from the shop when the user visits the pages. For example, when the user is browsing a
product page, the product information is asynchronously sent to Nosto, that in turn delivers product recommendations
based on that product to the shop and displays them to the user.

The more users that are visiting the site, and the more page views they create, the better and more accurate the
recommendations become.

In addition to the recommendation elements and the real time data gathering, the plugin also includes some behind the
scenes features for keeping the product information up to date and keeping track of orders in the shop.

Every time a product is updated in the shop, e.g. the price is changed, the information is sent to Nosto over an API.
This will sync the data across all the users visiting the shop that will see up-to-date recommendations.

All orders that are placed in the shop are also sent to Nosto. This is done to keep track of the orders that were a
direct result of the product recommendations, i.e. when a user clicks a product in the recommendation, adds it to the
shopping cart and places the order.

Nosto also keeps track of the order statuses, i.e. when an order is changed to
"payed" or "canceled" the order is updated over an API and newsletter subscribers.

All you need to take Nosto into use in your shop, is to create a Nosto account for your shop, install and configure the
plugin in you shop. This is as easy as clicking a button, so read on.

# Installation <a name="installation"></a>

Plugin can be installed in such ways:

1. Community store (preferred)
2. Zip archive

Also, the plugin has the embedded dependency of Overdose Job Scheduler. It's delivered with plugin sources.

## Community (preferred) <a name="installation-store"></a>

The plugin can be automatically downloaded and installed from within Shopware admin My Extensions section, if you have
connected your Shopware account to the installation. The plugin is found under the Customer account + Personalization
section in My Extensions, or by searching for "nosto". If you can't find it, you can also manually download it from
the [Community store](https://store.shopware.com/). Once you've found the plugin, simply click Download now button on
the plugin page and follow the instructions to activate the plugin.

## Zip archive <a name="installation-zip"></a>

The plugin can also be installed by uploading zip archive in Shopware admin My Extensions section. The plugin archive
can be downloaded only in [Community store](https://store.shopware.com/)

After this, the plugin can be activated in Shopware admin My Extensions section.

# Configuration <a name="configuration"></a>

The Nosto plugin has a separate settings page.

Settings → Extensions → Nosto

## Account Settings Overview <a name="configuration-account-settings"></a>

There are basic configuration fields and control buttons are located in plugin configuration page marked with digits:

**Note!** Plugin requires to setup global Nosto account credentials. If you need to disable plugin functionality on
specific sales channel, you can disable account on specific channel via toggle off “Enable Account“ switch field.

![Account Settings](images/account-settings.png?raw=true)

1. Field which indicates is configured account is enabled for merchandising/product sync.
2. Api validation button, which will validate the tokens mentioned below and the result will be shown in the
   notification windows.
3. Required Field with account id. It can be retrieved in Nosto account (in account settings), additional guides can be
   found [here](https://help.nosto.com/en/articles/613483-settings-account-settings).
4. Required Field with account name. It can be retrieved in Nosto account (in account settings), additional guides can
   be found [here](https://help.nosto.com/en/articles/613483-settings-account-settings).
5. Required Field with Product Token API key (API_PRODUCTS). It used to synchronize products between Shopware and Nosto
   . The key must be requested from Nosto Technical Support, after which it will appear in authentication tokens section
   in the admin, additional guides can be
   found [here](https://help.nosto.com/en/articles/613616-settings-authentication-tokens).
6. Required Field with Email Token API key, (API_EMAIL). It used to synchronize emails between Shopware and Nosto . The
   key must be requested from Nosto Technical Support, after which it will appear in authentication tokens section in
   the admin, additional guides can be
   found [here](https://help.nosto.com/en/articles/613616-settings-authentication-tokens).
7. Required Field with GraphQL Token API key, (API_APPS). It used to synchronize orders, recommendations, segments,
   category merchandizing products between Shopware and Nosto . The key must be requested from Nosto Technical Support,
   after which it will appear in authentication tokens section in the admin, additional guides can be
   found [here](https://help.nosto.com/en/articles/613616-settings-authentication-tokens).

## General Settings Overview <a name="configuration-general-settings"></a>

![General Settings](images/general-settings.png?raw=true)

1. By enabling this setting, Nosto tracking JS scripts will be initialized and loaded directly after guest’s very first
   interaction with storefront page. It can be used for prevent storefront performance issues during page loading.
2. By enabling this setting, Nosto Merchandising feature will be activated. Please make sure you have setup all
   necessary product merchandising rules before enabling this feature.
   **Note!** To prevent empty PLP displaying because of network issues or Nosto API unavailability, plugin will follow
   the fallback to native Shopware 6 search engine.
3. By enabling this setting, Nosto will cache the product and category pages for not logged in users.

## Tags Assignment Overview <a name="configuration-tag-assignment"></a>

![Tag Assignment](images/tag-settings.png?raw=true)

All fields displayed in the “Tags assignment“ card are used to transfer product’s custom fields values to the associated
Nosto product entity.
<br>
![Nosto product entity.](images/tag-nosto.png?raw=true)

## Features Flags Overview <a name="configuration-features-flags"></a>

![Feature Flags](images/feature-settings.png?raw=true)

This configuration card contains multiple feature toggles which enable/disable what information to send to Nosto with
product data. Also, there is possibility to enabling/disable ratings and reviews. Nosto supports tagging the rating and
review metadata. The rating value and review count metadata can be used for creating advanced recommendation rules to
promote products that are well reviewed.

# Uninstallation <a name="uninstallation"></a>

The plugin can be uninstalled from within Shopware admin My Extensions section, with standard shopware flow. More
information can be found [here](https://docs.shopware.com/en/shopware-6-en/extensions/myextensions)

# Nosto Plugin Job Scheduling <a name="job-scheduling"></a>

Once the plugin is installed and activated, in Shopware 6 administration you should be able to see the menu item under
the Marketing tab which will take us to the Nosto plugin dashboard. Marketing → Nosto Jobs Listing
![Job Navigation](images/job-navigation.png?raw=true)

## Features of Job Scheduling Dashboard <a name="job-scheduling-features"></a>

Once you are on the Nosto job listing page, you should be able to see the scheduled jobs list.

![Job Navigation Main](images/job-scheduler-main.png?raw=true)

On the job listing page we are able to reach out to the complete job information. After plugin installation all products
can be synced with Nosto via scheduling associated jobs by clicking over the control button “Schedule Full Product
Sync”.

There are 7 columns here with the proper information about the current job.

| Column name                         | Information                                                                                                                                                                                                                                                                                                                                                                                                                                                                    | Screenshot                                              | 
|-------------------------------------|--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|---------------------------------------------------------|
| Name                                | Job Name                                                                                                                                                                                                                                                                                                                                                                                                                                                                       | ![Job Name](images/job-scheduler-name.png?raw=true)     |
| Status                              | There are 4 type of statuses: Success, Failed, Running, Pending                                                                                                                                                                                                                                                                                                                                                                                                                | ![Job Status](images/job-scheduler-status.png?raw=true) |
| Started At, Created At, Finished At | Job’s creation, starting and finishing dates.                                                                                                                                                                                                                                                                                                                                                                                                                                  | ![Job Date](images/job-scheduler-date.png?raw=true)     |
| Child jobs                          | In this column we have 3 types of dot badges: <br> **Green** dot badge indicates to us how many successful sub jobs our current job has.<br>**Gray** dot badge indicates to us how many pending sub jobs our current job has. <br>**Red** dot badge indicates to us how many failed sub jobs our current job has.<br>By clicking on the corresponding row of the current job in the sub jobs column pop-up will open with the detailed listed view of the sub jobs of the current job. | ![Child Job](images/job-scheduler-child.gif?raw=true)   |
| Messages                            | In the messages column we can see the colored icons and the numbers in front of them. <br> **Blue**: Indicates to us the quantity of the INFO type messages.<br>**Yellow**: Indicates to us the quantity of the WARNING type messages.<br>**Red**: Indicates to us the quantity of the ERROR type messages.<br>By clicking on the corresponding row of the current job in the messages column the pop-up will open with the messages of the current job.                                   | ![Messages](images/job-scheduler-messages.gif?raw=true) |

## Views of Job Scheduling Dashboard <a name="job-scheduling-view"></a>
There are 3 different type of dashboard view in Nosto plugin.
View modes can be switched from the dashboard action bar on top of the job listing.

![Switch View](images/job-scheduler-switch-view.png?raw=true)

### Listing View <a name="job-scheduling-view-listing"></a>

List view is the default view of the dashboard with filtering support.
![Listing View](images/job-scheduler-listing-view.png?raw=true)

### Grouped view <a name="job-scheduling-view-group"></a>

![Grouped View](images/job-scheduler-grouped-view.gif?raw=true)
The grouped view has 2 types of grouping itself.
1. Group by status
2. Group by Job type.

Grouping types can be switched from the action bar at the top.

![Grouped View](images/job-scheduler-group-change.png?raw=true)

### Chart view <a name="job-scheduling-view-chart"></a>

Charts view allows us to group the jobs by **type** or by **status** and show them divided by dates.
At the top of the charts bar we have the dropdown selection where we can select the date range:
**30 Days.<br>
14 Days.<br>
7 Days.<br>
Last 24 hours.<br>
Yesterday.**

By clicking on the colored dot badge we can hide/show the chart line and info corresponding to the badge color and the
type/status in front of it.
![Chart View](images/job-scheduler-chart.gif?raw=true)

At the bottom of the charts there are colored dot badges with the chart line name (corresponding to the chart grouping mode status/type).

## Auto Load <a name="job-scheduling-auto-load"></a>

In the **Actions** at the top of the Nosto dashboard there is a switch field named **Auto Load**.

![Autoload](images/job-scheduler-autoload.png?raw=true)

Job Listing page has auto-reload feature so you don't even need to reload whole page to check job’s execution statuses. 
Listing data refreshes automatically every 1 minute.

Listing page contains all Nosto plugin jobs:
1. **Changelog Entity Sync Operation** - parent backlog events processing operation over the child's - **Marketing Permission Sync Operation** (newsletter), **Order Sync Operation** (New Order, Updated Order events), and **Product Sync Operation**.
2. **Full Catalog Sync Operation** - synchronize products - parent of the **Product Sync Operation**.

# Dependencies <a name="dependencies"></a>
* Overdose Job Scheduler which is included in the plugin sources
