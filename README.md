# Nette lazy router
Nette RouteList with capability of lazy loading routes with prefix.  
It is usable especially when you have many (hundreds?) of routes, which slows your system and you don't need to load them all for every request.

For example, when your application have more independent modules and every module has its own routes, they can be separated and lazy loaded per-module.

## Usage
By default, LazyRouteList behaves exactly like ordinary RouteList. For lazy loading function, use `lazyLoadPath` method:

```php
$router = new LazyRouteList();

// this route will not be lazy loaded
$router->addRoute("not/lazy/loaded", "Presenter:action");

// routes will be lazy loaded if url begins with "lazy" and routes will be prefixed
$router->lazyLoadPath("lazy", function(LazyRouteList $routeList) {
    // route will match url "lazy/test"
    $routeList->addRoute("test", "TestPresenter:default");
    
    // route will match url "lazy/test2/<param>"
    $routeList->addRoute("test2/<param>", "ParamPresenter:default");
    
    // route will match url "lazy/lazy/test"
    $routeList->addRoute("lazy/test", "AnotherTestPresenter:default");
});

// with third parameter false, routes will not be prefixed
$router->lazyLoadPath("lazy", function (LazyRouteList $routeList) {
    // route will match url "lazy/test", not "lazy/lazy/test"
    $routeList->addRoute("lazy/test", "TestPresenter:default");
    
    // route will match url "lazy/test2/<param>"
    $routeList->addRoute("lazy/test2/<param>", "ParamPresenter:default");
}, false);

```

## Creating links
By default, lazy loading of routes is not triggered for link creating. Router does not know if you want to create link for lazy loaded routes or other routes. When you do not want to create link for lazy loaded routes, you do not want router to load them and slow your system down.

But if you want routes to be lazy loaded, you need to add parameter with key `LazyRouteList::LAZY_LOAD_KEY` and value equal to defined lazy loaded path. If path from parameter matches prefixed lazy router, routes will be loaded and parameter will be removed, so it does not appear in generated URL.

Example with above defined routes:
```php
// call in presenter
$this->link(
    "ParamPresenter:default",
    [
        LazyRouteList::LAZY_LOAD_KEY => "lazy", // this parameter will trigger loading routes from path "lazy"
        'param' => 123,
        'otherParam' => 111
    ]
);
// generated url will be "/lazy/test2/123?otherParam=111"
```

In latte template, call is similar and generated link will be the same as above.
```
{link ParamPresenter:default, Movisio\Routing\LazyRouteList::LAZY_LOAD_KEY => "lazy, "param" => 123, "otherParam" => 111}
```

When you are generating many links to lazy loaded section, you just need to add trigger parameter once, to the first call of `link()` or `{link}`. After routes are loaded, parameter `LAZY_LOAD_KEY` is not necessary.

