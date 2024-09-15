<?php

class ArteCache
{
  private array $data = [];
  private array $fetched_at = [];
  private array $loaders = [];
  private array $ttl = []; // in seconds
  private static ?ArteCache $inst = null;
  public array $logs = [];

  const DEFAULT_TTL = 120; // seconds

  // Singleton pattern
  public static function getInst(): ArteCache
  {
    if (self::$inst === null) {
      self::$inst = new ArteCache();
    }

    return self::$inst;
  }

  // Install caching for methods of an object
  public function install(object $object, array $to_be_cached): void
  {
    foreach ($to_be_cached as $key => $info) {
      if (is_string($info)) {
        $method = $info;
        $ttl = self::DEFAULT_TTL;
      } else if (is_array($info)) {
        $method = $info[0];
        $ttl = $info[1];
      } else
        throw new InvalidArguments("Expected string or array info but received this: " . print_r($info, true));

      $this->register($key, [$object, $method], $ttl);
    }
  }

  // Register a method for caching
  public function register(string $key, mixed $loader, int $ttl): void
  {
    if ($ttl < 0) {
      trigger_error("Received TTL below zero for loading $key. Defaulting to 0", E_USER_WARNING);
      $ttl = 0;
    }

    $this->loaders[$key] = $loader;
    $this->ttl[$key] = $ttl;
  }

  // Check if a key is registered
  public function isRegistered(string $key): bool
  {
    return isset($this->loaders[$key]);
  }

  // Get cached data or fetch if needed
  public function get(string $key, array $args = [], bool $force_reload = false): mixed
  {
    $start = microtime(true);
    if (!$this->isRegistered($key)) {
      throw new UnregisteredLoader($key);
    }

    // Generate a unique cache key based on the method name and arguments
    $cache_key = $this->generateCacheKey($key, $args);

    if ($force_reload || !isset($this->data[$cache_key]) || $this->isExpired($cache_key)) {
      $this->data[$cache_key] = call_user_func_array($this->loaders[$key], $args);
      $this->fetched_at[$cache_key] = new Ndate();
    }

    $taken_time = microtime(true) - $start;
    if ($taken_time < 0.1)
      $taken_time = 0;
    $this->logs[] = "loaded $key in $taken_time secs at " . (new Ndate)->format(Ndate::DATE_TIME);
    return $this->data[$cache_key];
  }

  private function generateCacheKey(string $key, array $args): string
  {
    return $key . ':' . md5(serialize($args));
  }

  private function isExpired(string $cache_key): bool
  {
    if (!isset($this->fetched_at[$cache_key])) {
      return true;
    }

    $now = new Ndate();
    return $this->fetched_at[$cache_key]->secondsUntil($now) >= $this->ttl[explode(':', $cache_key)[0]];
  }

  // Set cached data manually
  public function set(string $key, mixed $val, array $args = []): void
  {
    if (!$this->isRegistered($key)) {
      throw new UnregisteredLoader($key);
    }

    $cache_key = $this->generateCacheKey($key, $args);
    $this->data[$cache_key] = $val;
    $this->fetched_at[$cache_key] = new Ndate();
  }

  // Export cache data to a file
  public function export(string $path = null): string
  {
    do {
      $path = TMP_DIR . '/' . md5(microtime(true) . random_int(1, 99999)) . '.json';
    } while (file_exists($path));

    $exportData = [];
    foreach ($this->data as $key => $val) {
      $exportData[$key] = [
        'data' => $val,
        'fetched_at' => $this->fetched_at[$key]->format(Ndate::DATE_TIME)
      ];
    }
    file_put_contents($path, json_encode($exportData));
    return $path;
  }

  // Import cache data from a file
  public function import(string $path): void
  {
    if (!file_exists($path)) {
      echo "WARNING: Couldn't load cache from $path because it doesn't exist\n";
      return;
    }

    $cached_data = json_decode(file_get_contents($path), true);
    foreach ($cached_data as $key => $info) {
      if (isset($info['data'], $info['fetched_at'])) {
        try {
          $this->data[$key] = $info['data'];
          $this->fetched_at[$key] = new Ndate($info['fetched_at']);
        } catch (UnregisteredLoader $e) {
          // Silence
        }
      }
    }
  }
}

class UnregisteredLoader extends Exception
{
  public function __construct(string $key)
  {
    parent::__construct("Cannot load $key from cache because it is not registered", 500);
  }
}
