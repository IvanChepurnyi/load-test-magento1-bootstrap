<?php

class EcomDev_BenchmarkDataTool_Model_Resource_Info
    extends Mage_Core_Model_Resource_Db_Abstract
{
    /**
     * Generates information about database
     *
     * @return string
     */
    protected function _construct()
    {
        $this->_init('ecomdev_benchmarkdatatool/data_index', 'entity_id');
    }


    /**
     * Returns number of products in database by type
     *
     * @param string $typeId
     * @return int
     */
    public function fetchProductStatsByTypeId($typeId)
    {
        $select = $this->_getLoadSelect('type_id', $typeId, new Varien_Object());
        $select->reset(Varien_Db_Select::COLUMNS);

        return [
            'total' => $this->fetchCountBySelect($select, 'entity_id', []),
            'enabled' => $this->fetchCountBySelect($select, 'entity_id', ['status = ?' => 1]),
            'visible_in_catalog' => $this->fetchCountBySelect($select, 'entity_id', [
                'visibility IN(?)' => [
                    Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH,
                    Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_CATALOG
                ]
            ]),
            'visible_in_search' => $this->fetchCountBySelect($select, 'entity_id', [
                'visibility IN(?)' => [
                    Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH,
                    Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_SEARCH
                ]
            ]),
            'in_category' => $this->fetchCountBySelect($select, 'entity_id', ['is_in_category = ?' => 1]),
            'in_configurable' => $this->fetchCountBySelect($select, 'entity_id', ['is_in_configurable = ?' => 1])
        ];
    }

    /**
     * Returns count by numbers
     *
     * @param Varien_Db_Select $select
     * @param string $column
     * @param mixed[] $where
     * @return int
     */
    private function fetchCountBySelect($select, $column, $where)
    {
        $select = clone $select;
        $select->columns([
            'count' => sprintf('COUNT(%s)', $this->_getReadAdapter()->quoteIdentifier($column))
        ]);

        foreach ($where as $condition => $value) {
            $select->where($condition, $value);
        }

        return $this->_getReadAdapter()->fetchOne($select);
    }

    /**
     * @param int $typeId
     *
     * @return int
     */
    public function fetchCategoryStatsByTypeId($typeId)
    {
        $select = $this->_getReadAdapter()->select();
        $select
            ->from(
                ['category_summary' => $this->getTable('ecomdev_benchmarkdatatool/category_summary')],
                []
            )
            ->join(
                ['category' => $this->getTable('catalog/category')],
                'category.entity_id = category_summary.category_id',
                []
            )
            ->where('category_summary.type_id = ?', $typeId)
            ->where('category.level >= ?', 2)
        ;

        $minMaxSelect = clone $select;
        $minMaxSelect->columns([
            'type' => 'IF(category_summary.is_anchor, :anchor, :direct)',
            'avg_number_in_category' => 'AVG(category_summary.product_count)',
            'max_number_in_category' => 'MAX(category_summary.product_count)',
            'min_number_in_category' => 'MIN(category_summary.product_count)',
            'min_position_in_category' => 'MIN(category_summary.min_position)',
            'max_position_in_category' => 'MAX(category_summary.max_position)',
            'avg_position_in_category' => 'AVG(category_summary.avg_position)'
        ]);

        $minMaxSelect->group('category_summary.is_anchor');

        $result['position_summary'] = $this->_getReadAdapter()->fetchAssoc(
            $minMaxSelect,
            [
                'direct' => 'direct',
                'anchor' => 'anchor'
            ]
        );

        $select->columns([
            'category_id' => 'category_summary.category_id',
            'min_position' => 'category_summary.min_position',
            'min_id' => 'category_summary.min_id',
            'max_position' => 'category_summary.max_position',
            'max_id' => 'category_summary.max_id',
            'products_assigned' => 'category_summary.product_count',
        ]);

        $select->where('category_summary.is_anchor = ?', 0);
        $select->group('category_summary.category_id');

        $result['category_summary'] = $this->_getReadAdapter()->fetchAssoc($select);
        return $result;
    }
}
