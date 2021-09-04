<?php declare(strict_types=1);

namespace Fissible\Framework\Repositories;

use Fissible\Framework\Models\User;
use Fissible\Framework\Services\AuthService;
use React\Promise;

class UserRepository
{
    public static function count(array $criteria = [])
    {
        $Query = User::query();

        if (!empty($criteria)) {
            $Query->where($criteria);
        }

        return $Query->count();
    }

    public static function get(array $criteria = [])
    {
        $Query = User::query();

        if (!empty($criteria)) {
            $Query->where($criteria);
        }

        return $Query->get();
    }

    public static function getById(int|string $id): Promise\PromiseInterface
    {
        return User::find($id);
    }

    public static function getForLogin(string $value, string $field = 'email'): Promise\PromiseInterface
    {
        return User::where($field, $value)->first();
    }

    public static function getForVerification(string $value, string $field = 'verification_code'): Promise\PromiseInterface
    {
        return User::where($field, $value)->first();
    }

    public static function create(array $attributes): Promise\PromiseInterface
    {
        $attributes['verification_code'] = User::generateVerificationCode();

        if (isset($attributes['password'])) {
            $attributes['password'] = AuthService::hash($attributes['password']);
        }

        return User::create($attributes);
    }

    public static function update(User $User, array $attributes): Promise\PromiseInterface
    {
        if (isset($attributes['password'])) {
            $attributes['password'] = AuthService::hash($attributes['password']);
        }
        
        return $User->update($attributes);
    }

    public static function delete(int|string $id): Promise\PromiseInterface
    {
        return User::find($id)->then(function (User $User) {
            return $User->delete();
        });
    }
}