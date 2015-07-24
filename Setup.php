<?php
/**
 * @link http://canis.io/
 *
 * @copyright Copyright (c) 2015 Canis
 * @license http://canis.io/license/
 */

namespace canis\setup;

use Migrator;
use Task;
use Yii;
use yii\helpers\ArrayHelper;

defined('STDOUT') or define('STDOUT', fopen('php://stdout', 'w'));

/**
 * Setup [[@doctodo class_description:canis\setup\Setup]].
 *
 * @author Jacob Morrison <email@ofjacob.com>
 */
abstract class Setup extends \canis\base\Object
{
    /**
     * @var [[@doctodo var_type:_instance]] [[@doctodo var_description:_instance]]
     */
    public static $_instance;
    /**
     * @var [[@doctodo var_type:_migrator]] [[@doctodo var_description:_migrator]]
     */
    public static $_migrator;
    /**
     * @var [[@doctodo var_type:_app]] [[@doctodo var_description:_app]]
     */
    public static $_app;
    /**
     * @var [[@doctodo var_type:basePath]] [[@doctodo var_description:basePath]]
     */
    public $basePath;
    /**
     * @var [[@doctodo var_type:applicationPath]] [[@doctodo var_description:applicationPath]]
     */
    public $applicationPath;
    /**
     * @var [[@doctodo var_type:name]] [[@doctodo var_description:name]]
     */
    public $name = 'Application';
    /**
     * @var [[@doctodo var_type:pageTitle]] [[@doctodo var_description:pageTitle]]
     */
    public $pageTitle = 'Setup';
    /**
     * @var [[@doctodo var_type:applicationNamespace]] [[@doctodo var_description:applicationNamespace]]
     */
    public $applicationNamespace = 'app';
    /**
     * @var [[@doctodo var_type:params]] [[@doctodo var_description:params]]
     */
    public $params = [];
    /**
     * @var [[@doctodo var_type:neededInformation]] [[@doctodo var_description:neededInformation]]
     */
    public $neededInformation = [];

    /**
     * [[@doctodo method_description:createSetupApplication]].
     *
     * @param array $config [[@doctodo param_description:config]] [optional]
     *
     * @return [[@doctodo return_type:createSetupApplication]] [[@doctodo return_description:createSetupApplication]]
     */
    public static function createSetupApplication($config = [])
    {
        defined('YII_DEBUG') or define('YII_DEBUG', true);
        defined('CANIS_APP_SETUP') or define('CANIS_APP_SETUP', true);
        if (is_null(self::$_instance)) {
            self::$_instance = new static($config);
        }

        return self::$_instance;
    }



    /**
     * @inheritdoc
     */
    public function __construct($config = [])
    {
        foreach ($config as $k => $v) {
            $this->{$k} = $v;
        }
        if (is_null($this->basePath)) {
            $this->basePath = dirname(dirname(__FILE__));
        }
    }

    /**
     * [[@doctodo method_description:beforeRun]].
     *
     * @return [[@doctodo return_type:beforeRun]] [[@doctodo return_description:beforeRun]]
     */
    public function beforeRun()
    {
        return true;
    }

    /**
     * [[@doctodo method_description:afterRun]].
     *
     * @return [[@doctodo return_type:afterRun]] [[@doctodo return_description:afterRun]]
     */
    public function afterRun()
    {
        return true;
    }

