<?php declare(strict_types=1);

namespace Fissible\Framework\Models;

use Carbon\Carbon;
use Fissible\Framework\Database\Query;
use Fissible\Framework\Exceptions\ModelException;
use React\Promise;

class Model implements \JsonSerializable, \Serializable
{
    protected static string $table;

    protected static string $primaryKey = 'id';

    protected static string $primaryKeyType = 'int';

    protected static $casts = [];

    protected array $dates = [];

    protected array $sensitive = [
        'password',
        'ssn'
    ];

    protected ?bool $exists = null;

    protected static array $castTypes = [
        'int', 'integer',
        'bool', 'boolean',
        'float', 'long', 'short', 'real',
        'string',
        'array', 'json',
        'date', 'datetime'
    ];
    
    protected static $dateFormat = 'U';

    protected const CREATED_FIELD = 'created_at';

    protected const UPDATED_FIELD = 'updated_at';

    private array $attributes = [];

    private array $dirty = [];

    private Query $query;

    /**
     * @param array|object $attributes
     */
    public function __construct($attributes = [])
    {
        if (is_object($attributes)) {
            $attributes = get_object_vars($attributes);
        }
        if (!is_array($attributes)) {
            throw new \InvalidArgumentException('Models must be hyrdated with an array or object with public properties.');
        }

        $this->setAttributes($attributes);
    }

    public static function create(array $attributes = [])
    {
        if (isset($attributes[0]) && is_array($attributes[0])) {
            throw new \InvalidArgumentException(sprintf(
                'Models must be created on at a time. Use %s::insert() for inserting multiple records.', get_called_class()
            ));
        }

        $instance = static::newInstance();

        return $instance->insertInternal($attributes)->then(function ($id) {
            return self::find($id);
        }, function (\Exception $error) {
            echo "\n" . 'Error: ' . $error->getMessage() . ' in ' . $error->getFile() . ':' . $error->getLine() . PHP_EOL;
            $this->db->quit();
        });
    }

    public static function find($id): Promise\PromiseInterface
    {
        return static::where(static::getPrimaryKey(), $id)->first();
    }

    public static function newInstance($attributes = []): Model
    {
        return new static($attributes);
    }

    public static function newQuery(): Query
    {
        return Query::table(static::getTable());
    }

    /**
     * Serialize \DateTime objects, default format is ISO-8601.
     * 
     * @param \DateTimeInterface $date
     * @param string $format
     * @return string
     */
    public static function serializeDate(\DateTimeInterface $date, string $format = \DateTime::ATOM): string
    {
        return $date->format($format);
    }


    /**
     * Delete the record.
     * 
     * @return Promise\PromiseInterface
     */
    public function delete(): Promise\PromiseInterface
    {
        return Query::table(static::getTable())
            ->where(static::$primaryKey, $this->primaryKey())
            ->delete()
            ->then(function ($affected) {
                $this->exists = false;
                $this->attributes = [];
                return $affected === 1;
            });
    }

    /**
     * Check if the primary key is set (implying the record is persisted).
     * 
     * @return bool
     */
    public function exists(): bool
    {
        if (isset($this->exists)) {
            return $this->exists;
        }

        if (empty($this->attributes)) return false;

        return isset($this->attributes[static::$primaryKey]);
    }

    public function first(): Promise\PromiseInterface
    {
        return $this->getQuery()->first()->then(function ($row) {
            if ($row) {
                return static::newInstance($row);
            }
            return null;
        });
    }

    public function get(): Promise\PromiseInterface
    {
        return $this->getQuery()->get()->then(function ($Models) {
            if ($Models) {
                return $Models->map(function ($attributes) {
                    $instance = static::newInstance($attributes);
                    $instance->exists = true;
                    return $instance;
                });
            }
            return $Models;
        });
    }

    /**
     * Get an attribute value.
     * 
     * @param string $name
     * @return mixed
     */
    public function getAttribute(string $name)
    {
        $value = null;

        if (isset($this->dirty[$name])) {
            $value = $this->dirty[$name];
        } else {
            $value = $this->getOriginal($name);
        }

        return $value;
    }

    /**
     * Get an attribute value.
     * 
     * @param string $name
     * @return mixed
     */
    public function getOriginal(string $name)
    {
        $value = null;

        if (isset($this->attributes[$name])) {
            $value = $this->attributes[$name];
        }

        return $value;
    }

