<?php
namespace Chetzof\Expector;

/**
 * @method $this dec($fields, $default = false)
 * @method $this expect_decimal($fields, $default = false)
 * @method $this expect_positive_decimal($fields, $default = false)
 * @method $this decp($fields, $default = false)
 * @method $this expect_slug($fields, $default = false)
 * @method $this slug($fields, $default = false)
 * @method $this expect_bool($fields, $invalidate_on_absence = true, $default = false)
 * @method $this bool($fields, $invalidate_on_absence = true, $default = false)
 * @method $this inarr($fields, $whitelist = [], $default = false)
 * @method $this expect_in_array($fields, $whitelist = [], $default = false)
 * @method $this string($fields, $whitelist = [], $default = false)
 * @method $this expect_string($fields, $default = false)
 * @method $this max($fields, $max, $default = false)
 * @method $this expect_max($fields, $max, $default = false)
 */
class Expector
{
    protected $input;
    protected $output = [];
    protected $expectations = [];
    protected $dirty = true;
    protected $valid = false;
    protected $force = true;
    protected $failed_fields = [];

    protected $method_shorthands = [
        'dec' => 'expect_decimal',
        'decp' => 'expect_positive_decimal',
        'slug' => 'expect_slug',
        'bool' => 'expect_bool',
        'inarr' => 'expect_in_array',
        'string' => 'expect_string',
        'max' => 'expect_max',
    ];

    const EMPTY_STRING_TO_NULL = 1;
    protected $flags;
    protected $flag_empty_string_to_null = false;

    protected $assumptions = [];

    public function __construct(array $input, $assumptions = [], $flags = 0) {
        $this->input = $input;
        $this->perform_preliminary_sanitization();
        $this->assumptions = $assumptions;
        $this->flags = $flags;
        $this->calculate_flags();
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
    }

    protected function apply_flag_operations() {
        array_walk_recursive($this->output, function (&$value) {
            if ($this->flag_empty_string_to_null && $value === '') {
                $value = null;
            }
        });
    }

    protected function build_filter() {
        foreach ($this->expectations as $expectation) {
            $validator_name = 'validate_' . $expectation['rule'];
            foreach ($expectation['fields'] as $field) {
                if (!in_array($field, $this->failed_fields)) {
                    if (isset($this->input[$field])) {
                        $surefield = &$this->input[$field];
                    } else {
                        $surefield = null;
                    }

                    $args = array_merge([&$surefield], $expectation['options']);
                    $validation_result = call_user_func_array([$this, $validator_name], $args);

                    if ($validation_result === false) {
                        $this->valid = false;
                        $this->output[$field] = $expectation['default'];
                        $this->failed_fields[] = $field;
                    } else {
                        $this->output[$field] = $surefield;
                    }
                    unset($surefield);
                }

            }
        }
    }

    public function get($key, $default = null) {
        if ($this->dirty) {
            $this->process();
        }

        if (!$this->valid && !$this->force) {
            throw new \Exception('Unsafe input');
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

            if (!count($arguments)) {
                $default = false;
            } else {
                $default = array_pop($arguments);
            }

            $this->expectations[] = [
                'rule' => $rule_name,
                'fields' => (array) $fields,
                'default' => $default,
                'options' => $options,
            ];

            return $this;
        } elseif (isset($this->method_shorthands[$name])) {
            return call_user_func_array([$this, $this->method_shorthands[$name]], $arguments);
        } else {
            throw new \Exception("Invalid method $name");
        }
    }

}