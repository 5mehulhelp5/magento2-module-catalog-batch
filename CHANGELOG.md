# Changelog

All notable changes to this module are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the versions follow [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- First release. A category page asks for every configurable product's super attributes, store labels and option rows once instead of once per product, by seeding the collection Magento checks for before it queries.
- Two observers, in the frontend area only: one for a loaded product listing, one for the product a product page is showing.
- A per store view switch, off on install, and a batch size so one query never grows without a bound.
- **Known: it seeds when a collection loads rather than when a product is first asked**, so a page carrying more than one collection pays for products nobody asks about. A configurable product page costs 113 database statements against Magento's 109 for that reason.
