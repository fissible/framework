<?php declare(strict_types=1);

namespace Fissible\Framework\Http\Resources;

use Fissible\Framework\Models\Model;

class UserResource extends JsonResource
{
    public function toArray($User): array
    {
        /*
            id INTEGER PRIMARY KEY,
            email VARCHAR (255) NOT NULL UNIQUE,
            password VARCHAR (255),
            verification_code VARCHAR (255),
            verified_at TIMESTAMP,
            name_first VARCHAR(255) NOT NULL,
            name_last VARCHAR(255) NOT NULL,
            is_closed BOOL DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT (strftime('%s','now')),
            updated_at TIMESTAMP
        */
        return [
            'id' => $User->id,
            'first_name' => $User->name_first,
            'last_name' => $User->name_last,
            'email' => $User->email,
            'verification_code' => $User->verification_code,
            'verified_at' => $User->verified_at,
            'is_close' => $User->is_closed,
            'created_at' => $User->created_at->toIso8601String()
        ];
    }
}