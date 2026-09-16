# Kingletas_CatalogBatch

Magento asks the database about a configurable product's attributes **once per product**. On a category page with twelve configurables, that's forty-eight queries for information two queries could have fetched. This module asks once per page.

It stores nothing. There's no index, no cron job, no second datastore and nothing to go stale. It reads the same tables Magento reads, at the moment Magento would read them, and hands each product the answer it was about to ask for.

New to it? Start with [From nothing to a working module-catalog-batch](docs/from-nothing.md).

## What it removes

Magento runs three kinds of query once for every configurable product on the page. The module answers the first two together:

| Query | Magento | With this module |
|---|---|---|
| `catalog_product_super_attribute`, which attributes tell the variants apart | one per product | one per page, with the labels |
| `catalog_product_super_attribute_label`, what to call them in this store | one per product | answered by the query above |
| The option rows behind every swatch | one per attribute per product | one per page |

Take a category page of twelve configurables with two super attributes each. Magento asks 48 times: 12 attribute queries, 12 label queries and 24 option queries. This module asks twice.

## How it works

`Magento\ConfigurableProduct\Model\Product\Type\Configurable::getConfigurableAttributes()` returns early when the product already carries a `_cache_instance_configurable_attributes` collection. The module fills that collection in before Magento looks.

It works in two parts:

- **Two observers note what a page loaded.** `catalog_product_collection_load_after` notes the configurables in each loaded collection, and `catalog_controller_product_view` notes the product a product page is showing. Each collection's products are kept together as one group, with the store and website they were loaded for. Nothing is queried yet.
- **A before plugin answers when Magento asks.** The first time Magento calls `getConfigurableAttributes()` for any product in a group, the plugin answers the whole group with two queries: the attribute rows with their labels, then the option rows.

A collection whose products Magento never asks about, like a related products block, costs nothing. If Magento asks with a different object for the same product, it gets the same answer. A product that no collection noted, like one in the shopping cart, goes down Magento's own path.

The collection it builds is a real `Attribute\Collection` with its items already in it and its loaded flag set. Anything that loops over it, counts it or calls `getItems()` on it behaves as it did before.

The plugin is a before plugin, not an around plugin. The observers and the plugin are registered for the storefront (the frontend area) only.

## Installing it

```bash
composer require kingletas/module-catalog-batch
```

```bash
bin/magento module:enable Kingletas_CatalogBatch && bin/magento setup:upgrade
```

In production mode, also run `bin/magento setup:di:compile` and `bin/magento setup:static-content:deploy`.

**The switch is off when you install it.** Turn it on per store view under **Stores › Configuration › Kingletas › Catalog Batch**, or from the command line:

```bash
bin/magento config:set kingletas_catalog_batch/general/enabled 1
```

```bash
bin/magento cache:flush
```

| Setting | Default | What it decides |
|---|---|---|
| `kingletas_catalog_batch/general/enabled` | `0` | Whether a page's configurable queries are batched at all. Set per store view |
| `kingletas_catalog_batch/general/batch_size` | `100` | How many configurables one query may cover. A bigger group is split across several. Set globally only |

## Before you turn it on

- **It won't make a product page faster.** On a configurable product page it breaks even: measured on Adobe Commerce 2.4.8-p2, that page costs 109 statements with the module and 109 without. The savings are on listings. The same store's category page of twelve configurables went from 169 statements to 123.
- **It merges queries, it doesn't remove them.** A page still talks to the database. If you need a browse page that never touches the database, that's a different design and a different module.
- **It doesn't filter options by salability.** Magento's storefront filters option rows to the current website, and this module does the same. Magento Inventory also adds a salability filter when a website uses a stock other than the default one and the store hides out of stock products. This module doesn't reproduce that filter, so **leave it off on such a store** until the gap is closed.
- **It touches nothing in the admin or the API.** Everything it registers is in the frontend area.

## Requirements

PHP 8.4 or later, and Magento Open Source or Adobe Commerce 2.4.8 or later.

## Working on it

```bash
make help
```

```bash
make check
```

`make check` runs the coding standard and every suite. The suites need a Magento vendor tree, so point `M2_VENDOR` at one or run the shared harness from the repository root.

## Licence

OSL-3.0. See [LICENSE](LICENSE).
