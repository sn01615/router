<?php

/**
 * @author      Bram(us) Van Damme <bramus@bram.us>
 * @copyright   Copyright (c), 2013 Bram(us) Van Damme
 * @license     MIT public license
 */
namespace Bramus\Router;

use Closure;

/**
 * Class Router.
 */
class Router
{
    /**
     * @var string Current base route, used for (sub)route mounting
     */
    private $prefix = '';

    /**
     * @var string The Request Method that needs to be handled
     */
    private $requestedMethod = '';

    /**
     * @var string The Server Base Path for Router Execution
     */
    private $serverBasePath;

    /**
     * @var string Default Controllers Namespace
     */
    private $namespace = '';

    /**
     * @var string The sub route domain
     */
    private $domain;

    /**
     * @var Attributes
     */
    private $attributes;

    /**
     * Router constructor.
     */
    public function __construct()
    {
        $this->attributes = new Attributes();
    }

    /**
     * Store a before middleware route and a handling function to be executed when accessed using one of the specified methods.
     *
     * @param string          $methods Allowed methods, | delimited
     * @param string          $pattern A route pattern such as /about/system
     * @param object|callable $fn      The handling function to be executed
     */
    public function before($methods, $pattern, $fn)
    {
        $this->match($methods, $pattern, $fn, 'beforeRoutes');
    }

    /**
     * Store a route and a handling function to be executed when accessed using one of the specified methods.
     *
     * @param string          $methods Allowed methods, | delimited
     * @param string          $pattern A route pattern such as /about/system
     * @param object|callable $fn      The handling function to be executed
     * @param string          $group afterRoutes or beforeRoutes
     */
    public function match($methods, $pattern, $fn, $group = 'afterRoutes')
    {
        $pattern = $this->getPrefix() . '/' . trim($pattern, '/');
        $pattern = $this->getPrefix() ? rtrim($pattern, '/') : $pattern;

        if (!is_callable($fn)) {
            // Adjust controller class if namespace has been set
            if ($this->getNamespace() !== '') {
                if (substr($fn, 0, 1) != '\\') {
                    $fn = $this->getNamespace() . '\\' . $fn;
                }
            }
        }

        $domain = $this->getDomain();

        if (is_string($methods)) {
            $methods = explode('|', $methods);
        }
        foreach ($methods as $method) {
            $this->attributes->addRoute($group, $method, array(
                'pattern' => $pattern,
                'fn' => $fn,
                'domain' => $domain,
            ));
        }
    }

    /**
     * Shorthand for a route accessed using any method.
     *
     * @param string          $pattern A route pattern such as /about/system
     * @param object|callable $fn      The handling function to be executed
     */
    public function all($pattern, $fn)
    {
        $this->match('GET|POST|PUT|DELETE|OPTIONS|PATCH|HEAD', $pattern, $fn);
    }

    /**
     * Shorthand for a route accessed using GET.
     *
     * @param string          $pattern A route pattern such as /about/system
     * @param object|callable $fn      The handling function to be executed
     */
    public function get($pattern, $fn)
    {
        $this->match('GET', $pattern, $fn);
    }

    /**
     * Shorthand for a route accessed using POST.
     *
     * @param string          $pattern A route pattern such as /about/system
     * @param object|callable $fn      The handling function to be executed
     */
    public function post($pattern, $fn)
    {
        $this->match('POST', $pattern, $fn);
    }

    /**
     * Shorthand for a route accessed using PATCH.
     *
     * @param string          $pattern A route pattern such as /about/system
     * @param object|callable $fn      The handling function to be executed
     */
    public function patch($pattern, $fn)
    {
        $this->match('PATCH', $pattern, $fn);
    }

    /**
     * Shorthand for a route accessed using DELETE.
     *
     * @param string          $pattern A route pattern such as /about/system
     * @param object|callable $fn      The handling function to be executed
     */
    public function delete($pattern, $fn)
    {
        $this->match('DELETE', $pattern, $fn);
    }

    /**
     * Shorthand for a route accessed using PUT.
     *
     * @param string          $pattern A route pattern such as /about/system
     * @param object|callable $fn      The handling function to be executed
     */
    public function put($pattern, $fn)
    {
        $this->match('PUT', $pattern, $fn);
    }

