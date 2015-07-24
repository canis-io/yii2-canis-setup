<?php
/**
 * @link http://canis.io/
 *
 * @copyright Copyright (c) 2015 Canis
 * @license http://canis.io/license/
 */

namespace canis\setup\tasks;

/**
 * Task [[@doctodo class_description:canis\setup\Task]].
 *
 * @author Jacob Morrison <email@ofjacob.com>
 */
abstract class BaseTask extends \canis\base\Object
{
    /**
     * @var [[@doctodo var_type:_setup]] [[@doctodo var_description:_setup]]
     */
    protected $_setup;
    /**
     * @var [[@doctodo var_type:errors]] [[@doctodo var_description:errors]]
     */
    public $errors = [];
    /**
     * @var [[@doctodo var_type:fieldErrors]] [[@doctodo var_description:fieldErrors]]
     */
    public $fieldErrors = [];
    /**
     * @var [[@doctodo var_type:input]] [[@doctodo var_description:input]]
     */
    public $input = [];
    /**
     * @var [[@doctodo var_type:skipComplete]] [[@doctodo var_description:skipComplete]]
     */
    public $skipComplete = false;

    public $priority = 1;

    /**
     * @inheritdoc
     */
    public function init()
    {
        if (!isset($this->_setup)) {
            throw new \Exception("Task wasn't given a setup object");
        }
    }

    public function getPrioritySort()
    {
        return $this->priority .'-'. md5(get_class($this));
    }

    /**
     * Get setup.
     *
     * @return [[@doctodo return_type:getSetup]] [[@doctodo return_description:getSetup]]
     */
    public function getSetup()
    {
        return $this->_setup;
    }

    public function setSetup($setup)
    {
        $this->_setup = $setup;
        return $this;
    }

    /**
     * Get id.
     *
     * @return [[@doctodo return_type:getId]] [[@doctodo return_description:getId]]
     */
    public function getId()
    {
        return self::baseClassName();
    }

    /**
     * [[@doctodo method_description:skip]].
     *
     * @return [[@doctodo return_type:skip]] [[@doctodo return_description:skip]]
     */
    public function skip()
    {
        return true;
    }

    /**
     * [[@doctodo method_description:loadInput]].
     *
     * @param [[@doctodo param_type:input]] $input [[@doctodo param_description:input]]
     *
     * @return [[@doctodo return_type:loadInput]] [[@doctodo return_description:loadInput]]
     */
    public function loadInput($input)
    {
        $error = false;
        $this->input = [];
        foreach ($this->fields as $fieldset => $fields) {
            $this->input[$fieldset] = [];
            foreach ($fields['fields'] as $key => $settings) {
                $fieldId = 'field_' . $this->id . '_' . $fieldset . '_' . $key . '';

                $this->input[$fieldset][$key] = null;

                if (!empty($input[$fieldset][$key])) {
                    $this->input[$fieldset][$key] = $input[$fieldset][$key];
                } elseif (!empty($settings['required'])) {
                    $this->fieldErrors[$fieldId] = $settings['label'] . ' is a required field';
                    $error = true;
                }
            }
        }

        return !$error;
    }

    /**
     * Get title.
     */
    abstract public function getTitle();
    /**
     * [[@doctodo method_description:test]].
     */
    abstract public function test();
    /**
     * [[@doctodo method_description:run]].
     */
    abstract public function run();
    /**
     * Get fields.
     *
     * @return [[@doctodo return_type:getFields]] [[@doctodo return_description:getFields]]
     */
    public function getFields()
    {
        return false;
    }
    /**
     * Get verification.
     *
     * @return [[@doctodo return_type:getVerification]] [[@doctodo return_description:getVerification]]
     */
    public function getVerification()
    {
        return false;
    }

    /**
     * [[@doctodo method_description:templatize]].
     *
     * @param unknown $template
     * @param array   $vars     [[@doctodo param_description:vars]] [optional]
     *
     * @return unknown
     */
    public function templatize($template, $vars = [])
    {
        global $setup;
        $tmp = [];
        foreach ($setup->vars as $name => $value) {
            if (!is_string($value) and !is_numeric($value) and is_callable($value)) {
                $value = $value();
            }
            if (!is_array($value)) {
                $tmp["/\%\%" . $name . "\%\%/i"] = preg_replace('#(\\$|\\\\)#', '\\\\$1', $value);
            }
        }
        $tmp["/\%\%.*\%\%/i"] = '';
        $template = preg_replace(
            array_keys($tmp),
            array_values($tmp),
            $template
        );

        return $template;
    }

    /**
     * [[@doctodo method_description:parseText]].
     *
     * @param [[@doctodo param_type:text]] $text      [[@doctodo param_description:text]]
     * @param array                        $variables [[@doctodo param_description:variables]] [optional]
     *
     * @return [[@doctodo return_type:parseText]] [[@doctodo return_description:parseText]]
     */
    public static function parseText($text, $variables = [])
    {
        if (is_object($text)) {
            return $text;
        }
        preg_match_all("/\%\%([^\%]+)\%\%/i", $text, $extracted);
        $replace = [];
        if (!empty($extracted)) {
            foreach ($extracted[0] as $k => $v) {
                $key = '/' . $v . '/';
                $parse = $extracted[1][$k];
                $replace[$key] = null;
                $instructions = explode('.', $parse);

                $placementItem = $variables;
                while (!empty($placementItem) and is_array($placementItem) and !empty($instructions)) {
                    $nextInstruction = array_shift($instructions);
                    if (isset($placementItem[$nextInstruction])) {
                        $placementItem = $placementItem[$nextInstruction];
                    } else {
                        $placementItem = null;
                    }
                }
                $replace[$key] = (string) $placementItem;
            }
        } else {
            return $text;
        }

        return trim(preg_replace(array_keys($replace), array_values($replace), $text));
    }
}
