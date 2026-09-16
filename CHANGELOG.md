# Changelog

All notable changes to this module are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the versions follow [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- First release. A category page asks for every configurable product's super attributes, store labels and option rows once instead of once per product, by filling in the collection Magento checks for before it queries.
- Two observers and a before plugin, in the frontend area only. The observers note the configurables each collection loads, and the plugin answers a whole group the first time Magento asks about one of them.
- A per store view switch, off on install, and a batch size so one query can't grow without a bound.

### Changed

- Attribute collections are now built when the page first asks about a configurable, not when the collection loads. A product page no longer pays for its related products block: it cost 113 statements against Magento's 109, and now costs 109.
