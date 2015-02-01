<?php
namespace Chetzof\InputFilter\Tests;

use Chetzof\InputFilter\InputFilter;

class ValidationTest extends \PHPUnit_Framework_TestCase
{
    public function phpTypesDataProvider() {
        return [
            [
                [
                    'string' => '90',
                    'empty' => '',
                    'empty_array' => [],
                    'non_empty_array' => ['bar'],
                    'float' => 42.44,
                    'negative_integer' => - 11,
                    'positive_integer' => 43,
                    'object' => new \stdClass(),
                    'null' => null,
                    'true' => true,
                    'false' => false,
                    '1' => '1',
                    '0' => '0',
                    'off' => 'off',
                ]
            ]
        ];
    }

    /**
     * @dataProvider phpTypesDataProvider
     */
    public function testPositiveIntegerFilter($data) {
        $v = new InputFilter($data);
        foreach (array_keys($data) as $field) {
            $v->expect_positive_integer($field);
        }
        $v->expect_positive_integer('non_existent_field');

        $this->assertEquals([
            'string' => 90,
            'empty' => false,
            'empty_array' => false,
            'non_empty_array' => false,
            'float' => false,
            'negative_integer' => false,
            'positive_integer' => 43,
            'object' => false,
            'null' => false,
            'true' => false,
            'false' => false,
            '1' => 1,
            '0' => false,
            'off' => false,
            'non_existent_field' => false
        ], $v->all());
    }

    public function testArrayObject(){
        $this->markTestIncomplete();
    }

}