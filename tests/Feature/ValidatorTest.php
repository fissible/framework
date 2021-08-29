<?php declare(strict_types=1);

namespace Tests\Feature;

use Fissible\Framework\Facades\DB;
use Fissible\Framework\Validation\Validator;
use React\EventLoop\Loop;
use Tests\TestCase;

final class ValidatorTest extends TestCase
{
    use \Tests\UsesDatabase;

    public $v;

    public function setUp(): void
    {
        $this->v = new Validator([
            'name' => ['required', 'regex:/B{1}o{1}b{1}/'],
            'date' => ['date'],
            'datetime' => ['date-time']
        ], [
            'name.required' => 'Name is required'
        ]);
    }

    public function testErrors()
    {
        $this->v->validate(['name' => '', 'date' => '2014-1-10'])->then(function ($result) {
            $expected = [
                'name' => ['Name is required'],
                'date' => ['The "date" field format is invalid.']
            ];
            $this->assertEquals($expected, $result[1]);
        })->done();

        $this->v->validate(['name' => ''])->then(function ($result) {
            $expected = [
                'name' => ['Name is required']
            ];
            $this->assertEquals($expected, $result[1]);
        })->done();

        $this->v->validate(['name' => null])->then(function ($result) {
            $expected = [
                'name' => ['Name is required']
            ];
            $this->assertEquals($expected, $result[1]);
        })->done();

        $this->v->validate(['name' => 'Rob'])->then(function ($result) {
            $expected = [
                'name' => ['The "name" field format is invalid.']
            ];
            $this->assertEquals($expected, $result[1]);
        })->done();
        $this->v->validate([
            'name' => 'Bob',
            'date' => '2012-01-1'
        ])->then(function ($result) {
            $expected = [
                'date' => ['The "date" field format is invalid.']
            ];
            $this->assertEquals($expected, $result[1]);
        })->done();

        $this->v->validate([
            'name' => 'Bob',
            'date' => '2012-01-10',
            'datetime' => '2018-03-20T09:12:28Z'
        ])->then(function ($result) {
            $this->assertEquals([], $result[1]);
        })->done();
    }

    public function testMessages()
    {
        $expected = [
            'name.required' => 'Name is required'
        ];
        $actual = $this->v->messages();

        $this->assertEquals($expected, $actual);
    }

    public function testExistsRule()
    {
        $this->setUpDatabase();

        $loop = Loop::get();

        $this->db->exec('CREATE TABLE IF NOT EXISTS test (
            id INTEGER PRIMARY KEY,
            name VARCHAR (30) NOT NULL,
            color VARCHAR (10) DEFAULT NULL,
            size INTEGER (2) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT (strftime(\'%s\',\'now\')),
            updated_at TIMESTAMP
        )');

        $v = new Validator([
            'test_id' => ['exists:test'],
            'test_name' => ['exists:test,name'],
        ]);

        // $this->assertTrue($v->fails(['test_name' => 'First']));
        $v->validate(['test_name' => 'First'])->then(function ($result) {
            $expected = [
                'test_name' => ['The "test name" must exist in the database.']
            ];
            $this->assertEquals($expected, $result[1]);
        })->done();

        $v->validate()->then(function ($result) use ($v) {
            DB::table('test')->insert(['name' => 'First', 'color' => 'red', 'size' => 1])->then(function ($id) use ($v) {
                $v->validate(['test_id' => 5345348573495])->then(function ($result) {
                    $expected = [
                        'test_id' => ['The "test id" must exist in the database.']
                    ];
                    $this->assertEquals($expected, $result[1]);
                })->done();

                $v->validate([
                    'test_id' => $id
                ])->then(function ($result) {
                    $this->assertEquals([], $result[1]);
                })->done();

                $v->validate([
                    'test_name' => 'First'
                ])->then(function ($result) {
                    $this->assertEquals([], $result[1]);
                })->done();

                $v->validate([
                    'test_id' => $id,
                    'test_name' => 'First'
                ])->then(function ($result) {
                    $this->assertEquals([], $result[1]);
                })->done();

                $this->db->quit();
            })->done();
        })->done();        

        $loop->run();
        $this->tearDownDatabase();
    }
}