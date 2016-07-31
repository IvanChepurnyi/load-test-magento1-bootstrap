<?php

class EcomDev_BenchmarkDataTool_Model_Resource_Fix
    extends Mage_Core_Model_Resource_Db_Abstract
{
    /**
     * @var Mage_Eav_Model_Config
     */
    private $eavConfig;

    /**
     * @var EcomDev_BenchmarkDataTool_Model_Resource_Generator
     */
    private $generator;

    protected function _construct()
    {
        $this->_init('ecomdev_benchmarkdatatool/data_index', 'entity_id');
        $this->eavConfig = Mage::getSingleton('eav/config');
        $this->generator = Mage::getResourceSingleton('ecomdev_benchmarkdatatool/generator');
    }

    /**
     * Fix products that are assigned to configurable,
     * but not assigned to categories and still marked as visible
     */
    public function fixVisibleButWithoutCategoryAndInConfigurable()
    {
        $visibilityAttribute = $this->eavConfig->getAttribute(Mage_Catalog_Model_Product::ENTITY, 'visibility');
        $select = $this->_getWriteAdapter()->select();
        $select->join(['data_index' => $this->getMainTable()], 'data_index.entity_id = attribute.entity_id', []);
        $select->where('attribute.attribute_id = ?', $visibilityAttribute->getId());
        $select->where('data_index.is_in_configurable = ?', 1);
        $select->where('data_index.is_in_category = ?', 0);
        $select->where('data_index.visibility <> ?', Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE);
        $select->columns([
            'value' => new Zend_Db_Expr(Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE)
        ]);
        $this->_getWriteAdapter()->query($select->crossUpdateFromSelect(
            ['attribute' => $visibilityAttribute->getBackendTable()]
        ));

        Mage::getSingleton('index/indexer')
            ->getProcessByCode('catalog_category_product')
            ->reindexAll();
        
        Mage::getSingleton('index/indexer')
            ->getProcessByCode('catalog_product_flat')
            ->reindexAll();
    }

    public function fixProductCategoryPosition()
    {
        $select = $this->_getWriteAdapter()->select();

        $columns = [
            'position' => 'CEIL(category_summary.product_count * RAND())'
        ];

        $select
            ->join(
                ['category_summary' => $this->getTable('ecomdev_benchmarkdatatool/category_summary')],
                'category_summary.category_id = category_product.category_id',
                []
            )
            ->where('category_summary.is_anchor = ?', 0)
            ->columns($columns)
        ;

        $this->_getWriteAdapter()->query(
            $select->crossUpdateFromSelect(['category_product' => $this->getTable('catalog/category_product')])
        );
        
        Mage::getSingleton('index/indexer')
            ->getProcessByCode('catalog_category_product')
            ->reindexAll();
    }

    /**
     * @param int $percentToLeaveUnchanged
     */
    public function fixSimpleProductThatAreAssignedIntoCategoryAndConfigurable($percentToLeaveUnchanged)
    {
        $this->generator->generateFixSchedule(
            [
                'visibility <> ?' => Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE,
                'status = ?' => Mage_Catalog_Model_Product_Status::STATUS_ENABLED,
                'is_in_category = ?' => 1,
                'is_in_configurable = ?' => 1,
                'type_id = ?' => Mage_Catalog_Model_Product_Type::TYPE_SIMPLE
            ],
            ['RAND()']
        );

        $itemsToLeave = ceil($this->fetchTotalNumberOfFixableRecords() * ($percentToLeaveUnchanged / 100));
        $visibilityAttribute = $this->eavConfig->getAttribute(Mage_Catalog_Model_Product::ENTITY, 'visibility');

        $select = $this->_getWriteAdapter()->select();
        $select
            ->join(
                ['fix_list' => $this->getTable('ecomdev_benchmarkdatatool/fix_schedule')],
                'fix_list.entity_id = attribute.entity_id',
                []
            )
            ->where('attribute.attribute_id = ?', $visibilityAttribute->getId())
            ->where('fix_list.schedule_id > ?', $itemsToLeave)
            ->columns([
                'value' => new Zend_Db_Expr(Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE)
            ]);

        $this->_getWriteAdapter()->query($select->crossUpdateFromSelect(
            ['attribute' => $visibilityAttribute->getBackendTable()]
        ));

        $select
            ->reset()
            ->from(['product_category' => $this->getTable('catalog/category_product')], [])
            ->join(
                ['fix_list' => $this->getTable('ecomdev_benchmarkdatatool/fix_schedule')],
                'fix_list.entity_id = product_category.product_id',
                []
            )
            ->where('fix_list.schedule_id > ?', $itemsToLeave);

        $this->_getWriteAdapter()->query($select->deleteFromSelect('product_category'));

        Mage::getSingleton('index/indexer')
            ->getProcessByCode('catalog_category_product')
            ->reindexAll();
    }

    /**
     * @return int
     */
    private function fetchTotalNumberOfFixableRecords()
    {
        $select = $this->_getReadAdapter()->select();
        $select->from($this->getTable('ecomdev_benchmarkdatatool/fix_schedule'), 'COUNT(entity_id)');
        return $this->_getReadAdapter()->fetchOne($select);
    }

}