    /**
     * [[@doctodo method_description:run]].
     *
     * @return [[@doctodo return_type:run]] [[@doctodo return_description:run]]
     */
    public function run()
    {
        $self = $this;
        $tasks = $this->setupTasks;
        $currentTask = null;
        $tasksLeft = count($tasks);
        $tasksDone = 0;
        $message = 'set up and is up-to-date';
        if ($this->isEnvironmented) {
            $message = 'updated successfully';
        }
        $skip = [];
        if (isset($_GET['skip'])) {
            $skip = explode(',', $_GET['skip']);
        }
        $newSkip = false;
        foreach ($tasks as $task) {
            if (!$this->beforeRun()) {
                $this->render('message');
                return false;
            }
            if (defined('CANIS_SETUP_DB_READY') && CANIS_SETUP_DB_READY) {
                $this->app(); //initialize the app
            }
            if (in_array($task->id, $skip) && $task->skipComplete) {
                $task->skip();
                continue;
            }
            // try {
                $tasksLeft--;
            if ($task->test()) {
                if ($task->skipComplete) {
                    $skip[] = $task->id;
                    $newSkip = true;
                }
                continue;
            }
            if (!empty($task->verification) && !$this->getConfirmed($task->id)) {
                // show confirm link
                    $this->params['question'] = $task->verification;
                $this->params['task'] = $task;
                $this->render('confirm');
            } elseif (!empty($task->fields) && (empty($_POST[$task->id]) || !$this->getConfirmed($task->id) || !$task->loadInput($_POST[$task->id]))) {
                // show form
                    $this->params['fields'] = $task->fields;
                $this->params['task'] = $task;
                $this->render('form');
            } else {
                // do it
                    if (!$task->run()) {
                        $this->params['message'] = "An error occurred while running the task <em>{$task->title}</em>";
                        $this->params['errors'] = $task->errors;
                        $this->params['error'] = true;
                        $this->render('message');
                    } else {
                        $this->afterRun();
                        $tasksDone++;
                        if ($task->skipComplete) {
                            $skip[] = $task->id;
                            $newSkip = true;
                        }
                        if (!empty($task->verification) || !empty($task->fields) || $newSkip) {
                            $this->refresh("Successfully completed the task <em>{$task->title}</em>", $skip);
                            break;
                        }
                    }
            }
            // } catch (Exception $e) {

            //     $message = 'Fatal error: '. $e->getFile() .':'. $e->getLine() .' '. $e->getMessage();
            //     $this->params['message'] = $message;
            //     $this->params['error'] = true;
            //     $this->render('message');
            //     break;
            // }
        }

        $this->params['message'] = "Your application has been {$message}!";
        $this->params['forceContinue'] = true;
        $this->render('message');

        return true;
    }

    /**
     * [[@doctodo method_description:refresh]].
     *
     * @param [[@doctodo param_type:message]] $message [[@doctodo param_description:message]] [optional]
     * @param boolean                         $skip    [[@doctodo param_description:skip]] [optional]
     */
    public function refresh($message = null, $skip = false)
    {
        //echo '<pre>';var_dump($_SERVER);exit;
        $url = $_SERVER['SCRIPT_NAME'] . '?message=' . $message;
        if (isset($skip)) {
            $url .= '&skip=' . implode(',', $skip);
        }
        header('Location: ' . $url);
        exit(0);
    }

    /**
     * Get is setup.
     *
     * @return [[@doctodo return_type:getIsSetup]] [[@doctodo return_description:getIsSetup]]
     */
    public function getIsSetup()
    {
        // if (!$this->isEnvironmented) { return false; }
        // if ($this->version > $this->instanceVersion) { return false; }
        // if ($this->migrator->check()) { return false; }
        // if (!$this->app()) { return false; }

        $steps = [];

        return true;
    }

    /**
     * Get setup tasks config.
     *
     * @return array of object configurations
     */
    abstract public function getSetupTaskConfig();

    /**
     * Get setup tasks.
     *
     * @return [[@doctodo return_type:getSetupTasks]] [[@doctodo return_description:getSetupTasks]]
     */
    public function getSetupTasks()
    {
        static $tasks;
        if ($tasks === null) {
            $tasks = [];
            $priority = 1;
            foreach ($this->getSetupTaskConfig() as $task) {
                if (!isset($task['priority'])) {
                    $task['priority'] = $priority;
                }
                $task['setup'] = $this;
                $tasks[] = Yii::createObject($task);
                $priority++;
            }
            ArrayHelper::multisort($tasks, 'prioritySort', SORT_ASC);
        }
        // echo '<pre>'; var_dump(ArrayHelper::map($tasks, 'title', 'prioritySort'));exit;
        return $tasks;
    }

