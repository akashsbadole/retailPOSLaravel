<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Spatie\Permission\Traits\HasRoles;


class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];


    public function cart(): HasMany
    {
        return $this->hasMany(Cart::class, 'customer_id');
    }

    public function cashierShifts(): HasMany
    {
        return $this->hasMany(\App\Models\CashierShift::class);
    }

    public function currentShift(): ?\App\Models\CashierShift
    {
        return $this->cashierShifts()->open()->first();
    }

    public function salesReturns()
    {
        return $this->hasMany(\App\Models\SalesReturn::class);
    }

    public function approvedReturns()
    {
        return $this->hasMany(\App\Models\SalesReturn::class, 'approved_by');
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }
}
