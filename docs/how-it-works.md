# How it works

Magento's configurable product type asks the database three separate questions about every configurable product on a page, and it asks them **once per product**. This module asks them once per page.

## The three questions

| Question | The table it reads | How often Magento asks |
|---|---|---|
| Which attributes tell this product's variants apart? | `catalog_product_super_attribute` | once per configurable product |
| What is each of those attributes called in this store? | `catalog_product_super_attribute_label` | once per configurable product |
| Which option values do its variants actually use, and what are they called? | `eav_attribute_option_value` and friends | once per attribute, per product |

A category page showing twelve configurables with two super attributes each asks **48 times**: twelve for the attributes, twelve for their labels and twenty-four for the options. Every one of those queries is small. That is the problem: 48 small queries cost more in round trips and statement parsing than two larger ones cost in work, one for the attributes with their labels and one for the options.

## The seam

`Magento\ConfigurableProduct\Model\Product\Type\Configurable::getConfigurableAttributes()` opens like this:

```php
if (!$product->hasData($this->_configurableAttributes)) {
    // load the collection
}
return $product->getData($this->_configurableAttributes);
```

`$this->_configurableAttributes` is the string `_cache_instance_configurable_attributes`. So a product that already carries that collection is never queried for. The module builds the collection from one page-wide query and puts it there before anything asks.

That is the whole mechanism. No plugin, no preference, no override of a core class.

## Why the collection has to be a real one

A plain array would be simpler and it breaks a real page. `Magento\ConfigurableProduct\Pricing\Price\ConfigurableRegularPrice` calls `$attributes->getItems()` on the result, which is a collection method, and it does so while rendering prices on a listing. So the module seeds a subclass of Magento's own `Attribute\Collection` with its items already added and its loaded flag set.

`getItems()`, `getIterator()` and `count()` all call `load()` first, and `load()` returns immediately when the collection says it is loaded. That flag is the only reason no query happens.

## Where it hooks in

| Event | What it covers |
|---|---|
| `catalog_product_collection_load_after` | every configurable in a listing, search result, widget or related-products block |
| `catalog_controller_product_view` | the one product a product page is showing |

Both are registered in the **frontend** area only, so the admin and the API are untouched.

## What it reproduces, exactly

The option query is the one that has to match Magento's own behaviour field for field, because the swatch renderer reads its output. The module reproduces:

- the same nine columns, in the same order, with `option_title` falling back from the store's own label to the default one;
- the website filter Magento's own storefront plugin adds, so a variant that belongs to another website is not offered;
- the store-scoped super attribute label, with the `use_default` fallback Magento's own label query computes;
- the source-model override, where an attribute with a source model takes its titles from the source rather than from the option table;
- a stable order, `attribute_option.sort_order` then `entity.entity_id`, so the same page renders the same way twice. Magento leaves the tie to MySQL.

## What it does not reproduce

**Magento Inventory adds a salability filter to the option query.** When a website uses the default stock, or the store shows out of stock products, that plugin adds nothing and the two behave the same. When a website uses another stock and the store hides out of stock products, Magento's own query drops options whose variants cannot be bought and this module's does not. **Leave the switch off there.**

Closing that gap is possible and this module is the right place for it: unlike an index, a query that runs at request time can see today's stock. It is not done yet.

## The cost, measured

On Adobe Commerce 2.4.8-p2 in production mode, 2,048 products, a category page listing twelve configurable products. Every statement timed with the slow log at `long_query_time = 0`, five samples per state in alternating rounds, the page cache bypassed so every request is a real render.

| | Statements | Database ms |
|---|---:|---:|
| Magento | 169 | 34.4 |
| With this module | **123** | **26.6** |

**23% of the database time, for a module that stores nothing.** The page itself renders in about 280 ms, of which the database is 12%, so read that as 3% of the page rather than as 23% of anything a shopper notices.

The number that mattered more: under a browse load arriving at a fixed 420 requests a minute, all of them cache misses, **checkout went from 37 orders a minute to 47** with this module and a salable-child count answered once per page too, which is not part of this module yet, and order p95 fell from 3.8 s to 1.6 s. Browse and checkout compete for PHP workers, and a page that finishes sooner releases one sooner.

**Every number here is from one laptop and one store.** A measurement is a fact about the machine that produced it.
