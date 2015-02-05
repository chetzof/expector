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

    public function phpTypesDataProviderRevised() {
        $data = [
            'simple_string' => 'foo',
            'slug_string' => 'foo-bar_foo',
            'numeric_string_high' => '90',
            'numeric_string_low' => '20',
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
        ];
        $output = [];
        foreach ($data as $key => $value) {
            $output[] = [$key, $value];
        }

        return $output;
    }

    public function testOptionalConstraint() {
        $i = new Expector([
            'page' => '4',
            'two' => 'foo'
        ]);
        $i
            ->string(['one', 'two'])
            ->decp('limit')
            ->max('limit', 20)
            ->optional('limit', 15)
            ->decp('page')
            ->optional('page', 2)
            ->optional(['one', 'two'], 'bar');
        $this->assertSame([
            'two' => 'foo',
            'page' => 4,
            'limit' => 15,
            'one' => 'bar'
        ], $i->all());
        $this->assertTrue($i->valid());
    }

    /**
     * @expectedException \Exception
     */
    public function testOptionalValueAndNoValidator() {
        $i = new Expector([]);
        $i
            ->optional('page', 2);
        $i->all();
    }

    public function testAllOptional() {
        $i = new Expector([
            'foo' => 1,
            'bar' => 'string'
        ], [], Expector::ALL_OPTIONAL);
        $i
            ->string('bar')
            ->decp('foo')
            ->dec('non-existent')
            ->dec('non-existent1')
            ->optional('non-existent', 'bar');
        $this->assertSame([
            'bar' => 'string',
            'foo' => 1,
            'non-existent' => 'bar',
            'non-existent1' => null,
        ], $i->all());
        $this->assertTrue($i->valid());
    }

    public function testOverwriteConstraint() {
        $i = new Expector([
            'limit' => '15',
        ]);
        $i
            ->max('limit', 10)
            ->max('limit', 20);
        $this->assertSame([
            'limit' => 15,
        ], $i->all());
        $this->assertTrue($i->valid());
    }

    public function testMaxAndDecimalCombinationValid() {
        $i = new Expector([
            'limit' => '9',
        ]);
        $i
            ->decp('limit')
            ->max('limit', 10);
        $this->assertSame([
            'limit' => 9,
        ], $i->all());
        $this->assertTrue($i->valid());
    }

    public function testMaxAndDecimalCombinationInvalid() {
        $i = new Expector([
            'over_limit' => '20',
        ]);
        $i
            ->decp('over_limit')
            ->max('over_limit', 10);
        $this->assertFalse($i->valid());
    }

    public function testFlags() {
        return $this->markTestIncomplete();
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

    public function testEmptyValidator() {
        $if = new Expector([
            'limit' => '31',
            'non_assumption_field' => '22',
            'test' => 'test1'
        ]);
        $this->assertEmpty($if->all());
    }

    public function testAssumptionsValid() {
        $input = [
            'limit' => '31',
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
            'test' => 'test1'
        ], $if->all());
    }

    public function testAssumptionsInvalid() {
        $input = [
            'limit' => '31',
            'page' => 'invalid string',
            'non_assumption_field' => '22',
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

        $this->assertFalse($if->valid());
    }

    /**
     * @dataProvider phpTypesDataProviderRevised
     */
    public function testMaxConstraint($name, $value) {
        $valid_fields = [
            'positive_integer' => 43,
            'numeric_string_low' => 20,
            'float' => 42.44,
            'negative_integer' => - 11,
            'one_string' => 1,
            'two_string' => 0
        ];
        $v = new Expector([$name => $value]);
        $v->max($name, 50);
        if (array_key_exists($name, $valid_fields)) {
            $this->assertSame([$name => $valid_fields[$name]], $v->all());
            $this->assertTrue($v->valid());
        } else {
            $this->assertFalse($v->valid());
        }
    }

    /**
     * @dataProvider phpTypesDataProviderRevised
     */
    public function testPositiveDecimalConstraint($name, $value) {
        $valid_fields = [
            'positive_integer' => 43,
            'numeric_string_low' => 20,
            'numeric_string_high' => 90,
            'one_string' => 1,
        ];

        $v = new Expector([$name => $value]);
        $v->decp($name);
        if (array_key_exists($name, $valid_fields)) {
            $this->assertSame([$name => $valid_fields[$name]], $v->all());
            $this->assertTrue($v->valid());
        } else {
            $this->assertFalse($v->valid());
        }
    }

    /**
     * @dataProvider phpTypesDataProviderRevised
     */
    public function testIntegerConstraint($name, $value) {
        $valid_fields = [
            'positive_integer' => 43,
            'negative_integer' => - 11,
            'numeric_string_low' => 20,
            'numeric_string_high' => 90,
            'one_string' => 1,
            'two_string' => 0,
        ];

        $v = new Expector([$name => $value]);
        $v->dec($name);
        if (array_key_exists($name, $valid_fields)) {
            $this->assertSame([$name => $valid_fields[$name]], $v->all());
            $this->assertTrue($v->valid());
        } else {
            $this->assertFalse($v->valid());
        }
    }

    /**
     * @dataProvider phpTypesDataProviderRevised
     */
    public function testSlugConstraint($name, $value) {
        $valid_fields = [
            'simple_string' => 'foo',
            'slug_string' => 'foo-bar_foo',
            'numeric_string_low' => '20',
            'numeric_string_high' => '90',
            'number_string' => '42a',
            'one_string' => '1',
            'two_string' => '0',
            'off' => 'off',
            'positive_integer' => '43',
            'negative_integer' => '-11'
        ];

        $v = new Expector([$name => $value]);
        $v->slug($name);
        if (array_key_exists($name, $valid_fields)) {
            $this->assertSame([$name => $valid_fields[$name]], $v->all());
            $this->assertTrue($v->valid());
        } else {
            $this->assertFalse($v->valid());
        }
    }

    /**
     * @dataProvider phpTypesDataProviderRevised
     */
    public function testStringInArrayConstraint($name, $value) {
        $whitelist = ['foo', 'bar'];
        $valid_fields = [
            'simple_string' => 'foo',
        ];

        $v = new Expector([$name => $value]);
        $v->inarr($name, $whitelist);

        if (array_key_exists($name, $valid_fields)) {
            $this->assertSame([$name => $valid_fields[$name]], $v->all());
            $this->assertTrue($v->valid());
        } else {
            $this->assertFalse($v->valid());
        }
    }


    /**
     * @dataProvider phpTypesDataProviderRevised
     */
    public function testIntegerInArrayConstraint($name, $value) {
        $whitelist = ['43', 90, -11];
        $valid_fields = [
            'negative_integer' => -11,
        ];

        $v = new Expector([$name => $value]);
        $v->inarr($name, $whitelist);

        if (array_key_exists($name, $valid_fields)) {
            $this->assertSame([$name => $valid_fields[$name]], $v->all());
            $this->assertTrue($v->valid());
        } else {
            $this->assertFalse($v->valid());
        }
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

    public function testSuccessfulInputWithDefaults() {
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