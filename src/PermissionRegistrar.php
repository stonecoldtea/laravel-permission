<?php

namespace Spatie\Permission;

use Illuminate\Support\Collection;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Cache\Repository;
use Spatie\Permission\Contracts\Permission;
use Illuminate\Contracts\Auth\Authenticatable;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

class PermissionRegistrar
{
    /** @var \Illuminate\Contracts\Auth\Access\Gate */
    protected $gate;

    /** @var \Illuminate\Contracts\Cache\Repository */
    protected $cache;

    /** @var string */
    protected $cacheKey = 'spatie.permission.cache';

    public function __construct(Gate $gate, Repository $cache)
    {
        if(isset($GLOBALS['dokimi_cacheKey']))
        {
            $this->cacheKey = $GLOBALS['dokimi_cacheKey'];
        } else {
            $this->cacheKey = Request()->header('host');
        }
        $this->gate = $gate;
        $this->cache = $cache;
    }

    public function registerPermissions(): bool
    {
        $this->gate->before(function (Authenticatable $user, string $ability) {
            try {
                if (method_exists($user, 'hasPermissionTo')) {
                    return $user->hasPermissionTo($ability);
                }
            } catch (PermissionDoesNotExist $e) {
            }
        });

        return true;
    }

    public function forgetCachedPermissions()
    {
        $this->cache->forget($this->cacheKey);
    }

    public function getPermissions(): Collection
    {
        return $this->cache->remember($this->cacheKey, config('permission.cache_expiration_time'), function () {
            $slug = env('APP_DOMAIN');
            if($this->cacheKey!=$slug) // not dokimi.app
            {
                $slug = str_replace('.'.$slug,'',$this->cacheKey);
            }
            return app(Permission::class)->where('name','LIKE', $slug.'_%')->with('roles')->get();
        });
    }
}
