<?php declare(strict_types=1);

namespace Tests;

use Fissible\Framework\Application;
use Fissible\Framework\Database\Drivers\Driver;
use Fissible\Framework\Filesystem\File;

trait UsesDatabase
{
    public $database;

    public $db;

    protected function getDatabaseFile()
    {
        return new File(sprintf('%s/database.sqlite3', __DIR__));
    }

    protected function setUpDatabase(): Driver
    {
        $this->tearDownDatabase();

        static::app()->bindInstance(Driver::class, Driver::create(['driver' => 'sqlite', 'path' => $this->getDatabaseFile()->getPath()]));
        $this->db = static::app()->instance(Driver::class);

        return $this->db;
    }

    protected function tearDownDatabase(): void
    {
        $File = $this->getDatabaseFile();
        if ($File->exists()) {
            $File->delete();
        }
    }
}