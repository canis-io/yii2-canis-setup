<?php
/**
 * @link http://canis.io/
 *
 * @copyright Copyright (c) 2015 Canis
 * @license http://canis.io/license/
 */

namespace canis\setup\tasks;

use canis\setup\Exception;
use canis\composer\TwigRender;
use yii\helpers\Inflector;
use yii\helpers\FileHelper;

class Environment extends BaseTask
{
		/**
     * @inheritdoc
     */
    public function getTitle()
    {
        return 'Environment';
    }

    /**
     * @inheritdoc
     */
    public function test()
    {
        if ($this->setup->isEnvironmented) {
            try {
                $oe = ini_set('display_errors', 0);
                $dbh = new \PDO('mysql:host=' . CANIS_APP_DATABASE_HOST . ';port=' . CANIS_APP_DATABASE_PORT . ';dbname=' . CANIS_APP_DATABASE_DBNAME, CANIS_APP_DATABASE_USERNAME, CANIS_APP_DATABASE_PASSWORD);
                ini_set('display_errors', $oe);
            } catch (\Exception $e) {
                throw new Exception("Unable to connect to db! Please verify your settings in <code>env.php</code>.");
            }
        }

        return $this->setup->isEnvironmented && $this->setup->version <= $this->setup->instanceVersion;
    }

    /**
     *
     */
    protected static function generateRandomString()
    {
        if (!extension_loaded('openssl')) {
            throw new \Exception('The OpenSSL PHP extension is required by Yii2.');
        }
        $length = 120;
        $bytes = openssl_random_pseudo_bytes($length);
        return strtr(substr(base64_encode($bytes), 0, $length), '+/=', '_-.');
    }

    /**
     * @inheritdoc
     */
    public function run()
    {
        if ($this->fields) {
            $input = $this->input;
            $upgrade = false;
        } else {
            $input = [];
            $input['app'] = [];
            $input['app']['name'] = $this->setup->app()->name;
            $input['app']['template'] = 'development';

            $input['db'] = [];
            $input['db']['host'] = CANIS_APP_DATABASE_HOST;
            $input['db']['port'] = CANIS_APP_DATABASE_PORT;
            $input['db']['username'] = CANIS_APP_DATABASE_USERNAME;
            $input['db']['password'] = CANIS_APP_DATABASE_PASSWORD;
            $input['db']['dbname'] = CANIS_APP_DATABASE_DBNAME;

            if (defined('CANIS_APP_REDIS_HOST')) {
                $input['redis'] = [];
                $input['redis']['host'] = CANIS_APP_REDIS_HOST;
                $input['redis']['port'] = CANIS_APP_REDIS_PORT;
                $input['redis']['database'] = CANIS_APP_REDIS_DATABASE;
            }
            $upgrade = true;
        }

        $input['templateDirectory'] = $this->setup->environmentTemplatesPath . DIRECTORY_SEPARATOR . $input['app']['template'];
        $input['version'] = $this->setup->version;
        $input['app']['id'] = self::generateId($input['app']['name']);
        if ($this->setup->app()) {
            $input['salt'] = $this->setup->app()->params['salt'];
            $input['cookieValidationString'] = $this->setup->app()->request['cookieValidationKey'];
        }
        if (empty($input['salt'])) {
            $input['salt'] = static::generateRandomString();
        }
        if (empty($input['cookieValidationString'])) {
            $input['cookieValidationString'] = static::generateRandomString();
        }

        if (!$this->initEnv($input) || !file_exists($this->setup->environmentFilePath)) {
            $this->errors[] = 'Unable to set up environment (Env file: '. $this->setup->environmentFilePath .')';
            return false;
        }
        // if ($upgrade) {
        //     return true;
        // }

        return true;
    }

    public function initEnv($env)
    {
        $configDirectory = CANIS_APP_CONFIG_PATH;
        $templateDirectory = $env['templateDirectory'];
        $renderer = new TwigRender();
        $parser = function($file) use ($env, $renderer) {
            $content = file_get_contents($file);
            return $renderer->renderContent($content, $env);
        };

        $findOptions = [];
        $findOptions['only'] = ['*.sample'];
        $files = FileHelper::findFiles($templateDirectory, $findOptions);
        foreach ($files as $file) {
            $newFilePath = strtr($file, [$templateDirectory => $configDirectory, '.sample' => '']);
            if ($newFilePath === $file) { continue; }
            $newFileDir = dirname($newFilePath);
            if (!is_dir($newFileDir)) {
                mkdir($newFileDir, 0755, true);
            }
            $newContent = $parser($file);
            file_put_contents($newFilePath, $newContent);
            if (!is_file($newFilePath)) {
                return false;
            }
        }
        return true;
    }

    /**
     *
     */
    public static function generateId($name)
    {
        return strtolower(Inflector::slug($name));
    }

