<?php declare(strict_types=1);

namespace Tests\Unit\Database\Grammar;

use Fissible\Framework\Database\Grammar\CreateTable;
use Tests\TestCase;

final class CreateTableTest extends TestCase
{
    public function testCompile()
    {
        $expected = <<<EOF
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255),
    verification_code VARCHAR(255),
    verified_at TIMESTAMP,
    name_first VARCHAR(255) NOT NULL,
    name_last VARCHAR(255) NOT NULL,
    is_closed BOOL DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT (strftime('%s','now')),
    updated_at TIMESTAMP
);
EOF;
        $Create = new CreateTable('users', [
            'id' => ['type' => 'integer', 'primary' => true],
            'email' => ['type' => 'varchar', 'width' => 255, 'null' => false, 'unique' => true],
            'password' => ['type' => 'varchar', 'width' => 255],
            'verification_code' => ['type' => 'varchar', 'width' => 255],
            'verified_at' => ['type' => 'timestamp'],
            'name_first' => ['type' => 'varchar', 'width' => 255, 'null' => false],
            'name_last' => ['type' => 'varchar', 'width' => 255, 'null' => false],
            'is_closed' => ['type' => 'bool', 'default' => false],
            'created_at' => ['type' => 'timestamp', 'default' => '(strftime(\'%s\',\'now\'))'],
            'updated_at' => ['type' => 'timestamp']
        ], true);
        $actual = $Create->compile();

        $this->assertEquals($expected, $actual);
    }
}