    /**
     * Get is available.
     *
     * @return [[@doctodo return_type:getIsAvailable]] [[@doctodo return_description:getIsAvailable]]
     */
    public function getIsAvailable()
    {
        if (!$this->isEnvironmented) {
            return false;
        }
        if ($this->version > $this->instanceVersion) {
            return false;
        }

        return true;
    }

    /**
     * [[@doctodo method_description:markDbReady]].
     *
     * @return [[@doctodo return_type:markDbReady]] [[@doctodo return_description:markDbReady]]
     */
    public function markDbReady()
    {
        if (!defined('CANIS_SETUP_DB_READY')) {
            self::$_app = null;
            define('CANIS_SETUP_DB_READY', true);
        }

        return true;
    }

    /**
     * Get confirm link.
     *
     * @param [[@doctodo param_type:task]] $task [[@doctodo param_description:task]]
     *
     * @return [[@doctodo return_type:getConfirmLink]] [[@doctodo return_description:getConfirmLink]]
     */
    public function getConfirmLink($task)
    {
        return $_SERVER['REQUEST_URI'] . '?task=' . $task . '&confirm=' . $this->getConfirmSalt($task);
    }

    /**
     * Get confirm salt.
     *
     * @param [[@doctodo param_type:task]] $task [[@doctodo param_description:task]] [optional]
     *
     * @return [[@doctodo return_type:getConfirmSalt]] [[@doctodo return_description:getConfirmSalt]]
     */
    public function getConfirmSalt($task = null)
    {
        return md5(date("Y-m-d") . __FILE__ . ':' . $task);
    }

    /**
     * Get confirmed.
     *
     * @param [[@doctodo param_type:task]] $task [[@doctodo param_description:task]]
     *
     * @return [[@doctodo return_type:getConfirmed]] [[@doctodo return_description:getConfirmed]]
     */
    public function getConfirmed($task)
    {
        $confirm = null;
        if (isset($_GET['confirm'])) {
            $confirm = $_GET['confirm'];
        }
        if (isset($_POST['confirm'])) {
            $confirm = $_POST['confirm'];
        }
        if (isset($confirm) and $confirm === $this->getConfirmSalt($task)) {
            return true;
        }

        return false;
    }

    /**
     * Get version.
     *
     * @return [[@doctodo return_type:getVersion]] [[@doctodo return_description:getVersion]]
     */
    public function getVersion()
    {
        return trim(file_get_contents($this->basePath . DIRECTORY_SEPARATOR . 'VERSION'));
    }

    /**
     * Get instance version.
     *
     * @return [[@doctodo return_type:getInstanceVersion]] [[@doctodo return_description:getInstanceVersion]]
     */
    public function getInstanceVersion()
    {
        if ($this->isEnvironmented) {
            return CANIS_APP_INSTANCE_VERSION;
        }

        return false;
    }

    /**
     * [[@doctodo method_description:app]].
     *
     * @throws Exception [[@doctodo exception_description:Exception]]
     * @return [[@doctodo return_type:app]] [[@doctodo return_description:app]]
     *
     */
    public function app()
    {
        if ($this->isEnvironmented) {
            if (is_null(self::$_app)) {
                $configPath = $this->environmentPath . DIRECTORY_SEPARATOR . 'console.php';
                if (!file_exists($configPath)) {
                    throw new Exception("Couldn't find environment config {$configPath}!");
                }
                $_SERVER['argv'] = [];
                $config = include $configPath;
                if (isset($config['components']['collectors'])) {
                    $config['components']['collectors']['cacheTime'] = false;
                }
                self::$_app = Yii::$app = new \canis\console\Application($config);
                Yii::$app->trigger(\yii\base\Application::EVENT_BEFORE_REQUEST);
            }

            return self::$_app;
        }

        return false;
    }

    /**
     * Get is environmented.
     *
     * @return [[@doctodo return_type:getIsEnvironmented]] [[@doctodo return_description:getIsEnvironmented]]
     */
    public function getIsEnvironmented()
    {
        if (file_exists($this->environmentFilePath)) {
            include_once $this->environmentFilePath;
        }
        if (isset($_GET['reset'])) {
            //  return false; // don't want to let this just sit here. could be a big security risk.
        }

        return defined('CANIS_APP_INSTANCE_VERSION') && CANIS_APP_INSTANCE_VERSION !== false;
    }

