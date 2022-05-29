<?php declare(strict_types=1);

namespace Fissible\Framework\Database\Grammar;

class CreateTable
{
    private array $primaryKey;

    public function __construct(
        private string $table,
        private array $columns,
        private bool $ifNotExists = false
    ) { }

    public function addColumn(string $name, array $config = []): self
    {
        $this->columns[$name] = $config;
        return $this;
    }

    public function setColumnConfig(string $name, string $key, mixed $value): self
    {
        if (!isset($this->columns[$name])) {
            $this->columns[$name] = [];
        }

        $this->columns[$name][$key] = $value;
        return $this;
    }

    public function setPrimaryKey(array|string $fields): self
    {
        $this->primaryKey = (array) $fields;
        return $this;
    }

    /**
     * @return string
     */
    public function compile(): string
    {
        $sql = sprintf("CREATE TABLE %s%s (", ($this->ifNotExists ? 'IF NOT EXISTS ' : ''), $this->table);

        foreach ($this->columns as $field => $config) {
            if ($config['type'] === 'string') {
                $config['type'] = 'VARCHAR';
                if (!isset($config['width'])) {
                    $config['width'] = 128;
                }
            }

            // data type
            $sql .= sprintf("\n    %s %s", $field, strtoupper($config['type']));

            // data type constraints
            if (isset($config['width'])) {
                $sql .= sprintf('(%d)', $config['width']);
            } elseif (isset($config['length'])) {
                $sql .= sprintf('(%d)', $config['length']);
            } elseif (isset($config['precision'])) {
                if (isset($config['scale'])) {
                    $sql .= sprintf('(%d, %d)', $config['precision'], $config['scale']);
                } else {
                    $sql .= sprintf('(%d)', $config['precision']);
                }
            }

            // column constraints
            if (isset($config['null']) && $config['null'] === false) {
                $sql .= ' NOT NULL';
            }
            if (isset($config['primary']) && $config['primary'] === true) {
                $sql .= ' PRIMARY KEY';
            }
            if (isset($config['unique']) && $config['unique'] === true) {
                $sql .= ' UNIQUE';
            }
            if (isset($config['foreign'])) {
                $sql .= sprintf(' FOREIGN KEY REFERENCES %s(%s)', $config['foreign']['table'], $config['foreign']['column']);
            }
            if (isset($config['check'])) {
                $sql .= ' CHECK(' . $config['check'] . ')';
            }

            // column default value
            if (isset($config['default'])) {
                if (!is_scalar($config['default']) || is_bool($config['default'])) {
                    $config['default'] = strtoupper(var_export($config['default'], true));
                }
                $sql .= ' DEFAULT ' . $config['default'];
            }

            $sql .= ',';
        }

        if (isset($this->primaryKey)) {
            $sql .= sprintf("\n    PRIMARY KEY (%s),", implode(', ', $this->primaryKey));
        }

        $sql = rtrim($sql, ',');
        $sql .= "\n);";

        return $sql;
    }
}