<?php
namespace Chetzof\Expector;

use Chetzof\Expector\Expector;

class API extends Expector {


    public function __construct(array $input, $assumptions = false) {

        parent::__construct($input);
    }

    protected function process() {
        if ($this->use_assumptions) {
            $this->add_assumption_filters();
        }
        parent::process();
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


}