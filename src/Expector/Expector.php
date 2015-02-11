<?php
namespace Chetzof\Expector;



/**
 * @method $this dec($fields)
 * @method $this expect_decimal($fields)
 * @method $this expect_positive_decimal($fields)
 * @method $this decp($fields)
 * @method $this expect_slug($fields)
 * @method $this slug($fields)
 * @method $this expect_bool($fields, $invalidate_on_absence = true)
 * @method $this bool($fields, $invalidate_on_absence = true)
 * @method $this inarr($fields, $whitelist = [])
 * @method $this expect_in_array($fields, $whitelist = [])
 * @method $this string($fields, $whitelist = [])
 * @method $this expect_string($fields)
 * @method $this max($fields, $max)
 * @method $this expect_max($fields, $max)
 * @method $this optional($fields, $value)
 */
class Expector
{
    protected $input;
    protected $output = [];
    protected $expectations = [];
    protected $dirty = true;
    protected $valid = false;
    protected $force = false;
    protected $failed_fields = [];
    protected $optional_fields = [];

    protected $method_shorthands = [
        'dec' => 'expect_decimal',
        'decp' => 'expect_positive_decimal',
        'slug' => 'expect_slug',
        'bool' => 'expect_bool',
        'inarr' => 'expect_in_array',
        'string' => 'expect_string',
        'max' => 'expect_max',
        'optional' => 'set_optional',
    ];

    const EMPTY_STRING_TO_NULL = 1;
    const ALL_OPTIONAL = 2;
    protected $flags;
    protected $flag_empty_string_to_null = false;
    protected $flag_all_optional = false;

    protected $assumptions = [];

    public function __construct(array $input, $assumptions = [], $flags = 0) {
        $this->input = $input;
        $this->perform_preliminary_sanitization();
        $this->assumptions = $assumptions;
        $this->flags = $flags;
        $this->calculate_flags();
    }

    public function set_optional($fields, $value = null) {
        foreach ((array) $fields as $field) {
            $this->optional_fields[$field] = $value;
        }

        return $this;
    }

    protected function process() {
        $this->valid = true;
        $this->failed_fields = [];
        if (!empty($this->assumptions)) {
            $this->add_assumption_filters();
        }
        $this->build_filter();
        $this->apply_flag_operations();
        $this->dirty = false;
    }

    private function add_assumption_filters() {
        $map = [];
        foreach ($this->assumptions as $assumption) {
            foreach ($assumption['fields'] as $field) {
                $map[$field] = $assumption['constraint'];
            }
        }

        foreach (
            new \RecursiveIteratorIterator(
                new \RecursiveArrayIterator($this->input),
                \RecursiveIteratorIterator::SELF_FIRST)
            as $key => $value
        ) {
            if (isset($map[$key])) {
                $args = (array) $map[$key];
                $callable = [$this, 'expect_' . array_shift($args)];
                array_unshift($args, $key);
                call_user_func_array($callable, $args);
            }
        }
    }

    protected function perform_preliminary_sanitization() {
        array_walk_recursive($this->input, function (&$value) {
            if (is_string($value)) {
                $value = trim($value);
            }
        });
    }

    protected function calculate_flags() {
        if ($this->flags & self::EMPTY_STRING_TO_NULL) {
            $this->flag_empty_string_to_null = true;
        }
        if ($this->flags & self::ALL_OPTIONAL) {
            $this->flag_all_optional = true;
        }
    }

    protected function apply_flag_operations() {
        array_walk_recursive($this->output, function (&$value) {
            if ($this->flag_empty_string_to_null && $value === '') {
                $value = null;
            }
        });
    }

    protected function build_filter() {
        if (!empty(array_diff_key($this->optional_fields, $this->expectations))) {
            throw new \Exception('Field cannot have optional value but no validation');
        }

        if ($this->flag_all_optional) {
            foreach (
                array_diff(
                    array_keys($this->expectations),
                    array_keys($this->optional_fields),
                    array_keys($this->input)) as $field
            ) {
                $this->optional_fields[$field] = null;
            }
        }

        foreach ($this->expectations as $field => $expectations) {
            if (array_key_exists($field, $this->input)) {
                $surefield = &$this->input[$field];
            } else {
                if (array_key_exists($field, $this->optional_fields)) {
                    continue;
                } else {
                    $this->failed_fields[] = $field;
                    $this->valid = false;
                    continue;
                }
            }
            foreach ($expectations as $expectation) {
                if (!in_array($field, $this->failed_fields)) {
                    $validator_name = 'validate_' . $expectation['rule'];

                    $args = array_merge([&$surefield], $expectation['options']);
                    $validation_result = call_user_func_array([$this, $validator_name], $args);

                    if ($validation_result === false) {
                        $this->valid = false;
                        $this->failed_fields[] = $field;
                    } else {
                        $this->output[$field] = $surefield;
                    }

                }
            }
            unset($surefield);
        }

        if ($this->valid) {
            $this->output = array_merge($this->output, array_diff_key($this->optional_fields, $this->output));
        }
    }

