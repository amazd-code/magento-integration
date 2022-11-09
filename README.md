# Amazd Magento integration

## Features

- Integration of Amazd wishbag-to-checkout flow

## Installation

- Install via composer:

```sh
composer require amazd/integration
php bin/magento setup:upgrade
```

- Install from Github:

```sh
git clone https://github.com/amazd-code/magento-integration
mv magento-integration {magento_root}/app/code/Amazd/Integration
cd {magento_root}
php bin/magento module:enable Amazd_Integration
php bin/magento setup:upgrade
php bin/magento setup:di:compile
```
