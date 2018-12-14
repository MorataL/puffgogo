<?php

namespace App;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Zizaco\Entrust\Traits\EntrustUserTrait;

class User extends Authenticatable
{

    use EntrustUserTrait;
    protected $fillable = [
            'name', 'email', 'password',
    ];
    protected $hidden = [
            'password', 'remember_token',
    ];

}