    public function get($key, $default = null) {
        if ($this->dirty) {
            $this->process();
        }

        if (!$this->valid && !$this->force) {
            throw new \Exception('Unsafe input');
        }

        if (!isset($this->expectations[$key])){
            throw new UnexpectedFieldException($key);
        }

        // to return false on null is expected
        if (isset($this->output[$key])) {
            return $this->output[$key];
        } else {
            return $default;
        }
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function all() {
        if ($this->dirty) {
            $this->process();
        }
        if (!$this->valid && !$this->force) {
            throw new \Exception('Unsafe input');
        }

        return $this->output;
    }

    public function valid() {
        if ($this->dirty) {
            $this->process();
        }

        return $this->valid;
    }

    /**
     * @param $value
     *
     * @return bool
     */
    protected function validate_positive_decimal(&$value) {
        if (!is_bool($value)) {
            $value = filter_var($value, FILTER_VALIDATE_INT, $this->build_filter_options(['min_range' => 1]));
        } else {
            $value = false;
        }

        return $value !== false;
    }

    protected function validate_decimal(&$value) {
        if (!is_bool($value)) {
            $value = filter_var($value, FILTER_VALIDATE_INT);
        } else {
            $value = false;
        }

        return $value !== false;
    }

    protected function validate_max(&$value, $max) {
        if (is_numeric($value) && $value <= $max) {
            $value += 0;

            return true;
        } else {
            return false;
        }
    }

    protected function validate_slug(&$value) {
        if (!is_bool($value)) {
            $value = filter_var($value, FILTER_VALIDATE_REGEXP, $this->build_filter_options([
                'regexp' => '/^([-a-z0-9_-]+)$/i'
            ]));
        } else {
            $value = false;
        }

        return $value !== false;
    }

    protected function validate_in_array(&$value, $whitelist) {
        $result = in_array($value, $whitelist, true);

        return $result;
    }

    protected function validate_bool(&$value, $invalidate_on_absence = true) {
        if ($value === null && $invalidate_on_absence) {
            $value = false;

            return false;
        }

        $value = filter_var($value, FILTER_VALIDATE_BOOLEAN,
            $this->build_filter_options([
                'flags' => $invalidate_on_absence ? FILTER_NULL_ON_FAILURE : null
            ])
        );

        if ($invalidate_on_absence) {
            return $value !== null;
        }

        return true;
    }

    protected function  validate_string(&$value) {
        if (is_scalar($value)) {
            $value = (string) $value;

            return true;
        } else {
            return false;
        }
    }

    protected function build_filter_options(array $input_options) {
        $output_options = [];
        if (isset($input_options['flags'])) {
            $output_options['flags'] = $input_options['flags'];
            unset($input_options['flags']);
        }
        $output_options['options'] = array_filter($input_options, function ($value) {
            return $value !== null;
        });

        return $output_options;
    }

    /**
     * @param $name
     * @param $arguments
     *
     * @return $this
     * @throws \Exception
     */
    public function __call($name, $arguments) {
        if (strpos($name, 'expect') === 0) {
            $this->dirty = true;
            $rule_name = substr($name, 7);
            $fields = array_shift($arguments);

            if (!count($arguments)) {
                $options = [];
            } else {
                $reflection = new \ReflectionClass($this);
                $num = $reflection->getMethod('validate_' . $rule_name)->getNumberOfParameters();

                $options = array_splice($arguments, 0, $num - 1);
            }

            $data_set = [
                'rule' => $rule_name,
                'options' => $options,
            ];

            foreach ((array) $fields as $field) {
                if (!isset($this->expectations[$field])) {
                    $this->expectations[$field][] = $data_set;
                } else {
                    $target_key = null;
                    foreach ($this->expectations[$field] as $key => $expectation) {
                        if ($expectation['rule'] === $rule_name) {
                            $target_key = $key;
                            break;
                        }
                    }
                    if ($target_key !== null) {
                        $this->expectations[$field][$target_key] = $data_set;
                    } else {
                        $this->expectations[$field][] = $data_set;
                    }
                }
            }

            return $this;
        } elseif (isset($this->method_shorthands[$name])) {
            return call_user_func_array([$this, $this->method_shorthands[$name]], $arguments);
        } else {
            throw new \Exception("Invalid method $name");
        }
    }

    private function has_rule($field, $rule) {
        if (isset($this->expectations[$field])) {
            foreach ($this->expectations[$field] as $expectation) {
                if ($expectation['rule'] === $rule) {
                    return true;
                }
            }
        }

        return false;
    }

}