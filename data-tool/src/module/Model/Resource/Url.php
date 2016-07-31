<?php

class EcomDev_BenchmarkDataTool_Model_Resource_Url
    extends Mage_Core_Model_Resource_Db_Abstract
{
    /**
     * Retrieves list of visible products from category product index
     * that can be discovered by an end customer
     *
     * @return string
     */
    protected function _construct()
    {
        $this->_init('ecomdev_benchmarkdatatool/data_index', 'entity_id');
    }

    /**
     * Fetches product url list by type id
     *
     * @param $typeId
     * @param $storeId
     *
     * @return Zend_Db_Statement_Interface|Traversable
     */
    public function fetchProductAsIterator($typeId, $storeId)
    {
        $select = $this->_getReadAdapter()->select();
        $select
            ->from(['index' => $this->getMainTable()], [])
            ->join(
                ['url' => $this->getTable('core/url_rewrite')],
                'url.product_id = index.entity_id and url.store_id = :store_id '
                . ' and url.category_id IS NUll and url.is_system = 1',
                []
            )
            ->join(
                ['inventory' => $this->getTable('cataloginventory/stock_status')],
                'inventory.product_id = index.entity_id and inventory.stock_status = 1',
                []
            )
            ->columns([
                'product_id' => 'index.entity_id',
                'url' => 'url.request_path'
            ])
            ->where('index.type_id = ?', $typeId)
            ->where('index.is_in_category = ?', 1)
            ->where('index.visibility IN(?)', [
                Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH,
                Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_CATALOG,
                Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_SEARCH
            ])
            ->where('index.status = ?', Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ;

        return $this->_getReadAdapter()->query($select, ['store_id' => $storeId]);
    }

    /**
     * Fetches product url list by type id
     *
     * @param $typeId
     * @param $storeId
     *
     * @return Zend_Db_Statement_Interface|Traversable
     */
    public function fetchCategoryAsIterator($storeId, $rootCategoryId)
    {
        $select = $this->_getReadAdapter()->select();
        $select
            ->from(['category' => $this->getTable('catalog/category')], [])
            ->join(
                ['url' => $this->getTable('core/url_rewrite')],
                'url.category_id = category.entity_id and url.store_id = :store_id '
                . ' and url.product_id IS NUll and url.is_system = 1',
                []
            )
            ->where('category.path LIKE ?', sprintf('1/%d/%%', $rootCategoryId))
            ->columns([
                'category_id' => 'category.entity_id',
                'url' => 'url.request_path'
            ])
        ;

        return $this->_getReadAdapter()->query($select, ['store_id' => $storeId]);
    }


}
