<?php
namespace Chetzof\Expector\Tests;

use Chetzof\Expector\Expector;

class ValidationTest extends \PHPUnit_Framework_TestCase
{
    public function phpTypesDataProvider() {
        return [
            [
                [
                    'simple_string' => 'foo',
                    'slug_string' => 'foo-bar_foo',
                    'numeric_string' => '90',
                    'text' => 'foo bar',
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
                    'one_string' => '1',
                    'two_string' => '0',
                    'off' => 'off',
                    'number_string' => '42a',
                ]
            ]
        ];
    }

    public function testMaxAndDecimalCombination() {
        $i = new Expector([
            'limit' => '10',
            'over_limit' => '20',

        ], [], Expector::EMPTY_STRING_TO_NULL);
        $i
            ->decp('limit')
            ->max('limit', 10)
            ->decp('over_limit')
            ->max('over_limit', 10)
        ;
        $this->assertSame([
            'limit' => 10,
            'over_limit' => false,
        ], $i->all());
    }


    public function testFlags() {
        $i = new Expector([
            'foo' => '',
            'foo1' => ' ',
            'zero' => 0,
            'bar' => false,
        ], [], Expector::EMPTY_STRING_TO_NULL);
        $i
            ->string('foo')
            ->string('foo1')
            ->dec('zero')
            ->bool('bar');
        $this->assertSame([
            'foo' => null,
            'foo1' => null,
            'zero' => 0,
            'bar' => false,
        ], $i->all());
    }

    public function testPreliminarySanitization() {
        $i = new Expector([
            'foo' => ' ',
            'bar' => ' bar '
        ]);
        $i
            ->string('foo')
            ->string('bar');
        $this->assertSame([
            'foo' => '',
            'bar' => 'bar'
        ], $i->all());
    }

    public function testAssumptions() {
        $input = [
            'limit' => '31',
            'page' => 'invalid string',
            'non_assumption_field' => '22',
            'test' => 'test1'
        ];
        $if = new Expector($input, [
            [
                'constraint' => 'positive_decimal',
                'fields' => ['limit', 'page', 'id']
            ],
            [
                'constraint' => ['in_array', ['test', 'test1']],
                'fields' => ['test']
            ]
        ]);
        $this->assertSame([
            'limit' => 31,
            'page' => false,
            'test' => 'test1'
        ], $if->all());

        $if = new Expector($input);
        $this->assertEmpty([], $if->all());
    }

    public function testDefaults() {
        $expector = new Expector(['limit' => '']);
        $expector->decp('limit', null, 10);
        $this->assertSame(['limit' => 10], $expector->all());
    }


    /**
     * @dataProvider phpTypesDataProvider
     */
    public function testMaxConstraint($data){
        $data['numeric_string_inlimit'] = '20';
        $expected_values_string = $this->getArrayTemplate($data,
            [
                'numeric_string_inlimit' => (int) $data['numeric_string_inlimit'],
                'one_string' => (int) $data['one_string'],
                'two_string' => (int) $data['two_string'],
            ],
            [
                'positive_integer', 'negative_integer', 'float'
            ]
        );

        $v = new Expector($data);
        $vs = new Expector($data);
        foreach (array_keys($expected_values_string) as $field) {
            $v->expect_max($field, 43);
            $vs->max($field, 43);
        }

        $this->assertSame($expected_values_string, $v->all());
        $this->assertSame($expected_values_string, $vs->all());
    }


    /**
     * @dataProvider phpTypesDataProvider
     */
    public function testPositiveDecimalConstraint($data) {

        $expected_values_string = $this->getArrayTemplate($data,
            [
                'numeric_string' => (int) $data['numeric_string'],
                'one_string' => (int) $data['one_string'],
                'two_string' => false,
            ],
            [
                'positive_integer',
            ]
        );

        $v = new Expector($data);
        $vs = new Expector($data);
        foreach (array_keys($expected_values_string) as $field) {
            $v->expect_positive_decimal($field);
            $vs->decp($field);
        }

        $this->assertSame($expected_values_string, $v->all());
        $this->assertSame($expected_values_string, $vs->all());
    }

