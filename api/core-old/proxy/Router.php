<?php

class ArteRouter
{
  private static ArteRouter $inst;

  private Route $route;
  private array $routes;

  public static function getInst(): ArteRouter
  {
    if (!isset(static::$inst))
      static::$inst = new ArteRouter;

    return static::$inst;
  }

  public function __construct()
  {
    $this->route = new Route;

    $requestUri = $_SERVER['REQUEST_URI'] ?? "";
    if (($pos = strpos($requestUri, API_BASE)) !== false)
      $requestUri = substr($requestUri, $pos + strlen(API_BASE));

    $this->route->uri = explode('?', ltrim(parse_url($requestUri, PHP_URL_PATH), '/'))[0];
    $this->route->test = (strpos($this->route->uri, 'test/') === 0);
    $this->route->uri = trim(preg_replace('/^test\//', '', $this->route->uri), '/');

    $this->routes = json_decode(file_get_contents(ROUTES_JSON), true);
  }

  public function addRoutes(array $routes)
  {
    $this->routes = array_merge($this->routes, $routes);
  }

  public function route(): Route
  {
    foreach ($this->routes as $pattern => $endpoint) {
      if (is_array($params = $this->matching($pattern))) {
        if (file_exists($endpoint))
          require $endpoint;
        else
          require ENDPOINTS_DIR . "/$endpoint";

        $endpoint_name = pathinfo($endpoint, PATHINFO_FILENAME);
        $this->route->endpoint = new $endpoint_name;
        $this->route->endpoint->request = [...$this->route->endpoint->request, ...$params];
        break;
      }
    }

    return $this->route;
  }

  private function matching(string $pattern): ?array
  {
    $patternSections = explode('/', trim($pattern, '/'));

    if (count($patternSections) !== count(explode('/', $this->route->uri)))
      return null;

    $params = [];

    foreach ($patternSections as $index => $patternSection) {
      if (
        $patternSection !== explode('/', $this->route->uri)[$index] &&
        !preg_match('/\{(.+?)\}/', $patternSection)
      ) {
        return null;
      }

      if (preg_match('/\{(.+?)\}/', $patternSection, $matches))
        $params[$matches[1]] = explode('/', $this->route->uri)[$index];
    }

    return $params;
  }
}

class Route
{
  public string $uri;
  public Endpoint $endpoint;
  public bool $test = false;
}

/**
 * Arte Core Routes
 */
ArteRouter::getInst()->addRoutes([
  'arte/installPlugin' => CORE_DIR . '/API/endpoints/InstallPlugin.php',
  'arte/uninstallPlugin' => CORE_DIR . '/API/endpoints/UninstallPlugin.php',
  'arte/reinstallPlugin' => CORE_DIR . '/API/endpoints/ReinstallPlugin.php',
  'arte/update' => CORE_DIR . '/API/endpoints/UpdateArte.php'
]);
