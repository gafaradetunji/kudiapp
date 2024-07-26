<?php

namespace App\Models;
use App\Models\User;
use Carbon\Carbon;

class UserDetail extends User
{
    protected $table = 'user_table';
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'fingerprint',
        'code',
        'codetime',
        'phone',
        'middlename',
        'lastname',
        'date_of_birth',
        'isverified',
        'uniqueID',
        'walletaddress',
        'bankname',
        'bankaccount',
        'bankaccountname',
        'cardnumber',
        'cardexpiredate',
        'cardcvv',
        'totalamount',
        '2fapin',
    ];
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}