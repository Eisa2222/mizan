<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Annotation;
use App\Models\User;

class AnnotationPolicy
{
    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::AnnotationsCreate);
    }

    public function delete(User $user, Annotation $annotation): bool
    {
        return $user->hasPermission(Permission::AnnotationsDelete)
            && $annotation->user_id === $user->id;
    }
}
