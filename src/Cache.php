<?php

	namespace Traineratwot\Cache;

	use FilesystemIterator;
	use RecursiveDirectoryIterator;
	use RecursiveIteratorIterator;
	use RuntimeException;
	use SplFileInfo;
	use Traineratwot\cc\Config;

	/**
	 * Класс для Кеша
	 * [github.com](https://github.com/Traineratwot/cache)
	 */
	class Cache
	{
		/**
		 * Stores the result of executing the callback function in the cache or returns the already cached value
		 *
		 * @param mixed    $key
		 * @param Callback $function Callback
		 * @param int      $expire   Cache lifetime in sec
		 * @param string   $category cache folder eg: category/subcategory
		 * @param mixed    ...$args  Values passed to the Callback function
		 * @return mixed|null
		 * @throws CacheException
		 */
		public static function call($key, $function, $expire = 600, $category = '', ...$args)
		{
			$result = self::getCache($key, $category);
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
		 * @param mixed  $key      Cache key
		 * @param string $category cache folder eg: category/subcategory
		 * @return mixed|null value
		 */
		public static function getCache($key, $category = '')
		{
			//если установлен заголовок отключить кеш отключаем кеш
			if (($category !== 'table') && function_exists('getallheaders')) {
				$headers = getallheaders();
				if ($headers['Cache-Control'] === 'no-cache') {
					return NULL;
				}
			}
			$name = self::getKey($key) . '.cache.php';
			if (file_exists(Config::get('CACHE_PATH') . $category . DIRECTORY_SEPARATOR . $name)) {
				return include Config::get('CACHE_PATH') . $category . DIRECTORY_SEPARATOR . $name;
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
		 * @throws CacheException
		 */
		public static function setCache($key, $value, $expire = 600, $category = '')
		{
			$name                = self::getKey($key) . '.cache.php';
			$v                   = var_export($value, 1);
			$expire              = $expire ? $expire + time() : 0;
			$body                = <<<PHP
<?php
	if($expire && time()>$expire){unlink(__FILE__);return null;}
	return $v;
?>
PHP;
			$concurrentDirectory = Config::get('CACHE_PATH') . $category . DIRECTORY_SEPARATOR;
			if (!file_exists($concurrentDirectory) || !is_dir($concurrentDirectory)) {
				if (!mkdir($concurrentDirectory, 0777, TRUE) && !is_dir($concurrentDirectory)) {
					throw new CacheException(sprintf('Directory "%s" was not created', $concurrentDirectory));
				}
			}
			if (is_dir($concurrentDirectory)) {
				file_put_contents($concurrentDirectory . $name, $body);
			}
			return $value;
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
			if (file_exists(Config::get('CACHE_PATH') . $category . DIRECTORY_SEPARATOR . $name)) {
				unlink(Config::get('CACHE_PATH') . $category . DIRECTORY_SEPARATOR . $name);
			}
			return !file_exists(Config::get('CACHE_PATH') . $name);
		}

		/**
		 * Deletes an outdated cache
		 * @return void
		 */
		public static function autoRemove()
		{
			$dirs     = new RecursiveDirectoryIterator(Config::get('CACHE_PATH'), FilesystemIterator::SKIP_DOTS);
			$Iterator = new RecursiveIteratorIterator($dirs);
			/** @var SplFileInfo $file */
			foreach ($Iterator as $file) {
				if (strpos($file->getFilename(), '.cache.php') !== FALSE) {
					include $file->getPathname();
				}
			}
		}

		/**
		 * Deletes all cache
		 * @param string $dir DON`T SET
		 * @return void
		 */
		public static function removeAll($dir = -1)
		{
			if ($dir < 0) {
				$dir = (string)Config::get('CACHE_PATH');
			}
			if ($dir && file_exists($dir)) {
				if (strpos($dir, Config::get('CACHE_PATH')) === FALSE) {
					throw new RuntimeException();
				}
				if ($objs = glob($dir . '/*')) {
					foreach ($objs as $obj) {
						is_dir($obj) ? self::removeAll($obj) : unlink($obj);
					}
				}
				rmdir($dir);
			}
		}
	}