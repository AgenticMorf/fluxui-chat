<?php

namespace AgenticMorf\FluxUIChat\Tests\Models;

use AgenticMorf\FluxUIChat\Tests\Database\Factories\UserFactory;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class User extends Model implements AuthenticatableContract
{
    use Authenticatable, HasFactory;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['id', 'name', 'email', 'password'];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    protected static function newFactory()
    {
        return UserFactory::new();
    }
}
