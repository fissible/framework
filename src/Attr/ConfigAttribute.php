<?php declare(strict_types=1);

namespace Fissible\Framework\Attr;

#[\Attribute]
class ConfigAttribute
{
    public function __construct(
        private string $key,
        private mixed $default = null
    ) {}
}