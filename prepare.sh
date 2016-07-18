#!/bin/bash

dir=$(dirname $(readlink -f $0))
path=$(basename $dir)
database=$1

if [[ $database == "" ]]
then
    echo "Please provide a database name as a first argument"
    exit 1
fi

cd $dir/magento

# Clean up existing orders, to not affect our load test
echo "
 SET foreign_key_checks=0;
 TRUNCATE downloadable_link_purchased;
 TRUNCATE sales_billing_agreement_order;
 TRUNCATE sales_flat_order_address;
 TRUNCATE sales_flat_order_grid;
 TRUNCATE sales_flat_order_item;
 TRUNCATE sales_flat_order_payment;
 TRUNCATE sales_flat_order_status_history;
 TRUNCATE sales_flat_shipment;
 TRUNCATE sales_payment_transaction;
 TRUNCATE sales_recurring_profile_order;
 TRUNCATE sales_flat_order;
 TRUNCATE sales_flat_quote_address;
 TRUNCATE sales_flat_quote_item;
 TRUNCATE sales_flat_quote_payment;
" | mysql $database

# Clear caches for not affecting our load tests
n98-magerun cache:flush
varnishadm "ban req.url ~ /"
rm -rf media/catalog/product/cache

