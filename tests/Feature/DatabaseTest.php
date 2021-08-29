<?php declare(strict_types=1);

namespace Tests\Feature;

use Fissible\Framework\Database\PaginatedQuery;
use Fissible\Framework\Database\Query;
use Fissible\Framework\Facades\DB;
use React\EventLoop\Loop;
use Tests\TestCase;

final class DatabaseTest extends TestCase
{
    use \Tests\UsesDatabase;

    public $createTestTablePromise;

    public function setUp(): void
    {
        $this->setUpDatabase();

        $this->db->exec('CREATE TABLE IF NOT EXISTS test (
            id INTEGER PRIMARY KEY,
            name VARCHAR (30) NOT NULL,
            color VARCHAR (10) DEFAULT NULL,
            size INTEGER (2) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT (strftime(\'%s\',\'now\')),
            updated_at TIMESTAMP
        )');
    }

    public function testQuery()
    {
        $loop = Loop::get();

        DB::table('test')->insert(['name' => 'First', 'color' => 'red', 'size' => 1])->then(function ($id) {
            $this->assertEquals(1, $id);
        })->done();

        Query::table('test')->insert([
            ['name' => 'Second', 'color' => null, 'size' => 2],
            ['name' => 'Third', 'size' => 2]
        ])->then(function ($count) {
            $this->assertEquals(2, $count);
        }, function (\Exception $e) {
            echo 'Error: ' . $e->getMessage() . PHP_EOL;
            $this->db->quit();
            throw $e;
        })->done();


        Query::table('test')->insert(['name' => 'Fourth', 'color' => 'Brown', 'size' => 4])->then(function ($id) {
            Query::table('test')
                ->where('id', $id)
                ->first()->then(function ($row) {
                    $this->assertEquals('Fourth', $row['name']);
                    $this->db->quit();
                }, function (\Exception $e) {
                    echo 'Error: ' . $e->getMessage() . PHP_EOL;
                    $this->db->quit();
                    throw $e;
                })->done();

            return $id;
        }, function (\Exception $e) {
            echo 'Error: ' . $e->getMessage() . PHP_EOL;
            $this->db->quit();
            throw $e;
        })->done();


        // Query::table('test')
        //     ->where('name', 'Fourth')
        //     ->first()->then(function ($row) {
        //         $this->assertEquals('Fourth', $row['name']);
        //     })->done();

        // Query::table('test')->where('name', 'Third')->update(['size' => 2]);

        // DB::table('test')
        //     ->where('name', 'Second')
        //     ->first()->then(function ($row) {
        //         $this->assertEquals('Second', $row['name']);
        //     })->done();
        
        // Query::table('test')
        //     ->select('name', 'color')
        //     ->whereIn('name', ['Second', 'Third'])
        //     ->count()->then(function ($result) {
        //         $this->assertEquals(2, $result);
        //     })->done();

        // Query::table('test')
        //     ->whereIn('name', ['Second', 'Third'])
        //     ->get()->then(function ($Rows) {
        //         $rows = $Rows->column('name');

        //         $this->assertFalse($rows->contains('First'));
        //         $this->assertTrue($rows->contains('Second'));
        //         $this->assertTrue($rows->contains('Third'));
        //     })->done();

        // Query::table('test')
        //     ->where('color', null)
        //     ->get()->then(function ($Rows) {
        //         $rows = $Rows->column('name');

        //         $this->assertFalse($rows->contains('First'));
        //         $this->assertTrue($rows->contains('Second'));
        //         $this->assertTrue($rows->contains('Third'));
        //     })->done();

        // Query::table('test')
        //     ->where('name', 'Third')
        //     ->orWhere(function (Query $query) {
        //         $query->where('name', '!=', 'First');
        //     })
        //     ->get()->then(function ($Rows) {
        //         $rows = $Rows->column('name');

        //         $this->assertFalse($rows->contains('First'));
        //         $this->assertTrue($rows->contains('Second'));
        //         $this->assertTrue($rows->contains('Third'));
        //     })->done();

        // Query::table('test')
        //     ->whereBetween('id', [2, 3])
        //     ->get()->then(function ($Rows) {
        //         $rows = $Rows->column('name');

        //         $this->assertFalse($rows->contains('First'));
        //         $this->assertTrue($rows->contains('Second'));
        //         $this->assertTrue($rows->contains('Third'));
        //     })->done();
        
        // Query::table('test')
        //     ->where('name', 'Second')
        //     ->delete()->then(function ($Result) {
        //         $this->assertEquals(1, $Result->changed);
        // })->done();

        // Query::table('test')
        //     ->where('name', 'Second')
        //     ->first()->then(function ($Result) {
        //         $this->assertNull($Result);
        //     })->done();

        $loop->run();
    }

    public function testPaginatedQuery()
    {
        $loop = Loop::get();

        Query::table('test')->insert([
            ['name' => 'First', 'color' => 'red', 'size' => 1],
            ['name' => 'Second', 'color' => null, 'size' => 2],
            ['name' => 'Fourth', 'color' => 'Green', 'size' => 3],
            ['name' => 'Fifth', 'color' => 'Green', 'size' => 4],
            ['name' => 'Sixth', 'color' => 'Green', 'size' => 5],
            ['name' => 'Seventh', 'color' => 'Green', 'size' => 6],
            ['name' => 'Eight', 'color' => 'Yellow', 'size' => 7],
            ['name' => 'Ninth', 'color' => 'Green', 'size' => 8]
        ])->then(function ($Result) {
            $this->assertEquals(8, $Result->insertId);
            $this->assertEquals(8, $Result->changed);
        });

        $query = PaginatedQuery::table('test', 3);
        $query->where('color', 'Green');

        $query->get(1)->then(function ($Rows) use ($query) {
            $query->total()->then(function ($total) {
                $this->assertEquals(5, $total);
            });
            $query->pages()->then(function ($pages) {
                $this->assertEquals(2, $pages);
            });
            $this->assertCount(3, $Rows);
            $this->assertTrue($Rows->column('name')->contains('Fourth'));
            $this->assertTrue($Rows->column('name')->contains('Fifth'));
            $this->assertTrue($Rows->column('name')->contains('Sixth'));
        })->done();

        $query->get(2)->then(function ($Rows) {
            $this->assertCount(2, $Rows);
            $this->assertTrue($Rows->column('name')->contains('Seventh'));
            $this->assertTrue($Rows->column('name')->contains('Ninth'));
            $this->db->quit();
        })->done();

        $loop->run();
    }

    public function testQueryExe()
    {
        $loop = Loop::get();

        Query::table('test')->insert([
            ['name' => 'First', 'color' => 'red'],
            ['name' => 'Second', 'color' => null]
        ])->then(function ($count) {
            $this->assertEquals(2, $count);
        })->done();

        Query::exec('SELECT COUNT(*) FROM test')->then(function ($Result) {
            $this->assertEquals(2, $Result->get(0)['COUNT(*)']);
        })->done();

        Query::exec('INSERT INTO test (name, color) VALUES (\'Third\', \'blue\')')->then(function ($Result) {
            $this->assertEquals(3, $Result);
        })->done();

        Query::exec('SELECT name FROM test WHERE color IS NOT NULL')->then(function ($Result) {
            $this->assertEquals('First', $Result->get(0)['name']);
            $this->assertEquals('Third', $Result->get(1)['name']);
        })->done();

        Query::exec('UPDATE test SET color = \'green\' WHERE name = \'Third\'')->then(function ($count) {
            $this->assertEquals(1, $count);
        })->done();

        Query::exec('DELETE FROM test WHERE color = \'green\'')->then(function ($count) {
            $this->assertEquals(1, $count);
        })->done();

        Query::exec('SELECT COUNT(*) FROM test WHERE name = \'Third\'')->then(function ($Result) {
            $this->assertEquals(0, $Result->get(0)['COUNT(*)']);
        })->done();

        $this->db->quit();
        $loop->run();
    }

    public function testQueryHaving()
    {
        $loop = Loop::get();

        $this->db->exec('CREATE TABLE IF NOT EXISTS albums (
            albumid INTEGER PRIMARY KEY,
            title VARCHAR (20) DEFAULT NULL
        )');
        
        $this->db->exec('CREATE TABLE IF NOT EXISTS tracks (
            trackid INTEGER PRIMARY KEY,
            name VARCHAR (30) NOT NULL,
            albumid INTEGER NOT NULL,
            composer VARCHAR (20) DEFAULT NULL,
            milliseconds INTEGER DEFAULT 0,
            FOREIGN KEY (albumid) REFERENCES albums(albumid)
        )');

        $albums = [
            ['title' => 'Lost, Season 1'],
            ['title' => 'Lost, Season 2'],
            ['title' => 'Lost, Season 3'],
            ['title' => 'Lost, Season 4'],
            ['title' => 'Battlestar Galactica (Classic), Season 1'],
            ['title' => 'Battlestar Galactica (Classic), Season 2'],
            ['title' => 'Battlestar Galactica (Classic), Season 3'],
            ['title' => 'Battlestar Galactica (Classic), Season 4']
        ];

        Query::table('albums')->insert($albums)->then(function ($count) use ($albums) {
            // $this->assertEquals(count($albums), $Result->insertId);
            // $this->assertEquals(count($albums), $Result->changed);
            $this->assertEquals(count($albums), $count);
        }, function (\Exception $error) {
            echo "\n" . 'Error: ' . $error->getMessage() . ' in ' . $error->getFile() . ':' . $error->getLine() . PHP_EOL;
            echo $error->getTraceAsString() . PHP_EOL;
        })->done();

        $tracks = [
            ['name' => 'Arrival', 'albumid' => 1, 'milliseconds' => 986545],
            ['name' => 'Mystery', 'albumid' => 1, 'milliseconds' => 2345656],
            ['name' => 'Apochrophyl', 'albumid' => 2, 'milliseconds' => 8766554],
            ['name' => 'Annexed Neighbors', 'albumid' => 2, 'milliseconds' => 4456576],
            ['name' => 'Cat in the Cradle', 'albumid' => 3, 'milliseconds' => 8567765],
            ['name' => 'Mystery Unraveled', 'albumid' => 3, 'milliseconds' => 546756854],
            ['name' => 'Following Ephemeral', 'albumid' => 4, 'milliseconds' => 67897343],
            ['name' => 'Inundating Isotopes', 'albumid' => 4, 'milliseconds' => 345459873],
            ['name' => 'Birth of a Nation', 'albumid' => 5, 'milliseconds' => 84758698],
            ['name' => 'Dawn of the Cylons', 'albumid' => 5, 'milliseconds' => 574593457],
            ['name' => 'The Awakening Incident', 'albumid' => 5, 'milliseconds' => 435645674],
            ['name' => 'Breakdown', 'albumid' => 5, 'milliseconds' => 567543456],
            ['name' => 'Attack on Humanity', 'albumid' => 6, 'milliseconds' => 34546457],
            ['name' => 'Yukatan Explosion', 'albumid' => 6, 'milliseconds' => 3459877675],
            ['name' => 'Washburn Breakdown', 'albumid' => 7, 'milliseconds' => 98456793],
            ['name' => 'Insubornation', 'albumid' => 7, 'milliseconds' => 23455466],
            ['name' => 'Latitude South', 'albumid' => 8, 'milliseconds' => 345676457],
            ['name' => 'Freak Storm', 'albumid' => 8, 'milliseconds' => 34564576]
        ];

        Query::table('tracks')->insert($tracks)->then(function ($count) use ($tracks) {
            // $this->assertEquals(count($tracks), $Result->insertId);
            // $this->assertEquals(count($tracks), $Result->changed);
            $this->assertEquals(count($tracks), $count);
        }, function (\Exception $error) {
            echo "\n" . 'Error: ' . $error->getMessage() . ' in ' . $error->getFile() . ':' . $error->getLine() . PHP_EOL;
            echo $error->getTraceAsString() . PHP_EOL;
        })->done();
        
        $query = Query::table('tracks')
            ->select('tracks.albumid', 'title', 'SUM(milliseconds) AS length')
            ->innerJoin('albums', 'albums.albumid', 'tracks.albumid')
            ->groupBy('tracks.albumid')
            ->having('length', '>', 600000000);

        $query->get()->then(function ($Tracks) use ($albums, $tracks) {
            $albumsLengths = [];

            for ($i = 1; $i <= count($albums); $i++) {
                $albumsLengths[$i] = array_sum(array_column(array_filter($tracks, function ($track) use ($i) {
                    return $track['albumid'] === $i;
                }), 'milliseconds'));
            }

            $this->assertEquals(5, $Tracks->get(0)['albumid']);
            $this->assertEquals(6, $Tracks->get(1)['albumid']);
            $this->assertEquals($albumsLengths[5], $Tracks->get(0)['length']);
            $this->assertEquals($albumsLengths[6], $Tracks->get(1)['length']);
        }, function (\Exception $error) {
            echo "\n" . 'Error: ' . $error->getMessage() . ' in ' . $error->getFile() . ':' . $error->getLine() . PHP_EOL;
            echo $error->getTraceAsString() . PHP_EOL;
        })->done();

        $this->db->quit();
        $loop->run();
    }

    public function testWhereParams()
    {
        $query = Query::table('users')
            ->where('current', '<', Query::raw('max'))
            ->whereIn('status', [Query::raw('prior_status'), Query::raw('next_status')])
            ->whereBetween('current', [Query::raw('max'), Query::raw('min')]);

        $expected = 'SELECT * FROM users WHERE current < max AND status IN (prior_status, next_status) AND current BETWEEN max AND min;';
        $actual = $query.';';

        $this->assertEquals($expected, $actual);
    }

    public function testToString()
    {
        $query = Query::table('users')->as('a')
            ->select('a.*')
            ->join(Query::table('users')
                ->select('username', 'email', 'COUNT(*)')
                ->groupBy('username', 'email')
                ->having('COUNT(*)', '>', 1),
                'users.username', 'b.username')->as('b')
            ->orderBy('users.email');
        
        $expected = 'SELECT a.* FROM users AS a ';
        $expected .= 'INNER JOIN (';
        $expected .= 'SELECT username, email, COUNT(*) FROM users GROUP BY username, email HAVING COUNT(*) > :HAVING1';
        $expected .= ') AS b ON users.username = b.username ORDER BY users.email ASC;';
        $actual = $query->toSql().';';
        
        $this->assertEquals($expected, $actual);

        $query = Query::select(
                'country.country_name_eng',
                ['calls' => 'SUM(CASE WHEN call.id IS NOT NULL THEN 1 ELSE 0 END)'],
                ['avg_difference' => 'AVG(ISNULL(DATEDIFF(SECOND, call.start_time, call.end_time),0))']
            )
            ->from('country')
            ->leftJoin('city', 'city.country_id', 'country.id')
            ->leftJoin('customer', 'city.id', 'customer.city_id')
            ->leftJoin('call', 'call.customer_id', 'customer.id')
            ->groupBy('country.id', 'country.country_name_eng')
            ->having('AVG(ISNULL(DATEDIFF(SECOND, call.start_time, call.end_time),0))', '>', '(SELECT AVG(DATEDIFF(SECOND, call.start_time, call.end_time)) FROM call)')
            ->orderBy([
                'calls' => 'DESC',
                'country.id' => 'ASC'
            ]);

        $expected = preg_replace("/\s+/", ' ', 'SELECT 
                country.country_name_eng,
                SUM(CASE WHEN call.id IS NOT NULL THEN 1 ELSE 0 END) AS calls,
                AVG(ISNULL(DATEDIFF(SECOND, call.start_time, call.end_time),0)) AS avg_difference
            FROM country 
            LEFT JOIN city ON city.country_id = country.id
            LEFT JOIN customer ON city.id = customer.city_id
            LEFT JOIN call ON call.customer_id = customer.id
            GROUP BY 
                country.id,
                country.country_name_eng
            HAVING AVG(ISNULL(DATEDIFF(SECOND, call.start_time, call.end_time),0)) > (SELECT AVG(DATEDIFF(SECOND, call.start_time, call.end_time)) FROM call)
            ORDER BY calls DESC, country.id ASC;'
        );
        $actual = $query->toSql().';';
        
        $this->assertEquals($expected, $actual);
    }

    public function tearDown(): void
    {
        if ($this->db) {
            $this->db->quit();
        }
        $this->tearDownDatabase();
    }
}