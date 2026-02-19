# Nosto Shopware 6 Plugin - Architecture Guide

This document provides a comprehensive overview of the Nosto Shopware 6 plugin, which synchronizes product data, orders, and categories to the Nosto personalization platform.

---

## Table of Contents

1. [Overview](#overview)
2. [Plugin Structure](#plugin-structure)
3. [Product Synchronization](#product-synchronization)
4. [Order Synchronization](#order-synchronization)
5. [Category Synchronization](#category-synchronization)
6. [Configuration System](#configuration-system)
7. [Event System](#event-system)
8. [Scheduled Tasks](#scheduled-tasks)
9. [Search Integration](#search-integration)
10. [Key Services & Components](#key-services--components)
11. [Data Flow](#data-flow)
12. [API Endpoints](#api-endpoints)
13. [Database Entities](#database-entities)
14. [Performance Optimizations](#performance-optimizations)

---

## Overview

| Property | Value |
|----------|-------|
| **Package** | `nosto/nosto-integration` |
| **Version** | 3.6.4 |
| **Namespace** | `Nosto\NostoIntegration\` |
| **PHP Requirement** | 8.1+ |
| **Key Dependencies** | nosto/php-sdk (7.7.1), nosto/shopware6-job-scheduler (2.0.10) |

The plugin provides:
- **Product catalog sync** - Full and incremental product synchronization
- **Order sync** - New orders and order status updates
- **Category sync** - Category hierarchy synchronization
- **Search integration** - Nosto-powered search and navigation
- **Abandoned cart recovery** - Cart restoration for recovery campaigns

---

## Plugin Structure

```
src/
├── Api/
│   ├── Controller/          # REST API controllers
│   └── Route/               # API route definitions
├── Async/                   # Async message classes & event writers
├── Controller/Storefront/   # Storefront controllers (cart, etc.)
├── Decorator/               # Service decorators (search, cookies, etc.)
├── Entity/                  # Custom entity definitions
├── Enums/                   # Configuration enums
├── EventListener/           # Event listeners for data changes
├── Migration/               # Database migrations
├── Model/
│   ├── Config/              # Configuration management
│   ├── Nosto/               # Domain model builders
│   │   ├── Product/         # Product → Nosto transformation
│   │   ├── Order/           # Order → Nosto transformation
│   │   └── Category/        # Category → Nosto transformation
│   └── Operation/           # Sync operation handlers
├── Search/                  # Nosto search integration
├── Service/                 # Business services
├── Subscriber/              # Event subscribers
├── Twig/                    # Twig template extensions
└── Utils/                   # Utilities & helpers
```

---

## Product Synchronization

### Full Catalog Sync

Triggered manually via admin or scheduled daily.

```
Controller: NostoController::fullCatalogSyncAction()
    ↓
Route: NostoSyncRoute::fullCatalogSync()
    ↓
Message: FullCatalogSyncMessage
    ↓
Handler: FullCatalogSyncHandler
    ↓
Child Jobs: ProductSyncMessage (batched), CategorySyncMessage
```

### Incremental Product Sync

Triggered automatically when products are modified.

```
Event: ProductWrittenEvent / EntityDeleteEvent
    ↓
Listener: ProductWrittenDeletedEvent
    ↓
Changelog: nosto_integration_entity_changelog
    ↓
Handler: EntityChangelogSyncHandler (every 10 min)
    ↓
Message: ProductSyncMessage
```

### Product Builder (`Model/Nosto/Product/Builder.php`)

Transforms Shopware products to Nosto format:

| Shopware Data | Nosto Field | Notes |
|---------------|-------------|-------|
| Product ID/Number | `productId` | Configurable identifier |
| Name | `name` | |
| Price | `price`, `listPrice` | Currency-aware |
| Stock | `availability` | Configurable stock field |
| Categories | `categories` | Full path hierarchy |
| Tags | `tag1`, `tag2` | Configurable tag sources |
| Custom fields | `customFields` | Selectable fields |
| Images | `imageUrl`, `alternateImageUrls` | |
| Variants | `skus` | Via SkuBuilder |
| Cross-selling | Related products | Configurable sync mode |
| Ratings | `ratingValue`, `reviewCount` | Shopware or Nosto source |

**Key Events:**
- `NostoProductBuiltEvent` - After product transformation
- `BeforeUpsertProductsEvent` - Before sending to Nosto API

---

## Order Synchronization

### Order Capture Flow

```
Event: CheckoutOrderPlacedEvent (requires 2c_cId cookie)
    ↓
Listener: OrderWrittenEventListener
    ↓
Changelog: order_placed event
    ↓
Handler: EntityChangelogSyncHandler
    ↓
Message: OrderSyncMessage
    ↓
Handler: OrderSyncHandler
    ↓
SDK: OrderCreate / OrderStatus operations
```

### Order Status Updates

```
Event: state_machine.order.state_changed
    ↓
Listener: OrderWrittenEventListener
    ↓
Changelog: order_updated event
    ↓
Handler: OrderSyncHandler::sendUpdatedOrder()
```

### Order Builder (`Model/Nosto/Order/Builder.php`)

| Shopware Data | Nosto Field |
|---------------|-------------|
| Order number | `orderNumber` |
| Order ID | `externalOrderRef` |
| Created timestamp | `createdAt` |
| Payment method | `paymentProvider` |
| Order state | `orderStatus` |
| Customer | Buyer info (via Buyer\Builder) |
| Line items | Products + shipping (via Item\Builder) |

**Key Events:**
- `NostoOrderBuiltEvent` - After order transformation
- `BeforeOrderCreatedEvent` - Before sending to Nosto API

---

## Category Synchronization

### Category Sync Flow

```
Full Sync: FullCatalogSyncHandler
    ↓
Message: CategorySyncMessage (batched)
    ↓
Handler: CategorySyncHandler
    ↓
Builder: Category\Builder
    ↓
SDK: CategoryUpdate operation
```

### Incremental Category Sync

```
Event: CategoryWrittenEvent
    ↓
Listener: CategoryWrittenEvent (listener class)
    ↓
Changelog: category event
    ↓
Handler: EntityChangelogSyncHandler
```

**Key Event:** `BeforeCategoryUpdateEvent`

---

## Configuration System

### Storage

Configuration is stored in `nosto_integration_config` table with per-sales-channel and per-language granularity.

### Key Configuration Options

**Account Settings:**
| Key | Description |
|-----|-------------|
| `isEnabled` | Enable Nosto for this channel/language |
| `accountID` | Nosto account ID |
| `accountName` | Nosto account name |
| `productToken` | Product API token |
| `emailToken` | Email API token |
| `appToken` | GraphQL/App API token |
| `searchToken` | Search API token |

**Product Sync:**
| Key | Description |
|-----|-------------|
| `productIdentifier` | Use product ID or product number |
| `enableVariations` | Include product variants |
| `productProperties` | Include product properties |
| `alternateImages` | Include alternate images |
| `inventory` | Include inventory levels |
| `syncInactiveProducts` | Sync unpublished products |
| `crossSellingSync` | Cross-selling sync mode (NO_SYNC, SYNC_PRODUCTS, SYNC_PRODUCT_STREAMS) |
| `categoryBlocklist` | Categories to exclude |
| `syncBatchSize` | Batch size (default: 150) |

**Order Sync:**
| Key | Description |
|-----|-------------|
| `customerDataToNosto` | Send customer data |
| `storeAbandonedCartData` | Enable abandoned cart recovery |
| `enableIgnoreCookieConsent` | Ignore cookie consent |

**Search:**
| Key | Description |
|-----|-------------|
| `enableSearch` | Enable Nosto search |
| `enableNavigation` | Enable Nosto navigation |
| `enableCache` | Enable navigation caching |
| `cacheTtl` | Cache TTL in minutes |

**Scheduling:**
| Key | Description |
|-----|-------------|
| `dailySynchronization` | Enable daily product sync |
| `dailySynchronizationTime` | Time for daily sync |
| `oldJobCleanup` | Clean up old jobs |
| `oldJobCleanupPeriod` | Cleanup period (days) |

### Configuration Access

```php
// Via ConfigProvider
$configProvider->isEnabled($salesChannelId, $languageId);
$configProvider->getAccountName($salesChannelId, $languageId);
$configProvider->getSyncBatchSize($salesChannelId, $languageId);
```

---

## Event System

### Event Listeners

| Class | Events | Purpose |
|-------|--------|---------|
| `ProductWrittenDeletedEvent` | `PRODUCT_WRITTEN_EVENT`, `EntityDeleteEvent` | Capture product changes |
| `CategoryWrittenEvent` | `CATEGORY_WRITTEN_EVENT` | Capture category changes |
| `OrderWrittenEventListener` | `CheckoutOrderPlacedEvent`, `state_machine.order.state_changed` | Capture orders |
| `NewsletterEventListener` | Newsletter events | Marketing permissions |
| `FrontendSubscriber` | `HeaderPageletLoadedEvent`, `KernelEvents::RESPONSE` | Frontend integration |

### Plugin Events (for extension)

| Event | When Fired | Use Case |
|-------|------------|----------|
| `NostoProductBuiltEvent` | Product transformed | Modify product data |
| `NostoOrderBuiltEvent` | Order transformed | Modify order data |
| `NostoCategoryBuiltEvent` | Category transformed | Modify category data |
| `BeforeUpsertProductsEvent` | Before product API call | Filter/modify products |
| `BeforeOrderCreatedEvent` | Before order API call | Filter/modify orders |
| `BeforeCategoryUpdateEvent` | Before category API call | Filter/modify categories |

---

## Scheduled Tasks

| Task | Interval | Purpose |
|------|----------|---------|
| `EntityChangelogScheduledTask` | 10 min | Process changelog and trigger syncs |
| `DailyProductSyncScheduledTask` | 5 min (checks time) | Trigger daily full sync at configured time |
| `OldJobCleanupScheduledTask` | Configurable | Clean up old job records |
| `OldNostoDataCleanupScheduledTask` | Configurable | Clean up old Nosto data |

---

## Search Integration

### Architecture

```
Shopware Search Request
    ↓
Decorator: ProductSearchRoute / ProductListingRoute
    ↓
Service: SearchService
    ↓
Handler: SearchRequestHandler / NavigationRequestHandler
    ↓
GraphQL: Nosto Search API
    ↓
Parser: GraphQLResponseParser
    ↓
Shopware Response
```

### Key Components

| Component | Responsibility |
|-----------|----------------|
| `SearchService` | Main search orchestration |
| `SearchRequestHandler` | Product search requests |
| `NavigationRequestHandler` | Category/navigation requests |
| `FilterHandler` | Filter/facet processing |
| `SortingHandlerService` | Sort option handling |
| `PaginationService` | Pagination handling |
| `GraphQLResponseParser` | Parse Nosto responses |

---

## Key Services & Components

### Account Management

| Class | Purpose |
|-------|---------|
| `Account` | Represents a Nosto account |
| `Account\Provider` | Loads enabled accounts per channel/language |
| `Account\KeyChain` | Holds API tokens |

### Operation Handlers

| Handler | Code | Purpose |
|---------|------|---------|
| `FullCatalogSyncHandler` | `nosto-integration-full-catalog-sync` | Orchestrate full sync |
| `ProductSyncHandler` | `nosto-integration-product-sync` | Sync product batches |
| `CategorySyncHandler` | `nosto-integration-category-sync` | Sync category batches |
| `OrderSyncHandler` | `nosto-integration-order-sync` | Sync orders |
| `EntityChangelogSyncHandler` | `nosto-integration-entity-changelog-sync` | Process changelog |
| `MarketingPermissionSyncHandler` | `nosto-integration-marketing-permission-sync` | Newsletter sync |

### Builders

| Builder | Input | Output |
|---------|-------|--------|
| `Product\Builder` | `SalesChannelProductEntity` | Nosto `Product` |
| `Product\SkuBuilder` | Product variants | Nosto SKUs |
| `Order\Builder` | `OrderEntity` | Nosto `Order` |
| `Order\Buyer\Builder` | Customer data | Nosto Buyer |
| `Order\Item\Builder` | Line items | Nosto OrderItems |
| `Category\Builder` | `CategoryEntity` | Nosto `Category` |

---

## Data Flow

### Complete Sync Flow Diagram

```
┌─────────────────────────────────────────────────────────────────────┐
│                        USER ACTION                                   │
│            (Edit Product / Place Order / Update Category)            │
└─────────────────────────────────────────────────────────────────────┘
                                   │
                                   ▼
┌─────────────────────────────────────────────────────────────────────┐
│                      EVENT CAPTURED                                  │
│     ProductWrittenEvent / OrderPlacedEvent / CategoryWrittenEvent    │
└─────────────────────────────────────────────────────────────────────┘
                                   │
                                   ▼
┌─────────────────────────────────────────────────────────────────────┐
│                      EVENT LISTENER                                  │
│                 Writes to entity changelog table                     │
└─────────────────────────────────────────────────────────────────────┘
                                   │
                                   ▼
┌─────────────────────────────────────────────────────────────────────┐
│              SCHEDULED TASK (every 10 minutes)                       │
│                  EntityChangelogScheduledTask                        │
└─────────────────────────────────────────────────────────────────────┘
                                   │
                                   ▼
┌─────────────────────────────────────────────────────────────────────┐
│                ENTITY CHANGELOG SYNC HANDLER                         │
│          Processes changelog, creates sync messages                  │
└─────────────────────────────────────────────────────────────────────┘
                                   │
                    ┌──────────────┼──────────────┐
                    ▼              ▼              ▼
              ┌──────────┐  ┌──────────┐  ┌──────────┐
              │ Product  │  │  Order   │  │ Category │
              │  Sync    │  │  Sync    │  │  Sync    │
              │ Message  │  │ Message  │  │ Message  │
              └──────────┘  └──────────┘  └──────────┘
                    │              │              │
                    ▼              ▼              ▼
              ┌──────────┐  ┌──────────┐  ┌──────────┐
              │ Product  │  │  Order   │  │ Category │
              │  Sync    │  │  Sync    │  │  Sync    │
              │ Handler  │  │ Handler  │  │ Handler  │
              └──────────┘  └──────────┘  └──────────┘
                    │              │              │
                    ▼              ▼              ▼
              ┌──────────┐  ┌──────────┐  ┌──────────┐
              │ Product  │  │  Order   │  │ Category │
              │ Builder  │  │ Builder  │  │ Builder  │
              └──────────┘  └──────────┘  └──────────┘
                    │              │              │
                    └──────────────┼──────────────┘
                                   ▼
┌─────────────────────────────────────────────────────────────────────┐
│                      NOSTO PHP SDK                                   │
│         UpsertProduct / OrderCreate / CategoryUpdate                 │
└─────────────────────────────────────────────────────────────────────┘
                                   │
                                   ▼
┌─────────────────────────────────────────────────────────────────────┐
│                      NOSTO API                                       │
│              Products / Orders / Categories synced                   │
└─────────────────────────────────────────────────────────────────────┘
```

---

## API Endpoints

### Admin API Routes

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/api/_action/nosto-integration/schedule-full-product-sync` | POST | Schedule full catalog sync |
| `/api/_action/nosto-integration/delete-running-full-product-sync-job` | POST | Cancel running sync |
| `/api/_action/nosto-integration/clear-cache` | POST | Clear product cache |
| `/api/_action/nosto-integration-api-key-validate` | POST | Validate API credentials |
| `/api/_action/nosto-config` | GET/POST | Get/Save configuration |
| `/api/_action/nosto-config/batch` | POST | Batch save configuration |

### Storefront Routes

| Endpoint | Purpose |
|----------|---------|
| `/nosto/cart/restore/{id}` | Restore abandoned cart |

---

## Database Entities

### nosto_integration_config

Stores plugin configuration per sales channel and language.

| Column | Type | Description |
|--------|------|-------------|
| `id` | binary(16) | Primary key |
| `configuration_key` | varchar | Config key name |
| `configuration_value` | json | Config value |
| `sales_channel_id` | binary(16) | Sales channel (null = global) |
| `language_id` | binary(16) | Language (null = all languages) |

### nosto_integration_entity_changelog

Event log for product/order/category changes.

| Column | Type | Description |
|--------|------|-------------|
| `id` | binary(16) | Primary key |
| `entity_type` | varchar | Type (product, order, category) |
| `entity_id` | binary(16) | Entity ID |
| `product_number` | varchar | Product number (for products) |

### nosto_integration_checkout_mapping

Maps checkout sessions to carts for abandoned cart recovery.

| Column | Type | Description |
|--------|------|-------------|
| `id` | binary(16) | Primary key |
| `cart_token` | varchar | Shopware cart token |
| `nosto_customer_id` | varchar | Nosto customer identifier |

---

## Performance Optimizations

### Batching

- **Product sync**: Default 150 products per batch (configurable via `syncBatchSize`)
- **Category sync**: Default 150 categories per batch
- **Changelog processing**: Default 100 events per batch

### Caching

- **Product caching**: Via `Product\CachedProvider` using Symfony cache
- **Navigation caching**: Configurable TTL for navigation pages
- **Filter payload caching**: Via `FilterPayloadService`

### Async Processing

The plugin uses `nosto/shopware6-job-scheduler` for job orchestration:

1. Parent jobs (e.g., FullCatalogSync) generate child jobs
2. Jobs stored in `nosto_scheduler_job` table
3. Messages queued in `nosto_scheduler_job_message` table
4. Status tracking: pending → running → completed

### Logging

Enable `productSyncExtraLogging` for performance metrics during product sync.

---

## Extending the Plugin

### Adding Custom Product Data

Listen to `NostoProductBuiltEvent`:

```php
public function onProductBuilt(NostoProductBuiltEvent $event): void
{
    $nostoProduct = $event->getNostoProduct();
    $shopwareProduct = $event->getProduct();

    // Add custom tag
    $nostoProduct->addTag1('custom-value');
}
```

### Modifying Orders Before Sync

Listen to `BeforeOrderCreatedEvent`:

```php
public function beforeOrderCreated(BeforeOrderCreatedEvent $event): void
{
    $order = $event->getOrder();
    // Modify or filter order
}
```

### Custom Configuration

Use `ConfigProvider` to access configuration:

```php
$isEnabled = $this->configProvider->isEnabled($salesChannelId, $languageId);
```

---

## Troubleshooting

### Common Issues

1. **Products not syncing**
    - Check `isEnabled` configuration
    - Verify API tokens are valid
    - Check `nosto_integration_entity_changelog` for pending events
    - Review scheduled task status

2. **Orders not appearing in Nosto**
    - Ensure `2c_cId` cookie is present (Nosto session)
    - Check `customerDataToNosto` configuration
    - Verify order events in changelog

3. **Search not working**
    - Verify `enableSearch` is enabled
    - Check `searchToken` is configured
    - Review GraphQL response in logs

### Debug Logging

Enable extra logging via configuration:
- `productSyncExtraLogging` - Detailed product sync logs
- Check Shopware logs at `var/log/`

---

## Version History

See `CHANGELOG.md` for detailed version history.

---

*This document was generated to help developers understand and work with the Nosto Shopware 6 plugin architecture.*