    /**
     * Shorthand for a route accessed using OPTIONS.
     *
     * @param string          $pattern A route pattern such as /about/system
     * @param object|callable $fn      The handling function to be executed
     */
    public function options($pattern, $fn)
    {
        $this->match('OPTIONS', $pattern, $fn);
    }

    /**
     * Mounts a collection of callbacks onto a base route.
     *
     * @param string   $baseRoute The route sub pattern to mount the callbacks on
     * @param callable $fn        The callback method
     */
    public function mount($baseRoute, $fn)
    {
        // Track current base route
        $curBaseRoute = $this->getPrefix();

        // Build new base route string
        $this->setPrefix($curBaseRoute . $baseRoute);

        // Call the callable
        call_user_func($fn);

        // Restore original base route
        $this->setPrefix($curBaseRoute);
    }

    /**
     * Get all request headers.
     *
     * @return array The request headers
     */
    public function getRequestHeaders()
    {
        $headers = array();

        // If getallheaders() is available, use that
        if (function_exists('getallheaders')) {
            $headers = getallheaders();

            // getallheaders() can return false if something went wrong
            if ($headers !== false) {
                return $headers;
            }
        }

        // Method getallheaders() not available or went wrong: manually extract 'm
        foreach ($_SERVER as $name => $value) {
            if ((substr($name, 0, 5) == 'HTTP_') || ($name == 'CONTENT_TYPE') || ($name == 'CONTENT_LENGTH')) {
                $headers[str_replace(array(' ', 'Http'), array('-', 'HTTP'), ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }

        return $headers;
    }

    /**
     * Get the request method used, taking overrides into account.
     *
     * @return string The Request method to handle
     */
    public function getRequestMethod()
    {
        // Take the method as found in $_SERVER
        $method = $_SERVER['REQUEST_METHOD'];

        // If it's a HEAD request override it to being GET and prevent any output, as per HTTP Specification
        // @url http://www.w3.org/Protocols/rfc2616/rfc2616-sec9.html#sec9.4
        if ($_SERVER['REQUEST_METHOD'] == 'HEAD') {
            ob_start();
            $method = 'GET';
        }

        // If it's a POST request, check for a method override header
        elseif ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $headers = $this->getRequestHeaders();
            if (isset($headers['X-HTTP-Method-Override']) && in_array($headers['X-HTTP-Method-Override'], array('PUT', 'DELETE', 'PATCH'))) {
                $method = $headers['X-HTTP-Method-Override'];
            }
        }

        return $method;
    }

    /**
     * Set a Default Lookup Namespace for Callable methods.
     *
     * @param string $namespace A given namespace
     */
    public function setNamespace($namespace)
    {
        if (is_string($namespace)) {
            $this->namespace = $namespace;
        }
    }

    /**
     * Set sub namespace
     *
     * @param string $namespace A given namespace
     */
    private function setSubNamespace($namespace)
    {
        if (is_string($namespace)) {
            if ($this->getNamespace()) {
                $namespace = rtrim($this->getNamespace(), '\\') . '\\' . ltrim($namespace, '\\');
            }
            $this->setNamespace($namespace);
        }
    }

    /**
     * Get the given Namespace before.
     *
     * @return string The given Namespace if exists
     */
    public function getNamespace()
    {
        return $this->namespace;
    }

    /**
     * Handle 404 page
     */
    private function handle404()
    {
        if (isset($this->attributes->notFoundCallback['404'])) {
            foreach ($this->attributes->notFoundCallback['404'] as $item) {
                $domain = $this->getCurrentDomain();
                if ($item['domain']) {
                    if ($domain == $item['domain']) {
                        return $this->invoke($item['fn']);
                    }
                } else {
                    return $this->invoke($item['fn']);
                }
            }
        }
        if (!headers_sent()) {
            header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');
        } else {
            echo '404 Not Found';
        }

        return null;
    }

    /**
     * Execute the router: Loop all defined before middleware's and routes, and execute the handling function if a match was found.
     *
     * @param object|callable $callback Function to be executed after a matching route was handled (= after router middleware)
     *
     * @return bool
     */
    public function run($callback = null)
    {
        // Define which method we need to handle
        $this->requestedMethod = $this->getRequestMethod();

        // Handle all before middlewares
        if (isset($this->attributes->beforeRoutes[$this->requestedMethod])) {
            $this->handle($this->attributes->beforeRoutes[$this->requestedMethod]);
        }

        // Handle all routes
        $numHandled = 0;
        $result = null;
        if (isset($this->attributes->afterRoutes[$this->requestedMethod])) {
            list($numHandled, $result) = $this->handle($this->attributes->afterRoutes[$this->requestedMethod], true);
        }

        // If no route was handled, trigger the 404 (if any)
        if ($numHandled === 0) {
            $this->handle404();
        } // If a route was handled, perform the finish callback (if any)
        else {
            if ($callback && is_callable($callback)) {
                $callback();
            }
        }

        // If it originally was a HEAD request, clean up after ourselves by emptying the output buffer
        if ($_SERVER['REQUEST_METHOD'] == 'HEAD') {
            ob_end_clean();
        }

        $response = new Response($result);
        $response->handle();

        // Return true if a route was handled, false otherwise
        return $numHandled !== 0;
    }

    /**
     * Set the 404 handling function.
     *
     * @param object|callable $fn The function to be executed
     */
    public function set404($fn)
    {
        if (!is_callable($fn)) {
            $fn = rtrim($this->getNamespace(), '\\') . '\\' . ltrim($fn, '\\');
        }
        $domain = $this->getDomain();
        $this->attributes->addRoute('notFoundCallback', '404', array(
            'fn' => $fn,
            'domain' => $domain,
        ));
    }

    /**
     * Handle a a set of routes: if a match is found, execute the relating handling function.
     *
     * @param array $routes       Collection of route patterns and their handling functions
     * @param bool  $quitAfterRun Does the handle function need to quit after one route was matched?
     *
     * @return array The number of routes handled and result
     */
    private function handle($routes, $quitAfterRun = false)
    {
        $result = null;

        // Counter to keep track of the number of routes we've handled
        $numHandled = 0;

        // The current page URL
        $uri = $this->getCurrentUri();
        // The current page domain
        $domain = $this->getCurrentDomain();

        // Loop all routes
        foreach ($routes as $route) {
            if (isset($route['domain']) && $route['domain']) {
                if ($domain != $route['domain']) {
                    continue;
                }
            }

            // Replace all curly braces matches {} into word patterns (like Laravel)
            $route['pattern'] = preg_replace('/\/{(.*?)}/', '/(.*?)', $route['pattern']);

            // we have a match!
            if (preg_match_all('#^' . $route['pattern'] . '$#', $uri, $matches, PREG_OFFSET_CAPTURE)) {
                // Rework matches to only contain the matches, not the orig string
                $matches = array_slice($matches, 1);

                // Extract the matched URL parameters (and only the parameters)
                $params = array_map(function ($match, $index) use ($matches) {

                    // We have a following parameter: take the substring from the current param position until the next one's position (thank you PREG_OFFSET_CAPTURE)
                    if (isset($matches[$index + 1]) && isset($matches[$index + 1][0]) && is_array($matches[$index + 1][0])) {
                        return trim(substr($match[0][0], 0, $matches[$index + 1][0][1] - $match[0][1]), '/');
                    } // We have no following parameters: return the whole lot

                    return isset($match[0][0]) ? trim($match[0][0], '/') : null;
                }, $matches, array_keys($matches));

                // Call the handling function with the URL parameters if the desired input is callable
                $result = $this->invoke($route['fn'], $params);

                ++$numHandled;

                // If we need to quit, then quit
                if ($quitAfterRun) {
                    break;
                }
            }
        }

        // Return the number of routes handled and result
        return array($numHandled, $result);
    }

    /**
     * Set sub routes uri prefix.
     * @param $prefix string A router prefix such as /admin
     * @return $this
     */
    public function prefix($prefix)
    {
        $router = clone $this;
        $router->setSubPrefix($prefix);

        return $router;
    }

    /**
     * Set sub routes controller namespace.
     * @param $namespace string A controller namespace such as Admin or \Admin
     * @return Router
     */
    public function ns($namespace)
    {
        $router = clone $this;
        $router->setSubNamespace($namespace);

        return $router;
    }

    /**
     * Set sub route domain
     * @param string $domain The sub domain
     * @param string $delimiter Use this string to join to the parent domain
     * @return Router
     */
    public function domain($domain, $delimiter = '.')
    {
        $router = clone $this;
        $router->setSubDomain($domain, $delimiter);

        return $router;
    }

    /**
     * Define sub routes
     * @param Closure $callable $callable A callable namespace such as
     *  function (\Bramus\Router\Router $router) {
     *      $router->get('info', 'NovelController@getNovelInfo');
     *  }
     * @return Router
     */
    public function group(Closure $callable)
    {
        $router = clone $this;
        $callable($router);

        return $router;
    }

    private function invoke($fn, $params = array())
    {
        $result = null;
        if (is_callable($fn)) {
            $result = call_user_func_array($fn, $params);
        }

        // If not, check the existence of special parameters
        elseif (stripos($fn, '@') !== false) {
            // Explode segments of given route
            list($controller, $method) = explode('@', $fn);
            // Check if class exists, if not just ignore and check if the class exists on the default namespace
            if (class_exists($controller)) {
                // First check if is a static method, directly trying to invoke it.
                // If isn't a valid static method, we will try as a normal method invocation.
                $result = call_user_func_array(array(new $controller(), $method), $params);
            }
        } elseif (stripos($fn, '::') !== false) {
            // Explode segments of given route
            list($controller, $method) = explode('::', $fn);
            // Check if class exists, if not just ignore and check if the class exists on the default namespace
            if (class_exists($controller)) {
                // Try to call the method as an non-static method. (the if does nothing, only avoids the notice)
                $result = forward_static_call_array(array($controller, $method), $params);
            }
        }

        return $result;
    }

    /**
     * Define the current relative URI.
     *
     * @return string
     */
    public function getCurrentUri()
    {
        // Get the current Request URI and remove rewrite base path from it (= allows one to run the router in a sub folder)
        $uri = substr(rawurldecode($_SERVER['REQUEST_URI']), strlen($this->getBasePath()));

        // Don't take query params into account on the URL
        if (strstr($uri, '?')) {
            $uri = substr($uri, 0, strpos($uri, '?'));
        }

        // Remove trailing slash + enforce a slash at the start
        return '/' . trim($uri, '/');
    }

    /**
     * Get current request domain
     * @return null|string Domain
     */
    private function getCurrentDomain()
    {
        $domain = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : null;

        return $domain;
    }

    /**
     * Return server base Path, and define it if isn't defined.
     *
     * @return string
     */
    public function getBasePath()
    {
        // Check if server base path is defined, if not define it.
        if ($this->serverBasePath === null) {
            $this->serverBasePath = implode('/', array_slice(explode('/', $_SERVER['SCRIPT_NAME']), 0, -1)) . '/';
        }

        return $this->serverBasePath;
    }

    /**
     * Explicilty sets the server base path. To be used when your entry script path differs from your entry URLs.
     * @see https://github.com/bramus/router/issues/82#issuecomment-466956078
     *
     * @param string
     */
    public function setBasePath($serverBasePath)
    {
        $this->serverBasePath = $serverBasePath;
    }

    /**
     * @return string
     */
    private function getPrefix()
    {
        return $this->prefix;
    }

    /**
     * @param string $prefix
     */
    private function setPrefix($prefix)
    {
        $this->prefix = $prefix;
    }

    /**
     * Set sub prefix
     * @param $prefix
     */
    private function setSubPrefix($prefix)
    {
        if ($this->getPrefix()) {
            $prefix = rtrim($this->getPrefix(), '/') . '/' . ltrim($prefix, '/');
        }
        $this->setPrefix($prefix);
    }

    /**
     * @return string
     */
    private function getDomain()
    {
        return $this->domain;
    }

    /**
     * @param string $domain
     */
    private function setDomain($domain)
    {
        $this->domain = $domain;
    }

    /**
     * Merge sub domain
     * @param string $domain
     * @param string $delimiter
     */
    private function setSubDomain($domain, $delimiter = '.')
    {
        if ($this->getDomain()) {
            $domain = $domain . $delimiter . $this->getDomain();
        }
        $this->setDomain($domain);
    }
}
