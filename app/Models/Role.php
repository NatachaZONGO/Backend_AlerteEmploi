<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    //
    protected $fillable = [
        'nom',
        'description',
    ];

    //Relation avec les utilisateurs
    // public function users()
    // {
    //     return $this->belongsToMany(User::class);
    // }

    public function users()
    {
        return $this->belongsToMany(User::class, 'role_user'); // Table de jointure
    }
    
}
