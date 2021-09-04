<?php declare(strict_types=1);

namespace Fissible\Framework\Validation;

use Fissible\Framework\Collection;
use React\Promise\PromiseInterface;

class Validator
{
    protected Collection $errors;

    protected Collection $messages;

    protected Collection $rules;

    protected array $validated;

    /**
     * @param iterable $rules
     * @param iterable $messages
     */
    public function __construct(iterable $rules = [], iterable $messages = [])
    {
        $this->setRules($rules);
        $this->setMessages($messages);
    }

    /**
     * @return Collection|null
     */
    public function errors(): ?Collection
    {
        if (isset($this->errors)) {
            return $this->errors;
        }
        return null;
    }

    /**
     * @param string $ruleName
     * @return bool
     */
    public function hasRule(string $ruleName): bool
    {
        if (isset($this->rules)) {
            $rule = $this->rules->first(function ($rules, $attr) use ($ruleName) {
                foreach ($rules as $Rule) {
                    return $Rule->name === $ruleName;
                }
            });

            return !is_null($rule);
        }
        return false;
    }

    /**
     * @param string $name
     * @return bool
     */
    public function hasRuleFor(string $name): bool
    {
        return !is_null($this->getRules($name));
    }

    /**
     * @return array
     */
    public function messages(): array
    {
        if (isset($this->messages)) {
            return $this->messages->toArray();
        }
        return [];
    }

    /**
     * @param iterable $messages
     * @return self
     */
    public function setMessages(iterable $messages = []): self
    {
        if (isset($this->rules)) {
            foreach ($messages as $key => $message) {
                list($name, $ruleName) = explode('.', $key);

                if ($this->hasRuleFor($name)) {
                    foreach ($this->getRules($name) as $Rule) {
                        if ($Rule->name === $ruleName) {
                            $Rule->setMessage($message);
                        }
                    }
                }
            }
        }

        if (!($messages instanceof Collection)) {
            $messages = new Collection($messages);
        }
        $this->messages = $messages;

        return $this;
    }

    /**
     * @param string $attribute
     * @param string|array $rule
     * @param bool $replace
     * @return self
     */
    public function setRule(string $attribute, $rule, bool $replace = false): self
    {
        if (!$replace) {
            $rules = $this->rules->get($attribute, []);
            foreach ((array) $rule as $key => $_rule) {
                $rules[] = $this->getValidatedRule($_rule);
            }
        } else {
            $rules = (array) $rule;
        }

        $this->rules->set($attribute, $rules);

        return $this;
    }

    /**
     * @param iterable $data
     * @return self
     */
    public function setRules(iterable $rules): self
    {
        $this->rules = $this->normalizeRules($rules);

        return $this;
    }

    protected function getRules(string $name): ?array
    {
        return $this->rules->first(function ($rules, $_name) use ($name) {
            return $_name === $name;
        });
    }

    /**
     * @param iterable $data
     * @return PromiseInterface
     */
    public function validate(iterable $data = []): PromiseInterface
    {
        $pending = [];
        $errors = new Collection();
        $this->validated = [];
        // $this->errors = new Collection();

        $RequiredRules = $this->rules->map(function (array $rules) {
            return array_filter($rules, function (Rule $Rule) {
                return $Rule->name === 'required';
            })[0] ?? null;
        })->filter(function ($Rule) {
            return $Rule !== null;
        });

        $RequiredRules->each(function (Rule $Rule, string $attribute) use ($data, $errors) {
            if (!isset($data[$attribute])) {
                $message = str_replace([':attribute', '_'], [$attribute, ' '], $Rule->getMessage($attribute));
                $_errors = $errors->get($attribute) ?? [];
                if (!in_array($message, $_errors)) {
                    $_errors[] = $message;
                }
                $errors->set($attribute, $_errors);
            }
        });

        foreach ($data as $attribute => $value) {
            if ($this->hasRuleFor($attribute)) {
                $rules = $this->getRules($attribute);

                foreach ($rules as $Rule) {
                    if (is_null($value) && !($Rule instanceof RequiredRule)) {
                        continue;
                    }

                    $pending[] = $Rule->passes($attribute, $value);
                }
            }
        }

        return \React\Promise\all($pending)->then(function ($resolved) use ($errors, $data) {
            $validated = [];

            foreach ($resolved as $rule) {
                $Rule = $rule['Rule'];
                $attribute = $rule['field'];

                if ($rule['result'] === true) {
                    $validated[$attribute] = $data[$attribute];
                } else {
                    $message = str_replace([':attribute', '_'], [$attribute, ' '], $Rule->getMessage($attribute));
                    $_errors = $errors->get($attribute) ?? [];
                    if (!in_array($message, $_errors)) {
                        $_errors[] = $message;
                    }
                    $errors->set($attribute, $_errors);
                }
            }

            return [$validated, $errors->toArray()];
        });
    }

    /**
     * @param Rule|string|callable $rule
     * @return Rule
     */
    protected function getValidatedRule($rule): Rule
    {
        if ($rule instanceof Rule) {
            return $rule;
        }

        if (!is_string($rule) && is_callable($rule)) {
            return new Rule('custom', $rule);
        }

        if (!is_string($rule)) {
            throw new \InvalidArgumentException('Invalid rule.');
        }

        $params = [];

        if (false !== strpos($rule, ':')) {
            list($ruleName, $params) = explode(':', $rule, 2);
            if ($ruleName !== 'regex') {
                $params = explode(',', $params);
            } else {
                $params = [$params];
            }
        } else {
            $ruleName = $rule;
        }

        $params = array_filter($params);
        $ruleNameParts = explode('-', $ruleName);
        $ruleNameParts = array_map(function ($part) {
            return ucfirst(strtolower($part));
        }, $ruleNameParts);


        $className = '\\Fissible\\Framework\\Validation\\'.implode($ruleNameParts).'Rule';


        if (!class_exists($className)) {
            throw new \InvalidArgumentException('Invalid rule "'.$rule.'"');
        }

        return new $className(...$params);
    }

    /**
     * @param iterable $rules
     * @return Collection
     */
    protected function normalizeRules(iterable $rules): Collection
    {
        if (!($rules instanceof Collection)) {
            $rules = new Collection($rules);
        }
        return $rules->map(function ($rules, $name) {
            foreach ($rules as $key => $rule) {
                $rules[$key] = $this->getValidatedRule($rule);
            }
            return $rules;
        });
    }
}