    /**
     * @return array
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * @return string
     */
    public static function getDateFormat(): string
    {
        if (isset(static::$dateFormat)) {
            return static::$dateFormat;
        }
        return 'Y-m-d H:i:s';
    }

    /**
     * @return string
     */
    public static function getTable(): string
    {
        if (isset(static::$table)) {
            return static::$table;
        }
        return strtolower((new \ReflectionClass(new static))->getShortName());
    }

    public static function getPrimaryKey(): string
    {
        return static::$primaryKey ?? 'id';
    }

    /**
     * Set an attribute.
     * 
     * @param string $name
     * @param mixed $value
     * @return self
     */
    public function setAttribute(string $name, $value): self
    {
        $exists = $this->exists();

        // Casting
        if ($this->isCastable($name)) {
            $value = $this->castAttribute($name, $value);
        }

        if (!isset($this->attributes[$name])) {
            $this->attributes[$name] = null;
        }

        if (!$exists) {
            $this->attributes[$name] = $value;
        } else {
            if ($this->attributes[$name] !== $value) {
                $this->dirty[$name] = $value;
            } else {
                unset($this->dirty[$name]);
            }
        }

        return $this;
    }

    /**
     * Cast the value for instance representation.
     * 
     * @param string $name
     * @param mixed $value
     * @return mixed
     */
    protected function castAttribute(string $name, $value)
    {
        $castType = null;
        $format = null;
        if (isset(static::$casts[$name])) {
            $castType = static::$casts[$name];
            if (false !== ($pos = strpos($castType, ':'))) {
                $format = substr($castType, $pos + 1);
            }
        }

        if (is_null($value) && in_array($castType, static::$castTypes)) {
            return $value;
        }
        
        switch ($castType) {
            case 'int':
            case 'integer':
                return (int) $value;
            break;
            case 'bool':
            case 'boolean':
                return (bool) $value;
            break;
            case 'float':
            case 'long':
            case 'short':
            case 'real':
                return (float) $value;
            break;
            case 'string':
                return (string) $value;
            break;
            case 'json':
            case 'array':
                return json_decode($value);
            break;
            case 'date':
                $value = $this->asDate($value);
                if ($format) {
                    $value = $value->format($value);
                }
                return $value;
            break;
            case 'datetime':
                $value = $this->asDatetime($value);
                if ($format) {
                    $value = $value->format($value);
                }
                return $value;
            break;
        }

        if (!is_null($value) && in_array($name, $this->getDateFields())) {
            return $this->asDatetime($value);
        }

        return $value;
    }

    public function hasAttribute(string $name): bool
    {
        return (isset($this->dirty[$name]) || isset($this->attributes[$name]));
    }

    /**
     * Check if any fields have been updated in memory.
     * 
     * @return bool
     */
    public function isDirty(): bool
    {
        return !empty($this->dirty);
    }

    /**
     * Insert a new record.
     * 
     * @param array $attributes
     * @return Promise\PromiseInterface
     */
    protected function insertInternal(array $attributes = []): Promise\PromiseInterface
    {
        if (isset($attributes[0]) && is_array($attributes[0])) {
            throw new \InvalidArgumentException(__METHOD__.' must be used to insert one record at a time.');
        }

        if (empty($attributes)) {
            $attributes = $this->attributes;
            unset($attributes[static::$primaryKey]);
        } elseif (isset($attributes[static::$primaryKey])) {
            throw new \InvalidArgumentException('Data includes primary key value.');
        }

        return static::newQuery()->insert($this->uncastAttributes($attributes), static::CREATED_FIELD)->then(function ($primaryKey) {
            if ($primaryKey !== null) {
                if (static::$primaryKeyType === 'int') {
                    return intval($primaryKey);
                }
                return $primaryKey;
            }
            
            return null;
        });
    }

    /**
     * Insert multiple rows at once. Returns the number of affected rows.
     * 
     * @param array $records
     * @return int
     */
    protected function multiInsert(array $records): Promise\PromiseInterface
    {
        return static::newQuery()->insert($this->uncastAttributes($records), static::CREATED_FIELD);
    }

    public function jsonSerialize()
    {
        $attributes = [];
        foreach ($this->attributes as $key => $value) {
            if (in_array($key, $this->getDateFields())) {
                $value = static::serializeDate($value);
            }
            $attributes[$key] = $value;
        }
        foreach ($this->dirty as $key => $value) {
            if (in_array($key, $this->getDateFields())) {
                $value = static::serializeDate($value);
            }
            $attributes[$key] = $value;
        }

        foreach ($this->sensitive as $key) {
            unset($attributes[$key]);
        }

        return $attributes;
    }

