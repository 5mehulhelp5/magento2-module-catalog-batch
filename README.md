# Kingletas_CatalogBatch

Magento asks the database for a configurable product's attributes **once per product**. On a category page showing twelve configurables that is forty-eight queries for information that two would have fetched. This module asks once per page.

It stores nothing. There is no index, no cron job, no second datastore and no staleness window: it reads the same tables Magento reads, at the same moment, and hands each product the answer it was about to ask for.

## What it removes

Three query families, each of which Magento runs once per configurable product on the page. The module answers the first two together:

| Query | Magento | With this module |
|---|---|---|
| `catalog_product_super_attribute`, which attributes tell the variants apart | one per product | one per page, with the labels |
| `catalog_product_super_attribute_label`, what to call them in this store | one per product | answered by the query above |
| The option rows behind every swatch | one per attribute per product | one per page |

On a twelve-configurable category page in Adobe Commerce 2.4.8 that is **48 queries replaced by 2**.

## How it works

`Magento\ConfigurableProduct\Model\Product\Type\Configurable::getConfigurableAttributes()` returns early when the product already carries a `_cache_instance_configurable_attributes` collection. So the module builds that collection from one page-wide query and puts it there, before anything asks.

It hangs off two events and plugs nothing:

- `catalog_product_collection_load_after`: every configurable in a just-loaded listing
- `catalog_controller_product_view`: the one product a product page is showing

The collection it seeds is a real `Attribute\Collection` with its items already in it and its loaded flag set, so every caller that iterates it, counts it or asks it for `getItems()` behaves exactly as before.

## Installing it

```bash
composer require kingletas/module-catalog-batch
```

```bash
bin/magento module:enable Kingletas_CatalogBatch && bin/magento setup:upgrade
```

**The switch is off on install.** Turn it on per store view under **Stores › Configuration › Kingletas › Catalog Batch**, or from the command line:

```bash
bin/magento config:set kingletas_catalog_batch/general/enabled 1
```

```bash
bin/magento cache:flush
```

| Setting | Default | What it decides |
|---|---|---|
| `kingletas_catalog_batch/general/enabled` | `0` | Whether a page's configurable queries are batched at all |
| `kingletas_catalog_batch/general/batch_size` | `100` | How many configurables one query may cover; a wider page is covered by several |

## What it does not do

- **It seeds eagerly, and that costs something on a page with more than one collection.** A configurable product page loads a related-products collection as well as the product, so the observer fires twice and answers questions that page never asks: 113 statements against Magento's 109, and 1,792 rows scanned for nothing. **Measured on Adobe Commerce 2.4.8-p2 and not yet fixed.** The fix is to seed when the first product is asked rather than when the collection loads.
- **It does not remove the queries, it merges them.** A page still talks to the database. If you need a browse page that touches no database at all, that is a different design and a different module.
- **It does not filter options by salability.** Magento's own storefront filters the option rows to the current website, and this module does the same. Magento Inventory adds a salability filter when a website uses a stock other than the default one and the store hides out of stock products; this module reproduces the website filter only. **On such a store, leave this off** until that gap is closed.
- **It touches nothing on the admin or in the API.** The observers are registered in the frontend area only.

## Requirements

PHP 8.4 or later, Magento Open Source or Adobe Commerce 2.4.8 or later.

## Working on it

```bash
make help
```

```bash
make check
```

`make check` runs the coding standard and every suite. The suites need a Magento vendor tree: point `M2_VENDOR` at one, or run the shared harness from the repository root.

## Licence

OSL-3.0. See [LICENSE](LICENSE).
