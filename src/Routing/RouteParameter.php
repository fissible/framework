<?php declare(strict_types=1);

namespace Fissible\Framework\Routing;

class RouteParameter
{
    public function __construct(
        private string $name,
        private bool $required = true
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }
}