    public function primaryKey()
    {
        if (isset($this->attributes[static::$primaryKey])) {
            return $this->attributes[static::$primaryKey];
        }
        return null;
    }

    public static function query()
    {
        $instance = static::newInstance();
        $instance->getQuery();
        return $instance;
    }

    /**
     * Refresh the instance attributes.
     *
     * @return Promise\PromiseInterface
     */
    public function refresh(): Promise\PromiseInterface
    {
        if (!$this->exists()) {
            throw new \LogicException('Cannot refresh non-existent model.');
        }

        return Query::table(static::getTable())
            ->where(static::$primaryKey, $this->primaryKey())
            ->first()->then(function ($attributes) {
                if ($attributes === null) {
                    throw new ModelException('Error refreshing model attributes.');
                }

                $this->setAttributes((array) $attributes);

                return $this;
            });
    }

    /**
     * Create or update the instance.
     *
     * @return Promise\PromiseInterface
     */
    public function save(): Promise\PromiseInterface
    {
        if ($this->exists()) {
            return $this->update();
        } else {
            return $this->insertInternal()->then(function ($id) {
                if ($id === null) {
                    throw new ModelException('Error creating model.');
                }
                $this->attributes[static::$primaryKey] = $id;
                $this->exists = true;

                return $this->refresh();
            });
        }
    }

    public function serialize()
    {
        $attributes = $this->attributes;
        $dirty = $this->dirty;

        foreach ($this->getDateFields() as $dateField) {
            if (isset($attributes[$dateField]) && $attributes[$dateField] instanceof \DateTime) {
                $attributes[$dateField] = static::serializeDate($attributes[$dateField]);
            }
            if (isset($dirty[$dateField]) && $dirty[$dateField] instanceof \DateTime) {
                $dirty[$dateField] = static::serializeDate($dirty[$dateField]);
            }
        }

        return serialize([$this->exists, $attributes, $dirty]);
    }
    
    public function unserialize($data)
    {
        [$this->exists, $attributes, $dirty] = unserialize($data);

        foreach ($this->dates as $dateField) {
            if (isset($attributes[$dateField])) {
                $attributes[$dateField] = $this->asDatetime($attributes[$dateField]);
            }
            if (isset($dirty[$dateField])) {
                $dirty[$dateField] = $this->asDatetime($dirty[$dateField]);
            }
        }

        $this->attributes = $attributes;
        $this->dirty = $dirty;
    }

    /**
     * @param array $attributes
     * @return Promise\PromiseInterface
     */
    public function update(array $attributes = []): Promise\PromiseInterface
    {
        if (!empty($attributes)) {
            foreach ($attributes as $key => $val) {
                if ($this->attributes[$key] !== $val) {
                    $this->dirty[$key] = $val;
                }
            }
        }

        if (!$this->isDirty()) {
            return Promise\resolve($this);
        }

        $attributes = $this->dirty;
        if (static::UPDATED_FIELD && array_key_exists(static::UPDATED_FIELD, $attributes)) {
            unset($attributes[static::UPDATED_FIELD]);
        }
        unset($attributes[static::$primaryKey]);

        return static::newQuery()
            ->where(static::$primaryKey, $this->primaryKey())
            ->update($this->uncastAttributes($attributes), static::UPDATED_FIELD)
            ->then(function ($changed) {
                if ($changed !== 1) {
                    throw new ModelException('Error updating model.');
                }
                return $this->refresh();
            });
    }

    /**
     * @param mixed $value
     * @return \DateTime
     */
    protected function asDate($value): \DateTime
    {
        return new Carbon($this->asDatetime($value)->setTime(0, 0));
    }

    /**
     * @param mixed $value
     * @return \DateTime
     */
    protected function asDatetime($value): \DateTime
    {
        if ($value instanceof \DateTime) {
            return new Carbon($value);
        }

        if (is_numeric($value)) {
            return new Carbon(\DateTime::createFromFormat('U', (string) $value));
        }

        // Y-m-d
        if (is_string($value) && preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $value)) {
            return new Carbon(\DateTime::createFromFormat('Y-m-d', $value)->setTime(0, 0));
        }

