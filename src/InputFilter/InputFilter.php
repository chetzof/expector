<?php
namespace Chetzof\InputFilter;

/**
 * @method $this expect_positive_integer($fields, $max_range = null, $default = false)
 * @method $this expect_slug($fields, $default = false)
 * @method $this expect_bool($fields, $invalidate_on_absence = true, $default = false)
 * @method $this expect_in_array($fields, $whitelist = [], $default = false)
 */
class InputFilter
{
    private $input;
    private $output;
    private $expectations = [];
    private $dirty = true;
    private $valid = false;
    private $force = true;

    public function __construct(array $input) {
        $this->input = $input;
        $this->perform_preliminary_sanitization();
    }

    private function process() {
        $this->valid = true;
        $this->build_filter();
        $this->dirty = false;
    }

    private function perform_preliminary_sanitization() {
        array_walk_recursive($this->input, function (&$value) {
            if (is_string($value)) {
                $value = trim($value);
            }
//            if ($value === '') {
//                $value = null;
//            }
        });
    }

    private function build_filter() {
        foreach ($this->expectations as $expectation) {
            $validator_name = 'validate_' . $expectation['rule'];
            foreach ($expectation['fields'] as $field) {
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
                } else {
                    $this->output[$field] = $surefield;
                }
                unset($surefield);
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
     * @param $max_range
     *
     * @return bool
     */
    private function validate_positive_integer(&$value, $max_range = null) {
        if (!is_bool($value)) {
            $value = filter_var($value, FILTER_VALIDATE_INT, $this->build_filter_options(
                [
                    'min_range' => 1,
                    'max_range' => $max_range,
                ]
            )
            );
        } else {
            $value = false;
        }

        return $value !== false;
    }

    private function validate_integer(&$value, $min_range = null, $max_range = null) {
        $value = filter_var($value, FILTER_VALIDATE_INT, $this->build_filter_options(
            [
                'min_range' => $min_range,
                'max_range' => $max_range,
            ]
        )
        );

        return $value !== false;
    }

    private function validate_slug(&$value) {
        $value = filter_var($value, FILTER_VALIDATE_REGEXP, $this->build_filter_options([
            'regexp' => '/^([-a-z0-9_-])+$/i'
        ]));

        return $value !== false;
    }

    private function validate_in_array(&$value, $whitelist) {
        // try guess array type
        $value_copy = $value;
        $value_type = gettype(reset($whitelist));
        if ($value_type == 'integer') {
            if ($this->validate_integer($value_copy) === false) {
                return false;
            }
        }

        $result = in_array($value_copy, $whitelist, true);
        if ($result) {
            $value = $value_copy;
        }

        return $result;
    }

    private function validate_bool(&$value, $invalidate_on_absence = true) {
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

    private function build_filter_options(array $input_options) {
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
        } else {
            throw new \Exception('Invalid method');
        }
    }

}