<?php

namespace Buuum;


class Validation
{

    /**
     * @var bool
     */
    private $error = false;
    /**
     * @var array
     */
    private $errors = [];
    /**
     * @var array
     */
    private $apply_rules = [];
    /**
     * @var array|mixed
     */
    private $messages = [];
    /**
     * @var array|null
     */
    private $alias = [];

    /**
     * @var array
     */
    private $special = ['required'];

    /**
     * Validation constructor.
     * @param $rules
     * @param array|null $messages
     * @param null $alias
     */
    public function __construct($rules, array $messages = null, $alias = null)
    {
        $this->parseRules($rules);
        $this->messages = $messages ?: include __DIR__ . '/messages.php';
        $this->alias = $alias;
    }

    /**
     * @param $data
     * @return bool
     */
    public function validate($data)
    {
        //$data = $data;
        foreach ($this->apply_rules as $name => $rules) {
            foreach ($rules as $filter_function => $params) {

                if (!isset($data[$name])) {
                    $this->setError($filter_function, $name, $params);
                } elseif (is_array($data[$name]) && !in_array($filter_function, $this->special)) {
                    foreach ($data[$name] as $key => $data_) {
                        if (!$value = call_user_func(array($this, $filter_function), $data_, $data, $params)) {
                            $this->setError($filter_function, $name, $params, $key);
                        }
                    }
                } else {
                    if (!$value = call_user_func(array($this, $filter_function), $data[$name], $data, $params)) {
                        $this->setError($filter_function, $name, $params);
                    }
                }

            }
        }

        return ($this->error) ? false : true;
    }

    /**
     * @param $fname
     * @param $name
     * @param $value
     * @param null $key
     */
    private function setError($fname, $name, $value, $key = null)
    {
        $message_key = str_replace('validate_', '', $fname);

        $error = $this->messages[$message_key];
        if (is_array($error)) {
            $type = array_shift($value);
            $error = $error[$type];
        }
        $alias = (!empty($this->alias[$name])) ? $this->alias[$name] : $name;
        $error = str_replace(':attribute', $alias, $error);
        if ($value) {
            $error = str_replace(':value', implode(',', $value), $error);
        }

        if (!is_null($key)) {
            $this->errors[$name][$key][] = $error;
        } else {
            $this->errors[$name][] = $error;
        }
        $this->error = true;

    }

    /**
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * @param $rules
     */
    protected function parseRules($rules)
    {
        foreach ($rules as $name => $rule) {
            if (!empty($rule)) {
                $this->apply_rules[$name] = $this->getNameRules($rule);
            }
        }
    }

    /**
     * @param $rules
     * @return array
     */
    private function getNameRules($rules)
    {
        $rules = explode('|', $rules);
        $p_rules = [];
        foreach ($rules as $rule) {
            $params = null;
            $name = 'validate_' . $rule;
            if (strpos($rule, ':') !== false) {
                $params = explode(':', $rule, 2);
                $name = 'validate_' . $params[0];
                $params = (!empty($params[1])) ? explode(':', $params[1]) : null;
            }

            $p_rules[$name] = $params;
        }
        return $p_rules;
    }

    /**
     * @param $value
     * @return bool
     */
    protected function validate_required($value)
    {
        if (is_null($value)) {
            return false;
        } elseif (is_string($value) && trim($value) === '') {
            return false;
        }

        return true;
    }

    /**
     * @param $value
     * @param $data
     * @param $param
     * @return bool
     */
    protected function validate_contains($value, $data, $param)
    {

        $params = array_map(function ($element) {
            return trim(strtolower($element));
        }, $param);

        $val = trim(strtolower($value));

        if (!in_array($val, $params)) { // valid, return nothing
            return false;
        }

        return true;
    }

    /**
     * @param $value
     * @return bool
     */
    protected function validate_valid_email($value)
    {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        return true;
    }

    /**
     * @param $value
     * @param $data
     * @param $param
     * @return bool
     */
    protected function validate_max($value, $data, $param)
    {
        return $this->getSize($value) <= $param[0];
    }

    /**
     * @param $value
     * @return int
     */
    protected function getSize($value)
    {
        if (is_numeric($value)) {
            return $value;
        } elseif (is_array($value)) {
            return count($value);
        } else {
            if (function_exists('mb_strlen')) {
                return mb_strlen($value);
            } else {
                return strlen($value);
            }
        }
    }

    /**
     * @param $value
     * @param $data
     * @param $param
     * @return bool
     */
    protected function validate_min($value, $data, $param)
    {
        return $this->getSize($value) > $param[0];
    }

    /**
     * @param $value
     * @param $data
     * @param $param
     * @return bool
     */
    protected function validate_exact_len($value, $data, $param)
    {
        if (function_exists('mb_strlen')) {
            $value = mb_strlen($value);
        } else {
            $value = strlen($value);
        }
        return $value == $param[0];
    }

    /**
     * @param $value
     * @return bool
     */
    protected function validate_alpha($value)
    {
        if (empty($value)) {
            return false;
        }

        if (!preg_match('/^([a-zÀÁÂÃÄÅÇÈÉÊËÌÍÎÏÒÓÔÕÖÙÚÛÜÝàáâãäåçèéêëìíîïðòóôõöùúûüýÿ])+$/i',
                $value) !== false
        ) {
            return false;
        }

        return true;
    }

    /**
     * @param $value
     * @return bool
     */
    protected function validate_alpha_space($value)
    {
        if (empty($value)) {
            return false;
        }

        if (!preg_match("/^([a-z0-9ÀÁÂÃÄÅÇÈÉÊËÌÍÎÏÒÓÔÕÖÙÚÛÜÝàáâãäåçèéêëìíîïðòóôõöùúûüýÿ\s])+$/i", $value) !== false) {
            return false;
        }

        return true;
    }

    /**
     * @param $value
     * @return bool
     */
    protected function validate_alpha_dash($value)
    {
        if (empty($value)) {
            return false;
        }

        if (!preg_match('/^([a-z0-9ÀÁÂÃÄÅÇÈÉÊËÌÍÎÏÒÓÔÕÖÙÚÛÜÝàáâãäåçèéêëìíîïðòóôõöùúûüýÿ_-])+$/i', $value) !== false) {
            return false;
        }

        return true;
    }

    /**
     * @param $value
     * @return bool
     */
    protected function validate_alpha_numeric($value)
    {
        if (empty($value)) {
            return false;
        }

        if (!preg_match('/^([a-z0-9ÀÁÂÃÄÅÇÈÉÊËÌÍÎÏÒÓÔÕÖÙÚÛÜÝàáâãäåçèéêëìíîïðòóôõöùúûüýÿ])+$/i', $value) !== false) {
            return false;
        }

        return true;
    }

    /**
     * @param $value
     * @return bool
     */
    protected function validate_numeric($value)
    {

        if (empty($value)) {
            return false;
        }

        if (!is_numeric($value)) {
            return false;
        }

        return true;
    }

    /**
     * @param $value
     * @return bool
     */
    protected function validate_integer($value)
    {
        if (empty($value)) {
            return false;
        }

        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            return false;
        }

        return true;
    }

}