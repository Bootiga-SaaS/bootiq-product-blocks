# Bootiq Product Blocks

**Current stable release:** [1.0.1](https://github.com/Bootiga-SaaS/bootiq-product-blocks/releases/tag/1.0.1)

Bootiq Product Blocks provides reusable Drupal Layout Builder blocks for the [Bootiq storefront package](https://github.com/Bootiga-SaaS/bootiq).

The module contains configurable storefront sections such as editable product grids and related-product displays. It does not create products or demo content.

## Recommended installation

Install the complete package so its theme, blocks, and Layout Builder configuration stay aligned:

~~~bash
composer require bootiga/bootiq:^1.0
./vendor/drush/drush/drush en bootiq_installer -y
./vendor/drush/drush/drush bootiq:install
~~~

For module development only:

~~~bash
composer require bootiga/bootiq-product-blocks:^1.0
./vendor/drush/drush/drush en bootiq_product_blocks -y
~~~

## Requirements

- Drupal 10 or 11.
- Drupal Commerce 2 or 3.

## About Bootiga

Bootiq is developed and maintained by [Bootiga](https://www.bootiga.com), a hosted Drupal Commerce platform built around open-source ownership, portable stores, and no additional platform commission on store sales.

- [Bootiga](https://www.bootiga.com)
- [Documentation](https://www.bootiga.com/docs)
- [Complete Bootiq package](https://github.com/Bootiga-SaaS/bootiq)

## Contributing

Report block or Layout Builder issues in [GitHub Issues](https://github.com/Bootiga-SaaS/bootiq-product-blocks/issues).

## License

GPL-2.0-or-later.
