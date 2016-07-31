<?php

/**
 * Shell script for data reduction
 *
 */
class EcomDev_BenchmarkDataTool_Shell extends Mage_Shell_Abstract
{
    /**
     * Script action
     *
     * @var string
     */
    protected $_action;

    /**
     * Do not include Mage class via constructor
     *
     * @var bool
     */
    protected $_includeMage = false;

    /**
     * Map of arguments for shell script,
     * for making possible using shortcuts
     *
     * @var array
     */
    protected $_actionArgsMap = [
        'generate' => [],
        'summary' => [],
        'fix:visibility' => [],
        'fix:simple' => [
            'percent' => 'p'
        ],
        'fix:category:position' => [],
        'csv:product:url' => [
            'store'   => 's',
            'type'    => 't'
        ],
        'csv:category:url' => [
            'store'   => 's',
            'limit'   => 'l',
            'limit-name' => 'n'
        ]
    ];

    /**
     * Retrieve Usage Help Message
     *
     */
    public function usageHelp()
    {
        $baseFile = $_SERVER['SCRIPT_FILENAME'];
        return <<<USAGE
Usage:  php -f $baseFile -- <action> <options>

  -h --help             Shows usage

Defined <action>s:

  generate              Generates table with information about products and their stats
   
  summary               Information about products in the database in JSON format
  
  fix:visibility        Fixes product database by fixing visibility of simple products,
                        that are assigned to configurable but does not assigned to category
  
  fix:simple            Fixes product database by removing wrongly assigned simple products to categories, 
                        while they are in configurable
                        
    -p --percent        Percent of configurable to leave assigned into category
  
  fix:category:position Fixes category product position to randomise it 
  
  csv:product:url       Outputs products url CSV that can be used for load test. Output stream is STDOUT
    -t --type           Type of the product to output. Defaults to "simple"
    -s --store          Store code for which to generate those urls

  csv:category:url       Outputs products url CSV that can be used for load test. Output stream is STDOUT
    -s --store          Store code for which to generate those urls
    -l --limit          List of various limits to try. Default to "24,36"
    -n --limit-name     Name of limit parameter. Default to "limit"
USAGE;
    }

    /**
     * Parses actions for shell script
     *
     */
    protected function _parseArgs()
    {
        foreach ($_SERVER['argv'] as $index => $argument) {
            if (isset($this->_actionArgsMap[$argument])) {
                $this->_action = $argument;
                unset($_SERVER['argv'][$index]);
                break;
            }
            unset($_SERVER['argv'][$index]);
        }

        parent::_parseArgs();
    }

    /**
     * Retrieves arguments (with map)
     *
     * @param string $name
     * @param mixed $defaultValue
     * @return mixed|bool
     */
    public function getArg($name, $defaultValue = false)
    {
        if (parent::getArg($name) !== false) {
            return parent::getArg($name);
        }

        if ($this->_action && isset($this->_actionArgsMap[$this->_action][$name])) {
            $value = parent::getArg($this->_actionArgsMap[$this->_action][$name]);
            if ($value === false) {
                return $defaultValue;
            }
            return $value;
        }

        return $defaultValue;
    }

    /**
     * Runs scripts itself
     *
     */
    public function run()
    {
        if ($this->_action === null) {
            die($this->usageHelp());
        }

        $reflection = new ReflectionClass(__CLASS__);
        $methodName = 'run' . uc_words($this->_action, '', ':');
        if ($reflection->hasMethod($methodName)) {
            try {
                Mage::app('admin');
                exit((int)$this->$methodName());
            } catch (Exception $e) {
                fwrite(STDERR, "Error: \n{$e->getMessage()}\n");
                exit(1);
            }
        } else {
            die($this->usageHelp());
        }
    }

    private function stdout($string)
    {
        fwrite(STDOUT, $string . "\n");
        return $this;
    }

    public function runGenerate()
    {
        Mage::getResourceSingleton('ecomdev_benchmarkdatatool/generator')
            ->generateDataIndex()
            ->generateCategorySummary()
        ;

        $this->stdout('Data is generated');
    }

    public function runSummary()
    {
        $this->stdout(
            json_encode(Mage::getSingleton('ecomdev_benchmarkdatatool/info')->getSummary(), JSON_PRETTY_PRINT)
        );
    }

    public function runFixVisibility()
    {
        Mage::getResourceSingleton('ecomdev_benchmarkdatatool/fix')
            ->fixVisibleButWithoutCategoryAndInConfigurable();

        $this->stdout('Products has been fixed');
        return $this->runGenerate();
    }

    public function runFixSimple()
    {
        $percentToLeave = (int)$this->getArg('percent');
        if (!$percentToLeave) {
            $this->stdout('Please specify percent of products to leave via -p (--percent parameter)');
            return 1;
        }

        Mage::getResourceSingleton('ecomdev_benchmarkdatatool/fix')
            ->fixSimpleProductThatAreAssignedIntoCategoryAndConfigurable($percentToLeave);

        $this->stdout('Products has been fixed');
        return $this->runGenerate();
    }

    public function runFixCategoryPosition()
    {
        Mage::getResourceSingleton('ecomdev_benchmarkdatatool/fix')
            ->fixProductCategoryPosition();

        $this->stdout('Category position has been fixed');
        return $this->runGenerate();
    }

    public function runCsvProductUrl()
    {
        $store = Mage::app()->getStore($this->getArg('store', true));

        $iterator = Mage::getResourceSingleton('ecomdev_benchmarkdatatool/url')->fetchProductAsIterator(
            $this->getArg('type', 'simple'),
            $store->getId()
        );
        
        $csv = \League\Csv\Writer::createFromPath('php://output', 'w');

        $csv->insertOne(['product_id', 'url']);
        foreach ($iterator as $row) {
            $csv->insertOne([$row['product_id'], $row['url']]);
        }
    }

    public function runCsvCategoryUrl()
    {
        $store = Mage::app()->getStore($this->getArg('store', true));
        $limits = array_filter(explode(',', $this->getArg('limit', '24,36')));
        $limitName = $this->getArg('limit-name', 'limit');

        $iterator = Mage::getResourceSingleton('ecomdev_benchmarkdatatool/url')->fetchCategoryAsIterator(
            $store->getId(),
            $store->getRootCategoryId()
        );

        $csv = \League\Csv\Writer::createFromPath('php://output', 'w');

        $csv->insertOne(['category_id', 'url']);
        foreach ($iterator as $row) {
            $urls = [$row['url']];

            foreach ($limits as $limit) {
                $urls[] = $row['url'] . '?' . $limitName . '=' . $limit;
            }

            foreach ($urls as $url) {
                $csv->insertOne([$row['category_id'], $url]);
            }
        }
    }
}
