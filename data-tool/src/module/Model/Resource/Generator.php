<?php

class EcomDev_BenchmarkDataTool_Model_Resource_Generator
    extends Mage_Core_Model_Resource_Db_Abstract
{
    /**
     * @var Mage_Eav_Model_Config
     */
    private $eavConfig;

    protected function _construct()
    {
        $this->_init('ecomdev_benchmarkdatatool/data_index', 'entity_id');
        $this->eavConfig = Mage::getSingleton('eav/config');
    }

    /**
     * Generates data index tool
     *
     * @return $this
     */
    public function generateDataIndex()
    {
        $this->createDataIndexTable();

        $columns = [
            'entity_id' => 'main.entity_id',
            'type_id' => 'main.type_id',
            'status' => 'status_table.value',
            'visibility' => 'visibility_table.value',
            'is_in_configurable' => new Zend_Db_Expr('0'),
            'is_in_category' => new Zend_Db_Expr('0')
        ];

        $select = $this->_getWriteAdapter()->select();
        $select->from(['main' => $this->getTable('catalog/product')], []);

        $statusAttribute = $this->eavConfig->getAttribute(Mage_Catalog_Model_Product::ENTITY, 'status');
        $visibilityAttribute = $this->eavConfig->getAttribute(Mage_Catalog_Model_Product::ENTITY, 'visibility');

        $select->join(
            ['status_table' => $statusAttribute->getBackendTable()],
            'status_table.entity_id = main.entity_id '
            . ' and status_table.attribute_id = :status_attribute_id '
            . 'and status_table.store_id = 0',
            []
        );

        $select->join(
            ['visibility_table' => $visibilityAttribute->getBackendTable()],
            'visibility_table.entity_id = main.entity_id '
            . ' and visibility_table.attribute_id = :visibility_attribute_id '
            . 'and visibility_table.store_id = 0',
            []
        );

        $select->columns($columns);

        $this->_getWriteAdapter()->query(
            $select->insertFromSelect($this->getMainTable(), array_keys($columns)),
            [
                'status_attribute_id' => $statusAttribute->getId(),
                'visibility_attribute_id' => $visibilityAttribute->getId()
            ]
        );

        $select->reset()
            ->from($this->getTable('catalog/category_product'), ['product_id']);

        $this->_getWriteAdapter()->update(
            $this->getMainTable(),
            ['is_in_category' => 1],
            ['entity_id IN(?)' => $select]
        );

        $select->reset()
            ->from($this->getTable('catalog/product_super_link'), ['product_id']);

        $this->_getWriteAdapter()->update(
            $this->getMainTable(),
            ['is_in_configurable' => 1],
            ['entity_id IN(?)' => $select]
        );

        return $this;
    }

    /**
     * Generates data index tool
     *
     * @return $this
     */
    public function generateCategorySummary()
    {
        $this->createCategorySummaryTable();

        $columns = [
            'category_id' => 'product_category.category_id',
            'is_anchor' => 'NOT(product_category.is_parent)',
            'type_id' => 'main.type_id',
            'product_count' => 'COUNT(product_category.product_id)',
            'avg_position' => 'AVG(product_category.position)',
            'min_position' => 'MIN(product_category.position)',
            'max_position' => 'MAX(product_category.position)',
            'min_id' => 'MIN(product_category.product_id)',
            'max_id' => 'MAX(product_category.product_id)'
        ];

        $select = $this->_getWriteAdapter()->select();
        $select->from(['main' => $this->getMainTable()], [])
            ->join(
                ['product_category' => $this->getTable('catalog/category_product_index')],
                'product_category.product_id = main.entity_id',
                []
            )
            ->group([
                'main.type_id',
                'product_category.category_id',
                'product_category.is_parent'
            ])
            ->columns($columns);

        $this->_getWriteAdapter()->query(
            $select->insertFromSelect(
                $this->getTable('ecomdev_benchmarkdatatool/category_summary'),
                array_keys($columns)
            )
        );

        return $this;
    }


    /**
     * Generates data index tool
     *
     * @param mixed[] $where
     * @param string[] $order
     * @return $this
     */
    public function generateFixSchedule(array $where, array $order = [])
    {
        $this->createFixScheduleTable();

        $columns = [
            'entity_id' => 'main.entity_id'
        ];

        $select = $this->_getWriteAdapter()->select();
        $select->from(['main' => $this->getMainTable()], []);

        foreach ($where as $condition => $value) {
            $select->where($condition, $value);
        }

        if ($order) {
            $select->order($order);
        }

        $select->columns($columns);

        $this->_getWriteAdapter()->query(
            $select->insertFromSelect($this->getTable('ecomdev_benchmarkdatatool/fix_schedule'), array_keys($columns))
        );

        return $this;
    }


    private function createDataIndexTable()
    {
        $this->_getWriteAdapter()->dropTable($this->getMainTable());

        $table = $this->_getWriteAdapter()->newTable($this->getMainTable());

        $table
            ->addColumn(
                'entity_id',
                Varien_Db_Ddl_Table::TYPE_INTEGER,
                null,
                ['nullable' => false, 'unsigned' => true, 'primary' => true]
            )
            ->addColumn(
                'type_id',
                Varien_Db_Ddl_Table::TYPE_TEXT,
                255,
                ['nullable' => false]
            )
            ->addColumn(
                'status',
                Varien_Db_Ddl_Table::TYPE_INTEGER,
                null,
                ['nullable' => false, 'unsigned' => true]
            )
            ->addColumn(
                'visibility',
                Varien_Db_Ddl_Table::TYPE_INTEGER,
                null,
                ['nullable' => false, 'unsigned' => true]
            )
            ->addColumn(
                'is_in_configurable',
                Varien_Db_Ddl_Table::TYPE_INTEGER,
                null,
                ['nullable' => false, 'unsigned' => true]
            )
            ->addColumn(
                'is_in_category',
                Varien_Db_Ddl_Table::TYPE_INTEGER,
                null,
                ['nullable' => false, 'unsigned' => true]
            )
            ->addIndex('IDX_TYPE_ID', 'type_id')
            ->addIndex('IDX_STATUS', 'status')
            ->addIndex('IDX_VISIBILITY', 'visibility')
            ->addIndex('IDX_IS_IN_CONFIGURABLE', 'is_in_configurable')
            ->addIndex('IDX_IS_IN_CATEGORY', 'is_in_category')
        ;

        $this->_getWriteAdapter()->createTable($table);
        return $this;
    }

    private function createFixScheduleTable()
    {
        $this->_getWriteAdapter()->dropTable($this->getTable('ecomdev_benchmarkdatatool/fix_schedule'));

        $table = $this->_getWriteAdapter()->newTable($this->getTable('ecomdev_benchmarkdatatool/fix_schedule'));

        $table
            ->addColumn(
                'schedule_id',
                Varien_Db_Ddl_Table::TYPE_INTEGER,
                null,
                ['nullable' => false, 'unsigned' => true, 'primary' => true, 'identity' => true]
            )
            ->addColumn(
                'entity_id',
                Varien_Db_Ddl_Table::TYPE_INTEGER,
                null,
                ['nullable' => false, 'unsigned' => true]
            )
            ->addIndex('IDX_ENTITY_ID', 'entity_id')
        ;

        $this->_getWriteAdapter()->createTable($table);
        return $this;
    }

    private function createCategorySummaryTable()
    {
        $this->_getWriteAdapter()->dropTable($this->getTable('ecomdev_benchmarkdatatool/category_summary'));

        $table = $this->_getWriteAdapter()->newTable($this->getTable('ecomdev_benchmarkdatatool/category_summary'));

        $table
            ->addColumn(
                'category_id',
                Varien_Db_Ddl_Table::TYPE_INTEGER,
                null,
                ['nullable' => false, 'unsigned' => true, 'primary' => true]
            )
            ->addColumn(
                'is_anchor',
                Varien_Db_Ddl_Table::TYPE_INTEGER,
                1,
                ['nullable' => false, 'primary' => true]
            )
            ->addColumn(
                'type_id',
                Varien_Db_Ddl_Table::TYPE_TEXT,
                32,
                ['nullable' => false, 'primary' => true]
            )
            ->addColumn(
                'product_count',
                Varien_Db_Ddl_Table::TYPE_INTEGER,
                null,
                ['nullable' => false, 'unsigned' => true]
            )
            ->addColumn(
                'avg_position',
                Varien_Db_Ddl_Table::TYPE_INTEGER,
                null,
                ['nullable' => false, 'unsigned' => true]
            )
            ->addColumn(
                'min_position',
                Varien_Db_Ddl_Table::TYPE_INTEGER,
                null,
                ['nullable' => false, 'unsigned' => true]
            )
            ->addColumn(
                'max_position',
                Varien_Db_Ddl_Table::TYPE_INTEGER,
                null,
                ['nullable' => false, 'unsigned' => true]
            )
            ->addColumn(
                'min_id',
                Varien_Db_Ddl_Table::TYPE_INTEGER,
                null,
                ['nullable' => false, 'unsigned' => true]
            )
            ->addColumn(
                'max_id',
                Varien_Db_Ddl_Table::TYPE_INTEGER,
                null,
                ['nullable' => false, 'unsigned' => true]
            )
        ;

        $this->_getWriteAdapter()->createTable($table);
        return $this;
    }
}
