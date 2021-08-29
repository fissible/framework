<?php declare(strict_types=1);

namespace Tests\Feature;

use Fissible\Framework\Database\Query;
use Fissible\Framework\Database\PaginatedQuery;
use Fissible\Framework\Models\Model;
use React\EventLoop\Loop;
use Tests\TestCase;

final class ModelTest extends TestCase
{
    use \Tests\UsesDatabase;

    public function testFind()
    {
        $this->setUpDatabase();

        $this->db->exec('CREATE TABLE IF NOT EXISTS model (
            id INTEGER PRIMARY KEY,
            name VARCHAR (30) NOT NULL,
            created_at TIMESTAMP DEFAULT (strftime(\'%s\',\'now\')),
            updated_at TIMESTAMP
        )');

        $loop = Loop::get();

        Query::table('model')->insert(['name' => 'ModelFind'])->then(function ($id) {
            Model::find($id)->then(function ($Model) {
                $this->assertEquals('ModelFind', $Model->name);
            })->done();
        })->done();

        Model::insert(['name' => 'ModelFind'])->then(function ($id) {
            Model::find($id)->then(function ($Model) {
                $this->assertEquals('ModelFind', $Model->name);
                $this->db->quit();
            })->done();
        })->done();

        $loop->run();
    }

    public function testWhere()
    {
        $this->setUpDatabase();

        $this->db->exec('CREATE TABLE IF NOT EXISTS model (
            id INTEGER PRIMARY KEY,
            name VARCHAR (30) NOT NULL,
            color VARCHAR (10) DEFAULT NULL,
            size VARCHAR (10) DEFAULT NULL
        )');

        $loop = Loop::get();

        Query::table('model')->insert(['name' => 'ModelFind'])->then(function ($id) {
            Model::where('name', 'ModelFind')->first()->then(function ($Model) use ($id) {
                $this->assertEquals($id, $Model->id);
            })->done();
        })->done();

        Query::table('model')->insert([
            ['name' => 'First', 'color' => 'red', 'size' => 'small'],
            ['name' => 'Second', 'color' => 'blue', 'size' => 'medium'],
            ['name' => 'Third', 'color' => 'blue', 'size' => 'large']
        ])->then(function ($count) {
            $this->assertEquals(3, $count);
            Model::where('color', 'blue')->where('size', 'medium')->first()->then(function ($Model) {
                $this->assertEquals('Second', $Model->name);
                $this->db->close();
            })->done();
        })->done();

        $loop->run();
    }

    public function testGetAttribute()
    {
        $Model = new Model(['name' => 'TestModel']);

        $this->assertEquals('TestModel', $Model->getAttribute('name'));
    }

    public function testGetTable()
    {
        $Model = new Model();
        $this->assertEquals('model', $Model->getTable());
    }

    public function testSetAttribute()
    {
        $Model = new Model(['name' => 'Initial']);
        $Model->setAttribute('name', 'NameTest');

        $this->assertEquals('NameTest', $Model->name);

        $Model->setAttribute('name', 'Initial');

        $this->assertEquals('Initial', $Model->name);
    }

    public function testDelete()
    {
        $this->setUpDatabase();
        $this->db->exec('CREATE TABLE IF NOT EXISTS model (
            id INTEGER PRIMARY KEY,
            name VARCHAR (30) NOT NULL
        )');

        $Model = new Model([
            'name' => 'ModelDelete'
        ]);

        $loop = Loop::get();

        Query::table('model')->insert(['name' => 'ModelDelete'])->then(function ($id) use ($Model) {
            $Model->setAttribute('id', (int) $id);

            Model::find($id)->then(function ($Model) use ($id) {
                $this->assertEquals($id, $Model->id);
                
                $Model->delete()->then(function ($result) use ($id) {
                    $this->assertTrue($result);

                    Model::find($id)->then(function ($Model) {
                        $this->assertNull($Model);
                        $this->db->quit();
                    })->done();
                })->done();
            })->done();
        })->done();

        $loop->run();
    }

    public function testExists()
    {
        $this->setUpDatabase();

        $this->db->exec('CREATE TABLE IF NOT EXISTS model (
            id INTEGER PRIMARY KEY,
            name VARCHAR (30) NOT NULL
        )');

        $loop = Loop::get();

        $Model = new Model();

        $this->assertFalse($Model->exists());

        Query::table('model')->insert(['name' => 'ModelDelete'])->then(function ($id) use ($Model) {
            Model::find(intval($id))->then(function ($Model) {
                $this->assertTrue($Model->exists());
                $this->db->quit();
            })->done();
        });

        $loop->run();
    }

    public function testHasAttribute()
    {
        $Model = new Model(['name' => 'HasName']);

        $this->assertTrue($Model->hasAttribute('name'));
        $this->assertFalse($Model->hasAttribute('title'));
    }

    public function testIsDirty()
    {
        $Model = new Model(['name' => 'My Name']);
        $Model->setAttribute('name', 'Your Name');

        $this->assertFalse($Model->isDirty());

        $Model->setAttribute('id', 12);
        $Model->setAttribute('name', 'Our Name');

        $this->assertTrue($Model->isDirty());
    }

    public function testCreate()
    {
        $this->setUpDatabase();

        $this->db->exec('CREATE TABLE IF NOT EXISTS model (
            id INTEGER PRIMARY KEY,
            name VARCHAR (30) NOT NULL,
            created_at TIMESTAMP DEFAULT (strftime(\'%s\',\'now\')),
            updated_at TIMESTAMP
        )');

        $loop = Loop::get();

        Model::create(['name' => 'HasName'])->then(function ($Model) {
            $this->assertTrue($Model->exists());
            $this->assertTrue(is_int($Model->id));
            $this->assertEquals(date('Y-m-d H:i'), $Model->created_at->format('Y-m-d H:i'));

            $this->db->quit();
        })->done();

        $loop->run();
    }

    public function testInsert()
    {
        $this->setUpDatabase();

        $this->db->exec('CREATE TABLE IF NOT EXISTS model (
            id INTEGER PRIMARY KEY,
            name VARCHAR (30) NOT NULL,
            created_at TIMESTAMP DEFAULT (strftime(\'%s\',\'now\')),
            updated_at TIMESTAMP
        )');

        $Model = new Model(['name' => 'HasName']);

        $this->assertFalse($Model->exists());

        $loop = Loop::get();

        $Model->insert()->then(function ($id) {
            Model::find($id)->then(function ($Model) use ($id) {
                $this->assertTrue($Model->exists());
                $this->assertTrue(is_int($Model->id));
                $this->assertEquals($id, $Model->id);
                $this->assertEquals(date('Y-m-d H:i'), $Model->created_at->format('Y-m-d H:i'));
                $this->db->quit();
            })->done();
        })->done();

        $loop->run(); 
    }

    public function testPrimaryKey()
    {
        $Model = new Model(['name' => 'My Name']);

        $this->assertNull($Model->primaryKey());

        $Model->setAttribute('id', 12);

        $this->assertEquals(12, $Model->primaryKey());
    }

    public function testRefresh()
    {
        $this->setUpDatabase();

        $this->db->exec('CREATE TABLE IF NOT EXISTS model (
            id INTEGER PRIMARY KEY,
            name VARCHAR (30) NOT NULL,
            created_at TIMESTAMP DEFAULT (strftime(\'%s\',\'now\')),
            updated_at TIMESTAMP
        )');

        $loop = Loop::get();

        $Model = new Model(['name' => 'HasAnotherName']);
        $Model->save()->then(function ($Model) {
            $this->assertTrue($Model->exists());
            $this->assertEquals('HasAnotherName', $Model->name);

            $Model->update(['name' => 'UpdatedName'], 'updated_at')->then(function ($Model) {
                $this->assertEquals('UpdatedName', $Model->name);
                $this->db->quit();
            })->done();
        })->done();

        $loop->run(); 
    }

    public function testSave()
    {
        $this->setUpDatabase();

        $this->db->exec('CREATE TABLE IF NOT EXISTS model (
            id INTEGER PRIMARY KEY,
            name VARCHAR (30) NOT NULL,
            created_at TIMESTAMP DEFAULT (strftime(\'%s\',\'now\')),
            updated_at TIMESTAMP
        )');

        $Model = new Model(['name' => 'HasAnotherName']);
        
        $this->assertFalse($Model->exists());

        $loop = Loop::get();

        $Model->save()->then(function ($Model) {
            $this->assertTrue($Model->exists());
            $Model->name = 'UpdatedName';
            $Model->save()->then(function ($Model) {
                $this->assertEquals('UpdatedName', $Model->name);
                $this->db->quit();
            })->done();
        })->done();

        $loop->run(); 
    }

    public function testUpdate()
    {
        $this->setUpDatabase();

        $this->db->exec('CREATE TABLE IF NOT EXISTS model (
            id INTEGER PRIMARY KEY,
            name VARCHAR (30) NOT NULL,
            created_at TIMESTAMP DEFAULT (strftime(\'%s\',\'now\')),
            updated_at TIMESTAMP
        )');

        $loop = Loop::get();

        Query::table('model')->insert(['name' => 'ModelUpdate'])->then(function ($id) {
            Model::find($id)->then(function (Model $Model) use ($id) {
                $this->assertEquals('ModelUpdate', $Model->getAttribute('name'));

                $Model->setAttribute('name', 'AnotherName');
                $Model->update()->then(function (Model $Model) use ($id) {
                    $this->assertEquals('AnotherName', $Model->getAttribute('name'));
                    $this->db->quit();
                })->done();
            })->done();
        })->done();

        $loop->run(); 
    }

    public function testFloatParam()
    {
        $this->setUpDatabase();

        $this->db->exec('CREATE TABLE IF NOT EXISTS test_table (
            id INTEGER PRIMARY KEY,
            settlement DATE NOT NULL,
            quantity INTEGER NOT NULL,
            price DECIMAL (10,2)
        )');

        $loop = Loop::get();

        $date = \DateTime::createFromFormat('Ymd', '20200218');
        $settlementDate = $date->getTimestamp();
        $quantity = '3539';
        $price = '27.05';
        $Model = new class([
            'settlement' => $settlementDate,
            'quantity' => (int) $quantity,
            'price' => (float) $price
        ]) extends Model {
            protected static string $table = 'test_table';
            protected static $casts = ['quantity' => 'int'];
            protected array $dates = ['settlement'];
            protected const CREATED_FIELD = null;
            protected const UPDATED_FIELD = null;
        };

        $this->assertEquals($date, $Model->settlement);
        $this->assertEquals($quantity, $Model->quantity);
        $this->assertTrue(is_int($Model->quantity));
        $this->assertEquals(27.05, $Model->price);

        $Model->insert()->then(function ($id) use ($Model, $date) {
            $Model::find($id)->then(function ($Model) use ($date) {
                $this->assertEquals($date, $Model->settlement);
                $this->assertEquals(3539, $Model->quantity);
                $this->assertEquals(27.05, $Model->price);
                $this->db->quit();
            })->done();
        })->done();

        $loop->run(); 
    }

    public function testPaginatedQuery()
    {
        $this->setUpDatabase();

        $this->db->exec('CREATE TABLE IF NOT EXISTS test_table (
            id INTEGER PRIMARY KEY,
            name VARCHAR (30) NOT NULL,
            color VARCHAR (10),
            size VARCHAR (10) NOT NULL
        )');

        $loop = Loop::get();

        Query::table('test_table')->insert([
            ['name' => 'First', 'color' => 'red', 'size' => 1],
            ['name' => 'Second', 'color' => null, 'size' => 2],
            ['name' => 'Fourth', 'color' => 'Green', 'size' => 3],
            ['name' => 'Fifth', 'color' => 'Green', 'size' => 4]
        ])->done();

        (new TestModel)->insert([
            ['name' => 'Sixth', 'color' => 'Green', 'size' => 5],
            ['name' => 'Seventh', 'color' => 'Green', 'size' => 6],
            ['name' => 'Eight', 'color' => 'Yellow', 'size' => 7],
            ['name' => 'Ninth', 'color' => 'Green', 'size' => 8]
        ])->done();

        $query = new PaginatedQuery(TestModel::class, 3);
        $query->where('color', 'Green');

        $query->get(1)->then(function ($rows) use ($query) {
            $query->total()->then(function ($total) use ($query) {
                $this->assertEquals(5, $total);
                $query->pages()->then(function ($pages) {
                    $this->assertEquals(2, $pages);
                })->done();
            })->done();

            $this->assertCount(3, $rows);
            $this->assertTrue($rows->column('name')->contains('Fourth'));
            $this->assertTrue($rows->column('name')->contains('Fifth'));
            $this->assertTrue($rows->column('name')->contains('Sixth'));
        }, function ($e) { echo $e->getMessage()."\n"; $this->db->quit(); })->done();

        $query->get(2)->then(function ($rows) use ($query) {
            $this->assertCount(2, $rows);
            $this->assertTrue($rows->column('name')->contains('Seventh'));
            $this->assertTrue($rows->column('name')->contains('Ninth'));

            $query->pageData()->then(function ($data) {
                $this->assertEquals(5, $data['total']);
                $this->assertEquals(2, $data['pages']);
                $this->assertEquals(3, $data['perPage']);

                $this->db->quit();
            })->done();
        }, function ($e) { echo $e->getMessage()."\n"; $this->db->quit(); })->done();

        $loop->run(); 
    }

    public function testJsonSerialization()
    {
        $date = \DateTime::createFromFormat('Ymd', '20200218');
        $formattedDate = $date->format('Y-m-d\TH:i:sP');
        $settlementDate = $date->getTimestamp();
        $quantity = '3539';
        $price = '27.05';
        $Model = new TestModel([
            'settlement' => $settlementDate,
            'quantity' => (int) $quantity,
            'price' => (float) $price
        ]);

        $expected = '{"settlement":"'.$formattedDate.'","quantity":3539,"price":27.05}';
        $actual = json_encode($Model);

        $this->assertEquals($expected, $actual);
    }

    public function testSerialization()
    {
        $date = \DateTime::createFromFormat('Ymd', '20200218');
        $settlementDate = $date->getTimestamp();
        $quantity = '3539';
        $price = '27.05';
        $Model = new TestModel([
            'settlement' => $settlementDate,
            'quantity' => (int) $quantity,
            'price' => (float) $price
        ]);

        $ser = serialize($Model);
        $newModel = unserialize($ser);

        $this->assertEquals($Model->getAttributes(), $newModel->getAttributes());
    }

    public function tearDown(): void
    {
        $this->tearDownDatabase();
    }
}