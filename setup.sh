#!/bin/bash

dir=$(dirname $(readlink -f $0))
path=$(basename $dir)
database=$1
domain=$2
databaseVersion=$3
version=$4

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

if [[ $databaseVersion == "" ]]
then
    databaseVersion=""
fi

if [[ $version == "" ]]
then
    version="1.9.2.4"
fi

cd $dir

wget -qO- https://magento.mirror.hypernode.com/releases/magento-1.9.2.4.tar.gz | tar xfz -
composer install
composer run-script post-install-cmd -vvv -- --redeploy

rm ../public/*.txt
rmdir ../public
ln -sf $path/magento ../public

dbfile=$dir/db/data.sql.gz

if [ -f $dir/db/data-$databaseVersion-$version.sql.gz ]
then
   dbfile=$dir/db/data-$databaseVersion-$version.sql.gz
elif [ -f $dir/db/data-$version.sql.gz ]
then
   dbfile=$dir/db/data-$version.sql.gz
elif [ -f $dir/db/data-$databaseVersion.sql.gz ]
then
   dbfile=$dir/db/data-$databaseVersion.sql.gz
fi

echo "Importing $dbfile"
databaseFile=$dbfile

# Import database
mysql -e "drop database if exists $database; create database $database;"
MYSQLPASSWORD=$(awk -F "=" '/password/ {print $2}' ${HOME}/.my.cnf | sed -e 's/^[ \t]*//')
MYSQLUSER=$(awk -F "=" '/user/ {print $2}' ${HOME}/.my.cnf | sed -e 's/^[ \t]*//')
MYSQLHOST=$(awk -F "=" '/host/ {print $2}' ${HOME}/.my.cnf | sed -e 's/^[ \t]*//')
gunzip < $databaseFile | mysql $database

# Install magento configure it
n98-magerun install --dbHost="$MYSQLHOST" --dbUser="$MYSQLUSER" --dbPass="$MYSQLPASSWORD" --dbName="$database" \
    --installSampleData=yes --useDefaultConfigParams=yes --noDownload \
    --installationFolder="magento" --baseUrl="http://$domain/" --forceUseDb

cd magento/

n98-magerun config:set design/package/name benchmark
n98-magerun config:set web/unsecure/base_url http://$domain/
n98-magerun config:set web/secure/base_url http://$domain/
n98-magerun config:set dev/template/allow_symlink 1
n98-magerun config:set catalog/frontend/flat_catalog_category 1
n98-magerun config:set catalog/frontend/flat_catalog_product 1
n98-magerun cache:flush
n98-magerun cache:enable

# We have to re-index only flat and category index, as others are up-to date during install process
n98-magerun index:reindex catalog_product_flat
n98-magerun index:reindex catalog_category_flat

# Install varnish VCL
php shell/ecomdev-varnish.php vcl:generate -c $dir/config/vcl.json -v 4 > varnish.vcl

varnishadm vcl.load m1benchmark $PWD/varnish.vcl
varnishadm vcl.use m1benchmark

bash $dir/config/media.sh $dir/magento $dir/config/media.set $dir/config/media
