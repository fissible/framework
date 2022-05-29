<?php declare(strict_types=1);

namespace Fissible\Framework\Database\Grammar;

use Fissible\Framework\Database\Query;
use React\Promise\PromiseInterface;

class Schema
{
    private CreateTable $Table;

    private string $field;

    public function __construct(string $name, bool $ifNotExists = false)
    {
        $this->Table = new CreateTable($name, [], $ifNotExists);
    }

    public static function create(string $name, callable $builder, bool $ifNotExists = false): PromiseInterface
    {
        $Schema = new static($name, $ifNotExists);
        $builder($Schema);

        return Query::driver()->exec($Schema->toSql());
    }

    public static function drop(string $name): PromiseInterface
    {
        return Query::driver()->exec(sprintf("DROP TABLE IF EXISTS %s", $name));
    }

    public function blob(string $name): self
    {
        $this->field = $name;
        $this->Table->addColumn($name, ['type' => 'BLOB']);
        return $this;
    }

    public function clob(string $name): self
    {
        $this->field = $name;
        $this->Table->addColumn($name, ['type' => 'CLOB']);
        return $this;
    }

    public function bool(string $name): self
    {
        $this->field = $name;
        $this->Table->addColumn($name, ['type' => 'BOOLEAN']);
        return $this;
    }

    public function boolean(string $name): self
    {
        return $this->bool($name);
    }

    public function char(string $name, int $width = 1): self
    {
        $this->field = $name;
        $this->Table->addColumn($name, ['type' => 'CHAR', 'width' => $width]);
        return $this;
    }

    public function date(string $name): self
    {
        $this->field = $name;
        $this->Table->addColumn($name, ['type' => 'DATE']);
        return $this;
    }

    public function dec(string $name, int $precision = 38, int $scale = 0): self
    {
        $this->field = $name;
        $this->Table->addColumn($name, ['type' => 'DECIMAL', 'precision' => $precision, 'scale' => $scale]);
        return $this;
    }

    public function decimal(string $name, int $precision = 38, int $scale = 0): self
    {
        return $this->dec($name, $precision, $scale);
    }

    public function float(string $name, int $precision = 64): self
    {
        $this->field = $name;
        $this->Table->addColumn($name, ['type' => 'FLOAT', 'precision' => $precision]);
        return $this;
    }

    public function int(string $name): self
    {
        $this->field = $name;
        $this->Table->addColumn($name, ['type' => 'INT']);
        return $this;
    }

    public function integer(string $name): self
    {
        return $this->int($name);
    }

    public function numeric(string $name, int $precision = 38, int $scale = 0): self
    {
        return $this->dec($name, $precision, $scale);
    }

    public function real(string $name): self
    {
        $this->field = $name;
        $this->Table->addColumn($name, ['type' => 'REAL']);
        return $this;
    }

    public function smallint(string $name): self
    {
        $this->field = $name;
        $this->Table->addColumn($name, ['type' => 'SMALLINT']);
        return $this;
    }

    public function string(string $name, int $width = 128): self
    {
        return $this->varchar($name, $width);
    }

    public function text(string $name): self
    {
        $this->field = $name;
        $this->Table->addColumn($name, ['type' => 'TEXT']);
        return $this;
    }

    public function time(string $name): self
    {
        $this->field = $name;
        $this->Table->addColumn($name, ['type' => 'TIME']);
        return $this;
    }

    public function timestamp(string $name, int $precision = null): self
    {
        $this->field = $name;
        $config = ['type' => 'TIMESTAMP'];

        if (isset($precision)) {
            $config['precision'] = max($precision, 6);
        }

        $this->Table->addColumn($name, $config);

        return $this;
    }

    public function timestampz(string $name, int $precision = null): self
    {
        $this->field = $name;
        $config = ['type' => 'TIMESTAMPZ'];

        if (isset($precision)) {
            $config['precision'] = max($precision, 6);
        }

        $this->Table->addColumn($name, $config);

        return $this;
    }

    public function varchar(string $name, int $width = 128): self
    {
        $this->field = $name;
        $this->Table->addColumn($name, ['type' => 'VARCHAR', 'width' => $width]);
        return $this;
    }


    public function check($check): self
    {
        $this->Table->setColumnConfig($this->field, 'check', (string) $check);
        return $this;
    }

    public function default(mixed $value)
    {
        $this->Table->setColumnConfig($this->field, 'default', $value);
        return $this;
    }

    public function foreign(string $table, string $column): self
    {
        return $this->references($table, $column);
    }

    public function null(bool $nullable = true): self
    {
        $this->Table->setColumnConfig($this->field, 'null', $nullable);
        return $this;
    }

    public function notNull(bool $notNullable = true): self
    {
        $this->Table->setColumnConfig($this->field, 'null', !$notNullable);
        return $this;
    }

    public function primary(): self
    {
        $this->Table->setColumnConfig($this->field, 'primary', true);
        return $this;
    }

    public function primaryKey(string ...$fields)
    {
        $this->Table->setPrimaryKey($fields);
        return $this;
    }

    public function unique(): self
    {
        $this->Table->setColumnConfig($this->field, 'unique', true);
        return $this;
    }

    public function references(string $table, string $column): self
    {
        $this->Table->setColumnConfig($this->field, 'foreign', [
            'table' => $table,
            'column' => $column
        ]);

        return $this;
    }

    public function toSql(): string
    {
        return $this->Table->compile();
    }

    public function __toString(): string
    {
        return $this->Table->compile();
    }
}