    /**
     * @dataProvider phpTypesDataProvider
     */
    public function testIntegerConstraint($data) {

        $expected_values_string = $this->getArrayTemplate($data,
            [
                'numeric_string' => (int) $data['numeric_string'],
                'negative_integer' => (int) $data['negative_integer'],
                'one_string' => (int) $data['one_string'],
                'two_string' => (int) $data['two_string'],
            ],
            [
                'positive_integer',
                'negative_integer'
            ]
        );

        $v = new Expector($data);
        $vs = new Expector($data);
        foreach (array_keys($expected_values_string) as $field) {
            $v->expect_decimal($field);
            $vs->dec($field);
        }

        $this->assertSame($expected_values_string, $v->all());
        $this->assertSame($expected_values_string, $vs->all());
    }

    /**
     * @dataProvider phpTypesDataProvider
     */
    public function testSlugConstraint($data) {

        $expected_values_string = $this->getArrayTemplate($data,
            [
                'positive_integer' => (string) $data['positive_integer'],
                'negative_integer' => (string) $data['negative_integer'],
            ],
            [
                'simple_string',
                'slug_string',
                'numeric_string',
                'one_string',
                'two_string',
                'off',
                'number_string',
            ]
        );

        $v = new Expector($data);
        $vs = new Expector($data);
        foreach (array_keys($expected_values_string) as $field) {
            $v->expect_slug($field);
            $vs->slug($field);
        }

        $this->assertSame($expected_values_string, $v->all());
        $this->assertSame($expected_values_string, $vs->all());
    }

    /**
     * @dataProvider phpTypesDataProvider
     */
    public function testStringInArrayConstraint($data) {
        $whitelist = [$data['simple_string'], 'bar'];

        $expected_values_string = $this->getArrayTemplate($data, [], ['simple_string']);

        $v = new Expector($data);
        $vs = new Expector($data);
        foreach (array_keys($expected_values_string) as $field) {
            $v->expect_in_array($field, $whitelist);
            $vs->inarr($field, $whitelist);
        }

        $this->assertSame($expected_values_string, $v->all());
        $this->assertSame($expected_values_string, $vs->all());
    }

    /**
     * @dataProvider phpTypesDataProvider
     */
    public function testIntegerInArrayConstraint($data) {
        $whitelist = [11, $data['positive_integer']];

        $expected_values_string = $this->getArrayTemplate($data, [], ['positive_integer']);

        $v = new Expector($data);
        $vs = new Expector($data);
        foreach (array_keys($expected_values_string) as $field) {
            $v->expect_in_array($field, $whitelist);
            $vs->inarr($field, $whitelist);
        }

        $this->assertSame($expected_values_string, $v->all());
        $this->assertSame($expected_values_string, $vs->all());
    }

    /**
     * @dataProvider phpTypesDataProvider
     */
    public function testMixedStringInArrayConstraint($data) {
        $whitelist = [$data['simple_string'], (string) $data['positive_integer']];

        $expected_values_string = $this->getArrayTemplate($data, [], ['simple_string']);

        $v = new Expector($data);
        $vs = new Expector($data);
        foreach (array_keys($expected_values_string) as $field) {
            $v->expect_in_array($field, $whitelist);
            $vs->inarr($field, $whitelist);
        }

        $this->assertSame($expected_values_string, $v->all());
        $this->assertSame($expected_values_string, $vs->all());
    }

    /**
     * @dataProvider phpTypesDataProvider
     */
    public function testMixedIntegerInArrayConstraint($data) {
        $whitelist = [$data['positive_integer'], $data['simple_string']];

        $expected_values_string = $this->getArrayTemplate($data, [], ['simple_string', 'positive_integer']);

        $v = new Expector($data);
        $vs = new Expector($data);
        foreach (array_keys($expected_values_string) as $field) {
            $v->expect_in_array($field, $whitelist);
            $vs->inarr($field, $whitelist);
        }

        $this->assertSame($expected_values_string, $v->all());
        $this->assertSame($expected_values_string, $vs->all());
    }

    public function testStringtoIntConstraint() {
        $whitelist = [4];

        $v = new Expector(['val' => '4']);
        $v
            ->expect_decimal('val', $whitelist)
            ->expect_in_array('val', $whitelist);
        $this->assertSame(['val' => 4], $v->all());
    }

    public function testArrayObject() {
        $this->markTestIncomplete();
    }

    private function getArrayTemplate(array $data, $diff = [], $valid = []) {
        $array = array_combine(array_keys($data), array_fill(0, count(array_keys($data)), false));

        if (!empty($valid)) {
            $array = array_merge($array, array_intersect_key($data, array_flip($valid)));
        }

        foreach ($diff as $key => $value) {
            $array[$key] = $value;
        }

        $array['non_existent_field'] = false;

        return $array;
    }

}