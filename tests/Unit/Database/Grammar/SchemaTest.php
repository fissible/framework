<?php declare(strict_types=1);

namespace Tests\Unit\Database\Grammar;

use Fissible\Framework\Database\Grammar\Schema;
use Tests\TestCase;

final class SchemaTest extends TestCase
{
    public function testCompile()
    {
        $expected = <<<EOF
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255),
    verification_code VARCHAR(255),
    verified_at TIMESTAMP,
    name_first VARCHAR(255) NOT NULL,
    name_last VARCHAR(255) NOT NULL,
    is_closed BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT (strftime('%s','now')),
    updated_at TIMESTAMP
);
EOF;
        // 
        $builder = function ($table) {
            $table->int('id')->primary();
            $table->string('email', 255)->notNull()->unique();
            $table->string('password', 255);
            $table->string('verification_code', 255);
            $table->timestamp('verified_at');
            $table->string('name_first', 255)->notNull();
            $table->string('name_last', 255)->notNull();
            $table->bool('is_closed')->default(false);
            $table->timestamp('created_at')->default("(strftime('%s','now'))");
            $table->timestamp('updated_at');
        };
        $Create = new Schema('users', true);
        $builder($Create);

        $this->assertEquals($expected, $Create->toSql());
    }
}