<?php declare(strict_types=1);

namespace Fissible\Framework\Validation;

use Fissible\Framework\Database\Query;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;

class NotExistsRule extends Rule
{
    protected string $name = 'not-exists';

    protected string $table;

    protected string $field;

    public function __construct(string $table, string $field = 'id')
    {
        $this->table = $table;
        $this->field = $field;
    }

    /**
     * @return string
     */
    public function message(): string
    {
        return 'The ":attribute" must not exist in the database.';
    }

    /**
     * @param string $name
     * @param mixed $input
     * @return PromiseInterface
     */
    public function passes(string $name, $input): PromiseInterface
    {
        // $pending = new Deferred();
        return Query::table($this->table)->where($this->field, $input)->first()->then(function ($row) use ($name, $input) {
            return $this->resolve($name, $input, $row === null);
        });
    }
}