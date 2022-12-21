<?php

	namespace Traineratwot\Cache;

	use DateInterval;
	use DateTime;
	use Exception;
	use FilesystemIterator;
	use Psr\SimpleCache\CacheInterface;
	use RecursiveDirectoryIterator;
	use RecursiveIteratorIterator;
	use RuntimeException;
	use SplFileInfo;
	use Traineratwot\config\Config;


	/**
	 * Класс для Кеша
	 * [github.com]( https://github.com/Traineratwot/cache )
	 */
	class Cache implements CacheInterface
	{
		/**
		 * Stores the result of executing the callback function in the cache or returns the already cached value
		 *
		 * @param mixed    $key
		 * @param Callback $function            Callback
		 * @param int      $expire              Cache lifetime in sec
		 * @param string   $category            cache folder eg: category/subcategory
		 * @param bool     $BrowserCacheControl cache folder eg: category/subcategory
		 * @param mixed    ...$args             Values passed to the Callback function
		 * @return mixed|null
		 * @noinspection PhpDocMissingThrowsInspection
		 * @noinspection PhpUnhandledExceptionInspection
		 */
		public static function call($key, $function, $expire = 600, $category = '', $BrowserCacheControl = TRUE, ...$args)
		{
			$result = self::getCache($key, $category, $BrowserCacheControl);
			if ($result !== NULL) {
				return $result;
			}
			if (is_callable($function)) {
				$args   = func_get_args();
				$args   = array_slice($args, 4);
				$result = $function(...$args);
				if ($result !== NULL) {
					return self::setCache($key, $result, $expire, $category);
				}
				return NULL;
			}
			throw new CacheException("Is not a function");
		}

		/**
		 * Returns a value from the cache
		 *
		 * @param mixed   $key                 Cache key
		 * @param string  $category            cache folder eg: category/subcategory
		 * @param boolean $BrowserCacheControl if false ignore browser cache control
		 * @return mixed|null value
		 */
		public static function getCache($key, $category = '', $BrowserCacheControl = TRUE)
		{
			//если установлен заголовок отключить кеш отключаем кеш
			if ($BrowserCacheControl && function_exists('getallheaders')) {
				$headers = getallheaders();
				if (isset($headers['Cache-Control']) && $headers['Cache-Control'] === 'no-cache') {
					return NULL;
				}
			}
			$name = self::getKey($key) . '.cache.php';
			if (file_exists(Config::get('CACHE_PATH', $category) . $category . DIRECTORY_SEPARATOR . $name)) {
				return include Config::get('CACHE_PATH', $category) . $category . DIRECTORY_SEPARATOR . $name;
			}
			return NULL;
		}

		/**
		 * Turn the cache key into a string
		 * @param mixed $a
		 * @return string
		 */
		public static function getKey($a)
		{
			if (is_string($a) && strlen($a) < 32 && preg_match('@\w{1,32}@', $a)) {
				return $a;
			}
			return md5(serialize($a));
		}

		/**
		 * Saves the value to the cache
		 * @param mixed  $key      Cache key
		 * @param mixed  $value    value
		 * @param int    $expire   Cache lifetime in sec
		 * @param string $category cache folder eg: category/subcategory
		 * @return mixed
		 * @noinspection PhpDocMissingThrowsInspection
		 * @noinspection PhpUnhandledExceptionInspection
		 */
		public static function setCache($key, $value, DateInterval|int|null $expire = 600, $category = '')
		: bool
		{
			$name     = self::getKey($key) . '.cache.php';
			$v        = var_export($value, 1);
			$lifetime = new DateTime();

			if ($expire) {
				if (is_int($expire)) {
					$lifetime->modify("+ $expire second");
				} else {
					$lifetime->add($expire);
				}
			}
			$expire              = $lifetime->getTimestamp();
			$body                = <<<PHP
<?php
	if($expire && time()>$expire){unlink(__FILE__);return null;}
	return $v;
?>
PHP;
			$concurrentDirectory = Config::get('CACHE_PATH', $category) . $category . DIRECTORY_SEPARATOR;
			self::chmod(dirname($concurrentDirectory), 0777);
			if (!file_exists($concurrentDirectory) || !is_dir($concurrentDirectory)) {
				if (!mkdir($concurrentDirectory, 0777, TRUE) && !is_dir($concurrentDirectory)) {
					throw new CacheException(sprintf('Directory "%s" was not created', $concurrentDirectory));
				}
			}
			if (is_dir($concurrentDirectory)) {
				self::chmod($concurrentDirectory, 0777);
				file_put_contents($concurrentDirectory . $name, $body);
				self::chmod($concurrentDirectory, 0777);
			}
			return TRUE;
		}

		/**
		 * Deletes the cache file
		 * @param mixed  $key      key
		 * @param string $category cache folder eg: category/subcategory
		 * @return bool
		 */
		public static function removeCache($key, $category = '')
		{
			$name = self::getKey($key) . '.cache.php';
			if (file_exists(Config::get('CACHE_PATH', $category) . $category . DIRECTORY_SEPARATOR . $name)) {
				self::chmod(Config::get('CACHE_PATH', $category) . $category . DIRECTORY_SEPARATOR, 0777);
				self::chmod(Config::get('CACHE_PATH', $category) . $category . DIRECTORY_SEPARATOR . $name, 0777);
				unlink(Config::get('CACHE_PATH', $category) . $category . DIRECTORY_SEPARATOR . $name);
			}
			return !file_exists(Config::get('CACHE_PATH', $category) . $name);
		}

		private static function chmod($filename, $permissions)
		{
			try {
				return chmod($filename, $permissions);
			} catch (Exception $e) {
				return FALSE;
			}
		}

		/**
		 * Deletes an outdated cache
		 * @return void
		 */
		public static function autoRemove($category = '')
		{
			$dirs     = new RecursiveDirectoryIterator(Config::get('CACHE_PATH', $category), FilesystemIterator::SKIP_DOTS);
			$Iterator = new RecursiveIteratorIterator($dirs);
			/** @var SplFileInfo $file */
			foreach ($Iterator as $file) {
				try {
					if (file_exists($file->getFilename()) && strpos($file->getFilename(), '.cache.php') !== FALSE && self::chmod($file->getFilename(), 0777)) {
						include $file->getPathname();
					}
				} catch (Exception $e) {

				}
			}
		}

		/**
		 * Deletes all cache
		 * @param string $category
		 * @param string $dir DON`T SET
		 * @return void
		 */
		public static function removeAll($category = NULL, $dir = -1)
		{
			if ($dir < 0) {
				$dir = (string)Config::get('CACHE_PATH', $category);
			}
			if ($dir && file_exists($dir)) {
				if (strpos($dir, Config::get('CACHE_PATH', $category)) === FALSE) {
					throw new RuntimeException();
				}
				if ($objs = glob($dir . '/*')) {
					foreach ($objs as $obj) {
						self::chmod($obj, 0777);
						is_dir($obj) ? self::removeAll($category, $obj) : unlink($obj);
					}
				}
				rmdir($dir);
			}
		}

		public function clear()
		: bool
		{
			self::autoRemove();
			return TRUE;
		}

		public function get(string $key, mixed $default = NULL)
		: mixed
		{
			$category = '';
			if (strpos($key, '/') !== FALSE) {
				$category = dirname($key);
				$key      = basename($key);
			}
			return self::getCache($key, $category) ?: $default;
		}

		public function getMultiple(iterable $keys, mixed $default = NULL)
		: iterable
		{
			$result = [];
			foreach ($keys as $key) {
				$result[] = $this->get($key, $default);
			}
			return $result;
		}

		/**
		 * @throws CacheException
		 */
		public function set(string $key, mixed $value, DateInterval|int|null $ttl = NULL)
		: bool
		{
			$category = '';
			if (strpos($key, '/') !== FALSE) {
				$category = dirname($key);
				$key      = basename($key);
			}
			return self::setCache($key, $category, $ttl);
		}

		/**
		 * @throws CacheException
		 */
		public function setMultiple(iterable $values, DateInterval|int|null $ttl = NULL)
		: bool
		{
			foreach ($values as $key => $value) {
				if (!$this->set($key, $value)) {
					return FALSE;
				}
			}
			return TRUE;
		}

		public function delete(string $key)
		: bool
		{
			$category = '';
			if (strpos($key, '/') !== FALSE) {
				$category = dirname($key);
				$key      = basename($key);
			}
			self::removeCache($key);
			return TRUE;
		}

		public function deleteMultiple(iterable $keys)
		: bool
		{
			foreach ($keys as $key) {
				$this->delete($key);
			}
			return TRUE;
		}

		public function has(string $key)
		: bool
		{
			$name = self::getKey($key) . '.cache.php';
			return file_exists(Config::get('CACHE_PATH', $category) . $category . DIRECTORY_SEPARATOR . $name);
		}

		public static function getCacheFile($key, $category = '')
		{
			$name = self::getKey($key) . '.cache.php';
			if (file_exists(Config::get('CACHE_PATH', $category) . $category . DIRECTORY_SEPARATOR . $name)) {
				return Config::get('CACHE_PATH', $category) . $category . DIRECTORY_SEPARATOR . $name;
			}
			return NULL;
		}
	}