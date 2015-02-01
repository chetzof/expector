<?php
namespace Chetzof\InputFilter\Tests;

use Chetzof\InputFilter\InputFilter;

class ValidationTest extends \PHPUnit_Framework_TestCase {

    public function testPositiveIntegerFilter() {
        $v = new InputFilter(['integer' => '90']);
        $v->expect_positive_integer('integer');
        $this->assertEquals(['integer' => 90], $v->all());
    }

}