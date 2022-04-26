# Cache

define const `WT_CACHE_PATH` for set cache folder. without `WT_CACHE_PATH` save to vendor directory

**install**

```
composer require traineratwot/cache
```

Example:

```php
$key = ['key'];
$value = ['value'];

\Traineratwot\Cache\Cache::setCache($key,$value,600,'category/subcategory')

\Traineratwot\Cache\Cache::getCache($key,$value,600,'category/subcategory')

\Traineratwot\Cache\Cache::call($key,function($v) use ($key){
if(count($key) === 1){
	return $v
	}
	return 'noValue'
},600,'category/subcategory',$value)

\Traineratwot\Cache\Cache::autoRemove()

\Traineratwot\Cache\Cache::removeAll()

```