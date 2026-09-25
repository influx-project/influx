<?php

use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Anyone who can view a service can watch its live checks.
Broadcast::channel('services.{service}.live', function (User $user, Service $service) {
    return $user->can('view', $service);
});
