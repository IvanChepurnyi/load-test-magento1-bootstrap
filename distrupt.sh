#!/bin/bash

dir=$(dirname $(readlink -f $0))

cd $dir/magento/shell

# Category product relation indexer
php indexer.php --reindex catalog_category_product

# Stock inventory indexer
php indexer.php --reindex cataloginventory_stock

# Price indexer
php indexer.php --reindex catalog_product_price

# Catalog product attribute indexer
php indexer.php --reindex catalog_product_attribute

# Catalog product flat indexer
php indexer.php --reindex catalog_product_flat