    /**
     * @inheritdoc
     */
    public function loadInput($input)
    {
        if (!parent::loadInput($input)) {
            return false;
        }
        try {
            $oe = ini_set('display_errors', 0);
            $dbh = new \PDO('mysql:host=' . $this->input['db']['host'] . ';port=' . $this->input['db']['port'] . ';dbname=' . $this->input['db']['dbname'], $this->input['db']['username'], $this->input['db']['password']);
            ini_set('display_errors', $oe);
        } catch (\Exception $e) {
            $fieldId = 'field_' . $this->id . '_db_host';
            $this->fieldErrors[$fieldId] = 'Error connecting to db: ' . $e->getMessage();

            return false;
        }

        return true;
    }

    /**
     * Get env options.
     */
    public function getEnvOptions()
    {
        $envs = [];
        $templatePath = $this->setup->environmentTemplatesPath;
        $o = opendir($templatePath);
        while (($file = readdir($o)) !== false) {
            $path = $templatePath . DIRECTORY_SEPARATOR . $file;
            if (substr($file, 0, 1) === '.' or !is_dir($path)) {
                continue;
            }
            $envs[$file] = $path;
        }
        //var_dump($envs);
        return $envs;
    }

    /**
     * Get env list options.
     */
    public function getEnvListOptions()
    {
        $options = $this->envOptions;
        $list = [];
        foreach ($options as $k => $v) {
            $list[$k] = ucwords($k);
        }

        return $list;
    }

    /**
     * @inheritdoc
     */
    public function getFields()
    {
        if ($this->setup->isEnvironmented && $this->setup->app()) {
            return false;
        }

        $fields = [];
        $fields['app'] = ['label' => 'General', 'fields' => []];
        $fields['app']['fields']['template'] = ['type' => 'select', 'options' => $this->envListOptions, 'label' => 'Environment', 'required' => true, 'value' => function () { return defined('CANIS_APP_ENVIRONMENT') ? CANIS_APP_ENVIRONMENT : 'development'; }];
        $fields['app']['fields']['name'] = ['type' => 'text', 'label' => 'Application Name', 'required' => true, 'value' => function () { return $this->setup->name; }];

        $fields['db'] = ['label' => 'Database', 'fields' => []];
        $fields['db']['fields']['host'] = ['type' => 'text', 'label' => 'Host', 'required' => true, 'value' => function () { return defined('CANIS_APP_DATABASE_HOST') && CANIS_APP_DATABASE_HOST ? CANIS_APP_DATABASE_HOST : '127.0.0.1'; }];
        $fields['db']['fields']['port'] = ['type' => 'text', 'label' => 'Port', 'required' => true, 'value' => function () { return defined('CANIS_APP_DATABASE_PORT') && CANIS_APP_DATABASE_PORT ? CANIS_APP_DATABASE_PORT : '3306'; }];
        $fields['db']['fields']['username'] = ['type' => 'text', 'label' => 'Username', 'required' => true, 'value' => function () { return defined('CANIS_APP_DATABASE_USERNAME') && CANIS_APP_DATABASE_USERNAME ? CANIS_APP_DATABASE_USERNAME : ''; }];
        $fields['db']['fields']['password'] = ['type' => 'text', 'label' => 'Password', 'required' => true, 'value' => function () { return defined('CANIS_APP_DATABASE_PASSWORD') && CANIS_APP_DATABASE_PASSWORD ? '' : ''; }];
        $fields['db']['fields']['dbname'] = ['type' => 'text', 'label' => 'Database Name', 'required' => true, 'value' => function () { return defined('CANIS_APP_DATABASE_DBNAME') && CANIS_APP_DATABASE_DBNAME ? CANIS_APP_DATABASE_DBNAME : ''; }];

        if (defined('CANIS_APP_REDIS_HOST')) {
            $fields['redis'] = ['label' => 'Redis Cache', 'fields' => []];
            $fields['redis']['fields']['host'] = ['type' => 'text', 'label' => 'Host', 'required' => true, 'value' => function () { return defined('CANIS_APP_DATABASE_HOST') && CANIS_APP_REDIS_HOST ? CANIS_APP_REDIS_HOST : '127.0.0.1'; }];
            $fields['redis']['fields']['port'] = ['type' => 'text', 'label' => 'Port', 'required' => true, 'value' => function () { return defined('CANIS_APP_DATABASE_PORT') && CANIS_APP_REDIS_PORT ? CANIS_APP_REDIS_PORT : '6380'; }];
            // $fields['redis']['fields']['username'] = ['type' => 'text', 'label' => 'Username', 'required' => true, 'value' => function () { return defined('CANIS_APP_DATABASE_USERNAME') && CANIS_APP_DATABASE_USERNAME ? CANIS_APP_DATABASE_USERNAME : ''; }];
            // $fields['redis']['fields']['password'] = ['type' => 'text', 'label' => 'Password', 'required' => true, 'value' => function () { return defined('CANIS_APP_DATABASE_PASSWORD') && CANIS_APP_DATABASE_PASSWORD ? '' : ''; }];
            // $fields['redis']['fields']['database'] = ['type' => 'text', 'label' => 'Database Name', 'required' => true, 'value' => function () { return defined('CANIS_APP_DATABASE_DBNAME') && CANIS_APP_DATABASE_DBNAME ? CANIS_APP_DATABASE_DBNAME : ''; }];
        }
        return $fields;
    }
}