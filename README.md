# Nette lazy router
Nette RouteList with capability of lazy loading routes with prefix.  
It is usable especially when you have many (hundreds?) of routes, which slows your system and you don't need to load them all for every request.

For example, when your application have more independent modules and every module has its own routes, they can be separated and lazy loaded per-module.

Note that this router was created especially for hundreds of static routes
for large API and should not be used for normal route `<module>/<presenter>/<action>`.

## Usage
By default, LazyRouteList behaves exactly like ordinary RouteList.
For lazy loading function, use `setLazyCachedRoutes` method.
It has only one argument: callback for loading routes into cache.
Return value of callback should be array of routers.
Function also accepts argument $dependencies, just like nette Cache::load callback.

```php
$router = new LazyRouteList($cache);
$router->setLazyCachedRoutes(function (&$dependencies) : array {
    
    // .... read routes from files or whatever ...
    $routes = [
        new \Nette\Routing\Route('/api/v1/route/to/resource', 'Module:ApiPresenter:action')    
    ];
    
    // optional cache dependency for automatic cache invalidation and routes reloading
    $dependencies[\Nette\Caching\Cache::FILES] = ['path/to/file', 'path/to/another/file'];
    
    return $routes;
});
```

Routes will be separated into groups by static part of path (without parameters) and by presenters and will be cached.

When matching, router will load only routes with matching static part of path.  
When creating links, only routes matching target presenter will be loaded from cache.

