# From nothing to a working module-catalog-batch

By the end of this, your category pages ask the database about configurable products once per page instead of once per product, and you've counted the difference yourself.

Plan on about half an hour on a staging copy of the store. Don't start on production.

## Contents

- [What this is](#what-this-is)
- [Before you start](#before-you-start)
- [Step 1: install it](#step-1-install-it)
- [Step 2: find the switch](#step-2-find-the-switch)
- [Step 3: prove it works](#step-3-prove-it-works)
- [Step 4: decide where to leave it off](#step-4-decide-where-to-leave-it-off)
- [Step 5: turn it off](#step-5-turn-it-off)
- [If something looks wrong](#if-something-looks-wrong)
- [Where to go next](#where-to-go-next)

## What this is

When a category page shows configurable products, Magento asks the database which attributes each one has, what they're called and which options its variants use. It asks separately for every product. Twelve configurables with two attributes each means 48 queries.

This module notes which configurables the page loaded. The first time Magento asks about one of them, it answers the whole group with two queries. It stores nothing, so there's nothing to rebuild and nothing to go stale.

## Before you start

You need:

- Magento Open Source or Adobe Commerce 2.4.8 or later, running PHP 8.4 or later
- A staging store with a category page that lists several configurable products
- A MySQL or MariaDB database for that store where you're allowed to turn logging on. Never do this on a production database

> [!warning] Check one thing first
> If a website uses a Magento Inventory stock other than the default one, and the store hides out of stock products, this module isn't safe there. [Step 4](#step-4-decide-where-to-leave-it-off) explains why.

## Step 1: install it

```bash
composer require kingletas/module-catalog-batch
```

```bash
bin/magento module:enable Kingletas_CatalogBatch
```

```bash
bin/magento setup:upgrade
```

If the store runs in production mode, compile and deploy as you would after adding any module:

```bash
bin/magento setup:di:compile
```

```bash
bin/magento setup:static-content:deploy
```

Nothing a shopper sees changes yet. The switch is off when you install it.

## Step 2: find the switch

In the admin, go to **Stores › Configuration › Kingletas › Catalog Batch › General**. The switch is **Batch Configurable Attribute Queries**.

It works per store view, so you can turn it on for one store view and leave the rest alone. Use the scope switcher at the top of the page to pick the store view.

From the command line, the setting is `kingletas_catalog_batch/general/enabled`. In the examples below, `default` is the store view code. Use your own.

**Products Per Query** is the only other setting. It's how many configurables one query may cover, and it defaults to 100. A page with more is split across several queries. It's set globally, and you don't need to change it for this guide.

Don't turn the switch on yet. You'll count the page with it off first.

## Step 3: prove it works

You'll count how many statements one category page sends to the database, first with the module off and then with it on. Make sure cron isn't running and nobody else is using the staging store while you count, or their queries will land in your numbers.

### Turn on the query log

In a MySQL client connected to the staging database, send the log to a table so you can count it with SQL:

```sql
SET GLOBAL log_output = 'TABLE';
SET GLOBAL general_log = 'ON';
```

### Count with the switch off

Make sure the switch is off for your store view:

```bash
bin/magento config:set --scope=stores --scope-code=default kingletas_catalog_batch/general/enabled 0
```

```bash
bin/magento cache:flush
```

Request the category page once to warm Magento's caches. The query string makes the full page cache treat it as a new page:

```bash
curl -s -o /dev/null "https://staging.example.com/women/tops.html?warm=off"
```

Clear the log, then request the page again with a query string you haven't used before:

```sql
TRUNCATE TABLE mysql.general_log;
```

```bash
curl -s -o /dev/null "https://staging.example.com/women/tops.html?count=off"
```

Count what that one request sent. The last condition leaves out your own client's statements:

```sql
SELECT COUNT(*) FROM mysql.general_log
WHERE command_type IN ('Query', 'Execute')
  AND thread_id <> CONNECTION_ID();
```

Write the number down.

### Count with the switch on

```bash
bin/magento config:set --scope=stores --scope-code=default kingletas_catalog_batch/general/enabled 1
```

```bash
bin/magento cache:flush
```

Repeat the same three moves with new query strings: warm with `?warm=on`, run `TRUNCATE TABLE mysql.general_log;`, request `?count=on`, and run the same `SELECT COUNT(*)`.

### Read the result

The second number should be lower. For a page of `n` configurables with `k` super attributes each, Magento sends about `2n + nk` of these queries and the module sends 2, as long as the page fits in one batch. Twelve configurables with two attributes each should come out about 46 statements lower.

For comparison, on Adobe Commerce 2.4.8-p2 a category page of twelve configurables went from 169 statements to 123. Your totals will differ, because the rest of the page differs.

Now try a configurable product page the same way. Expect the same count both ways. The module breaks even there, and its savings are on listings.

Open the category page in a browser too, with the switch on. Swatches, option labels and prices should look exactly as they did before.

### Turn the log off again

```sql
SET GLOBAL general_log = 'OFF';
TRUNCATE TABLE mysql.general_log;
SET GLOBAL log_output = 'FILE';
```

The general log records every statement, so don't leave it running.

## Step 4: decide where to leave it off

Leave it off on any store view where all of these are true:

- the store uses Magento Inventory
- the store view's website uses a stock other than the default one
- the store hides out of stock products

On a store like that, Magento Inventory filters the swatch options so shoppers only see variants they can buy. This module filters options by website, the same as Magento's storefront, but it doesn't add the salability filter. With it on, a shopper could be offered an option that can't be bought.

If a website uses the default stock, or the store shows out of stock products, Inventory adds no filter and the module behaves the same as Magento.

## Step 5: turn it off

Set the switch back to No in the admin, or from the command line:

```bash
bin/magento config:set --scope=stores --scope-code=default kingletas_catalog_batch/general/enabled 0
```

```bash
bin/magento cache:flush
```

Pages go straight back to Magento's own queries. There's no data to clean up, because the module never stored any.

To remove the module completely:

```bash
bin/magento module:disable Kingletas_CatalogBatch
```

```bash
bin/magento setup:upgrade
```

```bash
composer remove kingletas/module-catalog-batch
```

In production mode, run `setup:di:compile` and `setup:static-content:deploy` again afterwards.

## If something looks wrong

Turn the switch off for that store view and flush the cache first. The page goes back to Magento's own queries, and nothing else needs undoing.

| What you see | Most likely cause |
| --- | --- |
| The count doesn't change | The switch is on at a different scope from the store view you're requesting, the cache wasn't flushed, or the page has no configurable products |
| The count changes by a different amount each run | Something else is using the database. Check that cron is stopped and nobody else is on the store |
| The count is the same on a product page | That's expected. The module breaks even on a product page |
| Swatches offer an option that's out of stock | The store matches the case in [Step 4](#step-4-decide-where-to-leave-it-off). Leave the switch off there |

## Where to go next

- [README](../README.md) for the settings and what the module doesn't do
- [How it works](how-it-works.md) for the seam it uses, what it reproduces and the measurements
- [CONTRIBUTING.md](../CONTRIBUTING.md)
