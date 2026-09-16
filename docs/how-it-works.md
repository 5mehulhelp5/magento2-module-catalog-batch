# How it works

Magento's configurable product type asks the database three separate questions about every configurable product on a page, and it asks them **once per product**. This module asks them once per page.

## The three questions

| Question | The table it reads | How often Magento asks |
|---|---|---|
| Which attributes tell this product's variants apart? | `catalog_product_super_attribute` | once per configurable product |
| What's each of those attributes called in this store? | `catalog_product_super_attribute_label` | once per configurable product |
| Which option values do its variants actually use, and what are they called? | `eav_attribute_option_value` and related tables | once per attribute, per product |

A category page showing twelve configurables with two super attributes each asks **48 times**: 12 for the attributes, 12 for their labels and 24 for the options.

Each of those queries is small, and that's the problem. Forty-eight small queries cost more in round trips and statement parsing than two larger ones cost in work. The module runs those two: one for the attributes with their labels, and one for the options.

## The seam

`Magento\ConfigurableProduct\Model\Product\Type\Configurable::getConfigurableAttributes()` opens like this:

```php
if (!$product->hasData($this->_configurableAttributes)) {
    // load the collection
}
return $product->getData($this->_configurableAttributes);
```

`$this->_configurableAttributes` is the string `_cache_instance_configurable_attributes`. If a product already carries that collection, Magento never queries for it. So the module builds the collection itself and puts it on the product just before Magento looks.

It doesn't replace or override any core class, and it doesn't use a preference.

## When it asks

Building the answer as soon as a collection loads would be simpler, and it wastes work. A product page loads a related products block as well as the product, and the page may never ask about those related products at all. So the module waits.

1. When a collection of products loads, an observer notes the configurables in it. They're kept together as one group, with the store and website the collection was loaded for.
2. When Magento first calls `getConfigurableAttributes()` for any product in a group, a before plugin answers the whole group with two queries.
3. The group is then forgotten, so it's never answered twice.

A group Magento never asks about costs nothing. A product no collection noted, such as one in the shopping cart, goes down Magento's own path untouched.

Magento sometimes asks with a different object for the same product. The plugin copies the answer from the object in the group, so both get the same collection.

It's a before plugin, not an around plugin. It always returns null, which tells Magento to keep its own arguments, and Magento then finds the collection already in place.

## Why the collection has to be a real one

A plain array would be simpler, and it breaks a real page. `Magento\ConfigurableProduct\Pricing\Price\ConfigurableRegularPrice` calls `$attributes->getItems()` on the result, which is a collection method, and it does so while rendering prices on a listing. So the module builds a subclass of Magento's own `Attribute\Collection` with its items already added and its loaded flag set.

`getItems()`, `getIterator()` and `count()` all call `load()` first, and `load()` returns straight away when the collection says it's loaded. That flag is the only reason no query happens.

## Where it hooks in

| Hook | Kind | What it does |
|---|---|---|
| `catalog_product_collection_load_after` | event | notes every configurable in a listing, search result, widget or related products block |
| `catalog_controller_product_view` | event | notes the product a product page is showing |
| `Configurable::getConfigurableAttributes()` | before plugin | answers a product's whole group the first time Magento asks about one of them |

All three are registered in the **frontend** area only, so the admin and the API aren't touched. The observers note nothing while the switch is off for the store view, so the plugin has nothing to answer.

## What it reproduces, exactly

The option query has to match Magento's own field for field, because the swatch renderer reads its output. The module reproduces:

- the same nine columns, in the same order, with `option_title` falling back from the store's own label to the default one;
- the website filter Magento's storefront adds, so a variant that belongs to another website isn't offered;
- the store-scoped super attribute label, with the `use_default` fallback Magento's own label query works out;
- the source model override, where an attribute with a source model takes its titles from the source rather than from the option table;
- a stable order, `attribute_option.sort_order` then `entity.entity_id`, so the same page renders the same way twice. Magento leaves the tie to MySQL.

## What it doesn't reproduce

**Magento Inventory adds a salability filter to the option query.** When a website uses the default stock, or the store shows out of stock products, that filter adds nothing and the two behave the same. When a website uses another stock and the store hides out of stock products, Magento's query drops options whose variants can't be bought, and this module's doesn't. **Leave the switch off there.**

That gap can be closed, and this module is the right place to do it: unlike an index, a query that runs during the request can see today's stock. It isn't done yet.

## The cost, measured

These numbers come from Adobe Commerce 2.4.8-p2 in production mode, with 2,048 products and a category page listing twelve configurable products. Every statement was timed with the slow log at `long_query_time = 0`, five samples per state in alternating rounds, with the page cache bypassed so every request was a real render.

| | Statements | Database ms |
|---|---:|---:|
| Magento | 169 | 34.4 |
| With this module | **123** | **26.6** |

The statement counts are from the current version. The database times are from an earlier timed pass. **That's 23% of the database time, from a module that stores nothing.** The page renders in about 280 ms and the database is 12% of that, so read it as 3% of the page, not 23% of anything a shopper notices.

A configurable product page costs 109 statements with the module and 109 without. It breaks even there. The savings are on listings.

The number that mattered more came from a load test. Browse traffic arrived at a fixed 420 requests a minute, all of them cache misses. **Checkout went from 37 orders a minute to 47**, and order p95 fell from 3.8 s to 1.6 s. That run also had a salable-child count answered once per page, which isn't part of this module yet. Browse and checkout compete for PHP workers, and a page that finishes sooner frees one sooner.

**Every number here comes from one laptop and one store.** A measurement is a fact about the machine that produced it.
