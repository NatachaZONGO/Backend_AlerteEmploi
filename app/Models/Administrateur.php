<?php

namespace App\Models;

class Administrateur extends User
{
    protected static function booted()
    {
        parent::booted();
        
        static::addGlobalScope('admin', function ($query) {
            $query->whereHas('roles', function ($q) {
                $q->where('nom', 'admin');
            });
        });
    }

    public static function create(array $attributes = [])
    {
        $admin = parent::create($attributes);
        $role = \App\Models\Role::where('nom', 'admin')->first();
        if ($role) {
            $admin->roles()->attach($role);
        }
        return $admin;
    }

    // Méthodes spécifiques aux admins
    public function peutGerer($model)
    {
        return true; // Admin peut tout gérer
    }
}