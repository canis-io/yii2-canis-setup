<?php
/**
 * @link http://canis.io/
 *
 * @copyright Copyright (c) 2015 Canis
 * @license http://canis.io/license/
 */

namespace canis\setup\tasks;

use canis\setup\Exception;

class Database extends BaseTask
{
	/**
     */
    protected $_migrator;
    /**
     * @inheritdoc
     */
    public $skipComplete = true;

    /**
     * @inheritdoc
     */
    public function getTitle()
    {
        return 'Database';
    }

    /**
     * @inheritdoc
     */
    public function skip()
    {
        return parent::skip() && $this->setup->markDbReady();
    }

    /**
     * @inheritdoc
     */
    public function test()
    {
        if ($this->isNewInstall()) {
            return false;
        }
        $request = $this->migrator->getRequest();
        $request->setParams(['migrate/new-plain', '--interactive=0', 1000]);
        list($route, $params) = $request->resolve();
        ob_start();
        $this->migrator->run();
        $result = ob_get_clean();
        //var_dump($result);exit;
        preg_match('/Found ([0-9]+) new migration/', $result, $matches);
        if (empty($matches[1])) {
            return true;
        }
        $numberMatches = (int) $matches[1];

        return $numberMatches === 0;
    }

    /**
     *
     */
    public function isNewInstall()
    {
        if (count($this->setup->app()->db->schema->tableNames) < 2) {
            return true;
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    public function run()
    {
        $request = $this->migrator->getRequest();
        $request->setParams(['migrate/up-plain', '--interactive=0']);
        ob_start();
        $this->migrator->run();
        $result = ob_get_clean();

        return preg_match('/Migrated up successfully./', $result) === 1;
    }

    /**
     * Get migrator.
     */
    public function getMigrator()
    {
        if (is_null($this->_migrator)) {
            $configFile = $this->setup->environmentPath . DIRECTORY_SEPARATOR . 'console.php';
            if (!is_file($configFile)) {
                throw new Exception("Invalid console config path: {$configFile}");
            }
            $config = require $configFile;
            //var_dump($config);exit;
            $this->_migrator = new \canis\console\Application($config);
        }

        return $this->_migrator;
    }

    /**
     * @inheritdoc
     */
    public function getVerification()
    {
        if (!$this->isNewInstall() && !$this->test()) {
            return 'There are database upgrades available. Would you like to upgrade the database now?';
        }
        return false;
    }
}