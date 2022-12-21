<?php

	namespace Traineratwot\tests\Cache;

	use PHPUnit\Framework\TestCase;
	use Traineratwot\Cache\Cache;
	use Traineratwot\config\Config;

	class CacheTest extends TestCase
	{
		protected function setUp()
		: void
		{
			Config::set("CACHE_PATH", __DIR__ . '/cache');
		}

		public function testSetCache()
		{
			$a = Cache::setCache('test', 'testValue', 5);
			$this->assertTrue($a);
		}

		/**
		 * @depends testSetCache
		 */
		public function testGetCache()
		{
			sleep(1);
			$a = Cache::getCache('test');
			echo Cache::getCacheFile('test');
			$this->assertEquals('testValue', $a);
		}

		/**
		 * @depends testGetCache
		 */
		public function testGetCacheExpire()
		{
			sleep(5);
			$a = Cache::getCache('test');
			$this->assertEquals(NULL, $a);
		}
		public function testDeleteCache()
		{
			$a = Cache::setCache('remove', 'testValue', 5);
			Cache::removeCache('remove');
			$a = Cache::getCache('test');
			$this->assertEquals(NULL, $a);
		}
	}
