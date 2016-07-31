<?php

class EcomDev_BenchmarkDataTool_Model_Info
    extends Mage_Core_Model_Abstract
{
    protected function _construct()
    {
        $this->_init('ecomdev_benchmarkdatatool/info');
    }

    /**
     * Returns summary of database information
     * 
     * @return array
     */
    public function getSummary()
    {
        $summary = [];

        foreach (Mage::getSingleton('catalog/product_type')->getOptionArray() as $type => $label) {
            $summary[$type] = ['label' => $label] 
                + $this->_getResource()->fetchProductStatsByTypeId($type)
                + $this->_getResource()->fetchCategoryStatsByTypeId($type);
        }

        return $summary;
    }
}
