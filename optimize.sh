#!/bin/bash

dir=$(dirname $(readlink -f $0))
path=$(basename $dir)

cd $dir/magento/

n98-magerun config:set checkout/cart/redirect_to_cart 0
n98-magerun cache:flush
varnishadm 'ban req.url ~ /'
