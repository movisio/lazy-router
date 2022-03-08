<?php
declare(strict_types = 1);

namespace Movisio\Routing;

use Nette;
use Nette\Application\Routers\Route;
use Nette\Application\Routers\RouteList;
use Nette\Caching\Cache;
use Nette\Utils\Strings;

/**
 * Lazy loaded cached RouteList
 */
class LazyRouteList extends RouteList
{
    protected array $routersByPath = []; // for matching

    protected array $routersByPresenter = []; // for generating urls

    protected Cache $cache;

    protected const CACHE_KEY_GROUPS = 'lazy_routes_groups';
    protected const CACHE_KEY_BY_PATHS = 'lazy_routes_by_path';
    protected const CACHE_KEY_BY_PRESENTER = 'lazy_routes_by_presenter';

    /**
     * @param Cache       $cache
     * @param string|null $module
     */
    public function __construct(Cache $cache, string $module = null)
    {
        $this->cache = $cache;
        parent::__construct($module);
    }

    /**
     * @param callable $routesCacheLoader
     * @throws \Throwable
     */
    public function setLazyCachedRoutes(callable $routesCacheLoader) : void
    {
        [$this->routersByPath, $this->routersByPresenter] = $this->cache->load(
            self::CACHE_KEY_GROUPS,
            function (&$dependencies) use ($routesCacheLoader) {
                // load routes from user's method and pass dependencies
                $routes = call_user_func_array($routesCacheLoader, [&$dependencies]);
                // group routers by non-parametric path and presenters
                [$byPath, $byPresenter] = $this->categorizeRoutes($routes);

                foreach ($byPath as $key => $routes) {
                    // save router groups to cache
                    $this->cache->save(self::CACHE_KEY_BY_PATHS . ":" . $key, $routes);
                }
                foreach ($byPresenter as $key => $routes) {
                    // save router groups to cache
                    $this->cache->save(self::CACHE_KEY_BY_PRESENTER . ":" . $key, $routes);
                }

                return [
                    array_fill_keys(array_keys($byPath), null), // ['path' => null]
                    array_fill_keys(array_keys($byPresenter), null) // ['Module:presenter' => null]
                ];
            }
        );
    }

    /**
     * Categorize routes into groups. Grouped by the same static path and called presenter
     * @param array $routes
     * @return array[]
     */
    protected function categorizeRoutes(array $routes) : array
    {
        $byPresenter = [];
        $byPath = [];
        /** @var Route $route */
        foreach ($routes as $route) {
            // copied from \Nette\Routing\Route. This will find static part of route mask without parameters
            $parts = Strings::split($route->getMask(), '/<([^<>= ]+)(=[^<> ]*)? *([^<>]*)>|(\[!?|\]|\s*\?.*)/');
            $staticPath = rtrim($parts[0], '/');
            $byPath[$staticPath][] = $route;
            $defaults = $route->getDefaults();
            $byPresenter[$defaults['presenter']][] = $route;
        }

        return [$byPath, $byPresenter];
    }

    /**
     * @param Nette\Http\IRequest $httpRequest
     * @return array|null
     * @throws \Throwable
     */
    public function match(Nette\Http\IRequest $httpRequest) : ?array
    {
        $url = $httpRequest->getUrl()->getRelativePath();
        foreach ($this->routersByPath as $path => &$loadedRouters) {
            if (strncmp($url, $path, strlen($path)) !== 0) { // 0 = equal; <> 0 = not equal
                continue;
            }

            if (is_null($loadedRouters)) {
                $loadedRouters = $this->cache->load("lazy_routes_by_path:" . $path);
                foreach ($loadedRouters as $router) {
                    $this->add($router, $router->getFlags());
                }
            }
        }

        return parent::match($httpRequest);
    }

    /**
     * @param array                $params
     * @param Nette\Http\UrlScript $refUrl
     * @return string|null
     * @throws \Throwable
     */
    public function constructUrl(array $params, Nette\Http\UrlScript $refUrl) : ?string
    {
        $presenter = $params['presenter'] ?? null;
        if (
            $presenter &&
            array_key_exists($presenter, $this->routersByPresenter) && // presenter has cached routes
            is_null($this->routersByPresenter[$presenter]) // cached routes are not loaded
        ) {
            $routers = $this->cache->load("lazy_routes_by_presenter:" . $presenter);
            $this->routersByPresenter[$presenter] = [];
            foreach ($routers as $router) {
                $this->add($router, $router->getFlags());
                $this->routersByPresenter[$presenter][] = $router;
            }
        }

        return parent::constructUrl($params, $refUrl);
    }
}