        // Y-m-d H:i:s
        if (is_string($value) && preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2}) (\d{2}):(\d{2}):(\d{2})$/', $value)) {
            return new Carbon(\DateTime::createFromFormat('Y-m-d H:i:s', $value));
        }

        if (static::$dateFormat) {
            if (false !== ($dateTime = \DateTime::createFromFormat(static::$dateFormat, (string) $value))) {
                return new Carbon($dateTime);
            }
        }
        
        return new Carbon($value);
    }

    protected function getDateFields(): array
    {
        return array_merge($this->dates, [static::UPDATED_FIELD, static::CREATED_FIELD]);
    }

    /**
     * @param string $field
     * @return bool
     */
    protected function isCastable(string $field): bool
    {
        return isset(static::$casts[$field]) || in_array($field, $this->getDateFields(), true);
    }

    /**
     * Set the attributes array.
     */
    protected function setAttributes(array $attributes = [])
    {
        foreach ($attributes as $field => $value) {
            if ($this->isCastable($field)) {
                $this->attributes[$field] = $this->castAttribute($field, $value);
            } elseif ($field === static::$primaryKey && static::$primaryKeyType === 'int') {
                $this->attributes[$field] = (int) $value;
            } else {
                $this->attributes[$field] = $value;
            }

            if (isset($this->dirty[$field]) && $this->dirty[$field] === $this->attributes[$field]) {
                unset($this->dirty[$field]);
            }
        }
        return $this;
    }

    /**
     * Prepare array of attributes for database storage.
     * 
     * @param array $data
     */
    protected function uncastAttributes(array $attributes)
    {
        if (isset($attributes[0]) && is_array($attributes[0])) {
            foreach ($this->getDateFields() as $field) {
                foreach ($attributes as $key => $record) {
                    if (isset($record[$field]) && $record[$field] instanceof \DateTime) {
                        $attributes[$key][$field] = $record[$field]->format(static::$dateFormat);
                    }
                }
            }
        } else {
            foreach ($this->getDateFields() as $field) {
                if (isset($attributes[$field]) && $attributes[$field] instanceof \DateTime) {
                    $attributes[$field] = $attributes[$field]->format(static::$dateFormat);
                }
            }
        }

        return $attributes;
    }

    private function getQuery()
    {
        if (!isset($this->query)) {
            $this->query = static::newQuery();
        }
        return $this->query;
    }

    private static function callQuery()
    {
        $args = func_get_args();
        $instance = array_shift($args);
        $method = array_shift($args);

        return call_user_func_array(array($instance->getQuery(), $method), $args);
    }

    public function __call($name, $arguments)
    {
        if ($name === 'insert') {
            if (isset($arguments[0]) && isset($arguments[0][0]) && is_array($arguments[0][0])) {
                return $this->multiInsert($arguments[0]);
            }
            return $this->insertInternal(...$arguments);
        }
        $result = static::callQuery($this, $name, ...$arguments);
        if (!($result instanceof Query)) {
            return $result;
        }

        return $this;
    }

    public static function __callStatic($name, $arguments)
    {
        $instance = static::newInstance();
        if ($name === 'insert') {
            if (isset($arguments[0]) && isset($arguments[0][0]) && is_array($arguments[0][0])) {
                return $instance->multiInsert($arguments[0]);
            }
            return $instance->insertInternal(...$arguments);
        }
        $result = static::callQuery($instance, $name, ...$arguments);
        if (!($result instanceof Query)) {
            return $result;
        }

        return $instance;
    }

    public function __get(string $name)
    {
        $method = $this->attributeGetter($name);

        if (method_exists($this, $method)) {
            return call_user_func([$this, $method]);
        }
        
        return $this->getAttribute($name);
    }

    public function __isset(string $name): bool
    {
        if ($this->hasAttribute($name)) {
            return true;
        }

        return method_exists($this, $this->attributeGetter($name));
    }

    public function __set(string $name, $value)
    {
        $method = $this->attributeSetter($name);

        if (method_exists($this, $method)) {
            return call_user_func([$this, $method], $value);
        }

        $this->setAttribute($name, $value);
    }

    private function attributeGetter(string $name)
    {
        $parts = explode('_', $name);
        $method = implode(array_map('ucfirst', array_map('strtolower', $parts)));
        return 'get'.$method.'Attribute';
    }

    private function attributeSetter(string $name)
    {
        $parts = explode('_', $name);
        $method = implode(array_map('ucfirst', array_map('strtolower', $parts)));
        return 'set'.$method.'Attribute';
    }
}