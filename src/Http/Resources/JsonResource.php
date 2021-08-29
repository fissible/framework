<?php declare(strict_types=1);

namespace Fissible\Framework\Http\Resources;

class JsonResource implements \JsonSerializable
{
    public function __construct(
        private \JsonSerializable $Resource
    )
    { }

    public function toArray($Resource): array
    {
        if (method_exists($Resource, 'toArray')) {
            return $Resource->toArray();
        }
        return (array) $Resource;
    }

    public function jsonSerialize(): array
    {
        return $this->toArray($this->Resource);
    }
}