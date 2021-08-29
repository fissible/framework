<?php declare(strict_types=1);

namespace Tests\Feature\Repositories;

use Fissible\Framework\Database\Query;
use Fissible\Framework\Models\User;
use Fissible\Framework\Repositories\UserRepository;
use React\EventLoop\Loop;
use Tests\TestCase;

class UserRepositoryTest extends TestCase
{
    use \Tests\UsesDatabase;

    public function setUp(): void
    {
        $this->setUpDatabase();

        $this->db->exec("CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY,
            email VARCHAR (255),
            password VARCHAR (255),
            verification_code VARCHAR (255),
            verified_at TIMESTAMP,
            name_first VARCHAR(255) NOT NULL,
            name_last VARCHAR(255) NOT NULL,
            is_closed BOOL DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT (strftime('%s','now')),
            updated_at TIMESTAMP
        )");
    }

    public function testGetById()
    {
        $loop = Loop::get();

        Query::table('users')->insert([
            'email' => 'someemail@web.com',
            'name_first' => 'Bob',
            'name_last' => 'Williams'
        ])->then(function ($id) {
            UserRepository::getById($id)->then(function ($User) {
                $email = (string) $User->email ?? '';
            
                $this->assertEquals('someemail@web.com', $email);
                
                $this->db->quit();
            })->done();
        })->done();

        $loop->run();
    }

    public function testGetForLogin()
    {
        $loop = Loop::get();

        Query::table('users')->insert([
            'email' => 'someemail@web.com',
            'name_first' => 'Bob',
            'name_last' => 'Williams'
        ])->then(function ($id) {
            UserRepository::getForLogin('someemail@web.com')->then(function ($User) use ($id) {
                $this->assertEquals($id, $User->id);

                $this->db->quit();
            })->done();
        })->done();

        $loop->run();
    }

    public function testCreate()
    {
        $loop = Loop::get();

        UserRepository::create([
            'email' => 'someemail@web.com',
            'password' => '123',
            'name_first' => 'Bob',
            'name_last' => 'Williams'
        ])->then(function ($User) {
            $this->assertInstanceOf(User::class, $User);
            $this->assertTrue($User->exists());

            $this->db->quit();
        }, function (\Exception $error) {
            echo "\n" . 'Error: ' . $error->getMessage() . ' in ' . $error->getFile() . ':' . $error->getLine() . PHP_EOL;
            $this->db->quit();
        })->done();
        
        $loop->run();
    }

    public function testUpdate()
    {
        $loop = Loop::get();

        Query::table('users')->insert([
            'email' => 'someemail@web.com',
            'name_first' => 'Bob',
            'name_last' => 'Williams'
        ])->then(function ($id) {
            UserRepository::getById($id)->then(function ($User) {
                $this->assertEquals('someemail@web.com', $User->email);

                UserRepository::update($User, ['email' => 'anotheremail@web.com'])->then(function ($User) {
                    $this->assertEquals('anotheremail@web.com', $User->email);
                    $this->db->quit();
                })->done();
            })->done();
        })->done();

        $loop->run();
    }

    public function testDelete()
    {
        $loop = Loop::get();

        Query::table('users')->insert([
            'email' => 'someemail@web.com',
            'name_first' => 'Bob',
            'name_last' => 'Williams'
        ])->then(function ($id) {
            UserRepository::getById($id)->then(function ($User) {
                $this->assertTrue($User->exists());
                UserRepository::delete($User->id)->then(function ($result) {
                    $this->assertTrue($result);
                    $this->db->quit();
                })->done();
            })->done();
        })->done();

        $loop->run();
    }

    public function tearDown(): void
    {
        $this->tearDownDatabase();
    }
}