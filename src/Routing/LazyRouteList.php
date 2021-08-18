<?php
declare(strict_types = 1);

namespace Movisio\Routing;

use Nette\Application\Routers\RouteList;
use Nette\Http\IRequest;
use Nette\Http\UrlScript;

/**
 * Lazy route list
 */
class LazyRouteList extends RouteList
{
    /** Lazy loading is enabled only when $lazyLoadFactory is set */
    protected bool $isLoaded = true;

    /** @var callable */
    protected $lazyLoadFactory;

    /** Path, which will be lazy loaded */
    protected ?string $lazyPath = null;

    /** Key for link generation - trigger lazy loading  */
    public const LAZY_LOAD_KEY = '__LAZY_LOAD_PATH__';

    /**
     * @param IRequest $httpRequest
     * @return array|null
     */
    public function match(IRequest $httpRequest) : ?array
    {
        // first check, if url is lazy-loaded
        if ($this->lazyPath) {
            $url = $httpRequest->getUrl();
            if (strncmp($url->getRelativePath(), $this->lazyPath, strlen($this->lazyPath))) {
                return null;
            }
        }

        if (!$this->isLoaded) {
            $this->load();
        }

        return parent::match($httpRequest);
    }

    /**
     * @param array     $params
     * @param UrlScript $refUrl
     * @return string|null
     */
    public function constructUrl(array $params, UrlScript $refUrl) : ?string
    {
        if (isset($params[self::LAZY_LOAD_KEY])) {
            $path = rtrim($params[self::LAZY_LOAD_KEY], '/') . '/';
            if ($path === $this->lazyPath && !$this->isLoaded) {
                $this->load();
                unset($params[self::LAZY_LOAD_KEY]);
            }
        }

        return parent::constructUrl($params, $refUrl);
    }

    /**
     * Lazy Load routes
     */
    protected function load() : void
    {
        call_user_func($this->lazyLoadFactory, $this);
        $this->isLoaded = true;
    }

    /**
     * @param string   $path
     * @param callable $loadFactory
     * @param bool     $prefixPath Should inner routes be prefixed with lazy load path?
     * @return self
     */
    public function lazyLoadPath(string $path, callable $loadFactory, bool $prefixPath = true) : self
    {
        if ($prefixPath) {
            $router = $this->withPath($path);
        } else {
            $router = new static;
            $router->parent = $this;
            $this->add($router);
        }
        $router->lazyPath = rtrim($path, '/') . '/';
        $router->isLoaded = false;
        $router->lazyLoadFactory = $loadFactory;
        return $router;
    }
}