    /**
     * Get environment path.
     *
     * @return [[@doctodo return_type:getEnvironmentPath]] [[@doctodo return_description:getEnvironmentPath]]
     */
    public function getEnvironmentPath()
    {
        if ($this->isEnvironmented) {
            return CANIS_APP_CONFIG_PATH;
        }

        return false;
    }

    /**
     * Get config path.
     *
     * @throws Exception [[@doctodo exception_description:Exception]]
     * @return [[@doctodo return_type:getConfigPath]] [[@doctodo return_description:getConfigPath]]
     *
     */
    public function getConfigPath()
    {
        $path = $this->basePath . DIRECTORY_SEPARATOR . 'config';
        if (!is_dir($path)) {
            throw new Exception("Config path does not exist: {$path}");
        }

        return $path;
    }

    /**
     * Get environment file path.
     *
     * @return [[@doctodo return_type:getEnvironmentFilePath]] [[@doctodo return_description:getEnvironmentFilePath]]
     */
    public function getEnvironmentFilePath()
    {
        $path = $this->configPath . DIRECTORY_SEPARATOR . 'env.php';

        return $path;
    }

    /**
     * Get environment template file path.
     *
     * @return [[@doctodo return_type:getEnvironmentTemplateFilePath]] [[@doctodo return_description:getEnvironmentTemplateFilePath]]
     */
    public function getEnvironmentTemplateFilePath()
    {
        $path = $this->environmentFilePath . '.sample';

        return $path;
    }

    /**
     * Get library config path.
     *
     * @throws Exception [[@doctodo exception_description:Exception]]
     * @return [[@doctodo return_type:getLibraryConfigPath]] [[@doctodo return_description:getLibraryConfigPath]]
     *
     */
    public function getLibraryConfigPath()
    {
        $path = CANIS_APP_PATH . DIRECTORY_SEPARATOR . 'config';
        if (!is_dir($path)) {
            throw new Exception("Library config path does not exist: {$path}");
        }

        return $path;
    }

    /**
     * Get common config path.
     *
     * @throws Exception [[@doctodo exception_description:Exception]]
     * @return [[@doctodo return_type:getCommonConfigPath]] [[@doctodo return_description:getCommonConfigPath]]
     *
     */
    public function getCommonConfigPath()
    {
        $path = $this->libraryConfigPath . DIRECTORY_SEPARATOR . 'common';
        if (!is_dir($path)) {
            throw new Exception("Base environment path does not exist: {$path}");
        }

        return $path;
    }

    /**
     * Get environment templates path.
     *
     * @throws Exception [[@doctodo exception_description:Exception]]
     * @return [[@doctodo return_type:getEnvironmentTemplatesPath]] [[@doctodo return_description:getEnvironmentTemplatesPath]]
     *
     */
    public function getEnvironmentTemplatesPath()
    {
        $path = $this->libraryConfigPath . DIRECTORY_SEPARATOR . 'templates';
        if (!is_dir($path)) {
            throw new Exception("Environment templates path does not exist: {$path}");
        }

        return $path;
    }

    /**
     * [[@doctodo method_description:render]].
     *
     * @param [[@doctodo param_type:view]] $view [[@doctodo param_description:view]]
     *
     * @throws Exception [[@doctodo exception_description:Exception]]
     */
    public function render($view)
    {
        $basePath = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'views';
        $viewFile = $basePath . DIRECTORY_SEPARATOR . $view . '.php';
        $layoutFile = $basePath . DIRECTORY_SEPARATOR . 'layout.php';
        if (!file_exists($viewFile)) {
            throw new Exception("Invalid setup view file!");
        }
        if (!file_exists($layoutFile)) {
            throw new Exception("Invalid setup layout file!");
        }
        foreach ($this->params as $k => $v) {
            $$k = $v;
        }
        ob_start();
        include $viewFile;
        $content = ob_get_clean();
        ob_start();
        include $layoutFile;
        ob_end_flush();
        exit(0);
    }
}
