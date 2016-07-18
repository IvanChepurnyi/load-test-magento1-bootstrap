#!/bin/bash

dir=$(dirname $(readlink -f $0))
path=$(basename $dir)
database=$1
domain=$2


if [[ $database == "" ]]
then
    echo "Please provide a database name as a first argument"
    exit 1
fi

if [[ $domain == "" ]]
then
    echo "Please provide a domain name as a second argument"
    exit 1
fi

cd $dir

wget -qO- https://magento.mirror.hypernode.com/releases/magento-1.9.2.4.tar.gz | tar xfz -
composer install
composer run-script post-install-cmd -vvv -- --redeploy

rm ../public/*.txt
rmdir ../public
ln -sf $path/magento ../public

# Import database
mysql -e "drop database if exists $database; create database $database;"
MYSQLPASSWORD=$(awk -F "=" '/password/ {print $2}' ${HOME}/.my.cnf | sed -e 's/^[ \t]*//')
MYSQLUSER=$(awk -F "=" '/user/ {print $2}' ${HOME}/.my.cnf | sed -e 's/^[ \t]*//')
MYSQLHOST=$(awk -F "=" '/host/ {print $2}' ${HOME}/.my.cnf | sed -e 's/^[ \t]*//')
gunzip < $dir/db/data.sql.gz | mysql $database

# Install magento configure it
n98-magerun install --dbHost="$MYSQLHOST" --dbUser="$MYSQLUSER" --dbPass="$MYSQLPASSWORD" --dbName="$database" \
    --installSampleData=yes --useDefaultConfigParams=yes --noDownload \
    --installationFolder="magento" --baseUrl="http://$domain/" --forceUseDb

cd magento/
n98-magerun config:set design/package/name benchmark
n98-magerun config:set dev/template/allow_symlink 1
n98-magerun config:set catalog/frontend/flat_catalog_category 1
n98-magerun config:set catalog/frontend/flat_catalog_product 1
n98-magerun cache:flush
n98-magerun cache:enable
n98-magerun index:reindex catalog_product_flat
n98-magerun index:reindex catalog_category_flat

# Install varnish VCL
php shell/ecomdev-varnish.php vcl:generate -c $dir/config/vcl.json -v 4 > varnish.vcl

varnishadm vcl.load v4 $PWD/varnish.vcl
varnishadm vcl.use v4
