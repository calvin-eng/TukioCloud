<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        // Read from session to avoid re-hydrating the User model, which would
        // re-trigger this scope recursively (the User model uses BelongsToTenant).
        // Tenant ID is stored in the session by LoginForm and Register flows.
        if ($tenantId = session('tenant_id')) {
            $builder->where($model->getTable().'.tenant_id', $tenantId);
        }
    }
}
