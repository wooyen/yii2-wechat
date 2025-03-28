<?php

namespace yii\wechat\mutex;

use Closure;
use Yii;
use yii\base\InvalidConfigException;
use yii\di\Instance;
use yii\mutex\RetryAcquireTrait;
use yii\redis\Connection;

class SharedRedisMutex extends SharedMutex
{
	private const PRE_LOCK_SCRIPT = <<<LUA
if redis.call("EXISTS", KEYS[1], KEYS[2]) == 0 then
    return redis.call("SET", KEYS[1], ARGV[1], "NX", "PX", ARGV[2])
else
    return 0
end
LUA;
	private const UPGRADE_LOCK_SCRIPT = <<<LUA
redis.call("PEXPIRE", KEYS[2], ARGV[1])
local t = redis.call("TIME")
t = t[1]*1000 + t[2] / 1000 - ARGV[1]
local count = redis.call("ZCARD", KEYS[1])
redis.call("ZREMRANGEBYSCORE", KEYS[1], 0, t)
if count > 0 then
	redis.call("PEXPIRE", KEYS[1], ARGV[1])
end
if count > 1 then
	return 0
end
local val = redis.call("GET", KEYS[2])
if count == 0 or redis.call("ZRANK", KEYS[1], val) == 0 then
	local res = redis.call("SET", KEYS[3], val, "NX", "PX", ARGV[1])
	if res == 1 then
		redis.call("DEL", KEYS[1], KEYS[2])
	end
	return res
else
	return 0
end
LUA;
	private const SHARED_LOCK_SCRIPT = <<<LUA
local t = redis.call("TIME")
t = t[1]*1000 + t[2] / 1000
if redis.call("EXISTS", KEYS[2], KEYS[3]) == 0 then
	local res = redis.call("ZADD", KEYS[1], t, ARGV[1])
	redis.call("PEXPIRE", KEYS[1], ARGV[2])
	return res
else
    return 0
end
LUA;
	private const DOWNGRADE_LOCK_SCRIPT = <<<LUA
local t = redis.call("TIME")
t = t[1]*1000 + t[2] / 1000
local val = redis.call("GET", KEYS[2])
if val == nil then
	return 0
end
local res = redis.call("ZADD", KEYS[1], t, val)
if res == 1 then
	redis.call("PEXPIRE", KEYS[1], ARGV[1])
end
return res
LUA;
	/**
	 * @var int the number of seconds in which the lock will be auto released.
	 */
	public $expire = 30;

	/**
	 * @var string a string prefixed to every cache key so that it is unique. If not set,
	 * it will use a prefix generated from [[Application::id]]. You may set this property to be an empty string
	 * if you don't want to use key prefix. It is recommended that you explicitly set this property to some
	 * static value if the cached data needs to be shared among multiple applications.
	 */
	public $keyPrefix;

	/**
	 * @var Connection|string|array the Redis [[Connection]] object or the application component ID of the Redis [[Connection]].
	 */
	public $redis = 'redis';

	/**
	 * @var array Redis lock values. Used to be safe that only a lock owner can release it.
	 */
	private $_lockValues = [];

	/**
	 * Initializes the redis Mutex component.
	 * This method will initialize the [[redis]] property to make sure it refers to a valid redis connection.
	 * @throws InvalidConfigException if [[redis]] is invalid.
	 */
	public function init()
	{
		parent::init();
		$this->redis = Instance::ensure($this->redis, Connection::class);
		if ($this->keyPrefix === null) {
			$this->keyPrefix = substr(md5(Yii::$app->id), 0, 5);
		}
	}

	/**
	 * Generates a unique key used for storing the mutex in Redis.
	 * @param string $name mutex name.
	 * @return string a safe cache key associated with the mutex name.
	 */
	protected function calculateKey($name): string
	{
		return $this->keyPrefix . md5(json_encode([__CLASS__, $name]));
	}
	protected function retryAcquire(float $deadline, Closure $callback, Closure $expect = null): bool
	{
		if ($expect === null) {
			$expect = fn($res) => $res;
		}
		do {
			$res = $callback();
			if ($expect($res)) {
				return $res;
			}
			usleep($this->retryDelay * 1000);
		} while (microtime(true) < $deadline);
		return false;
	}
	public function acquireLock($name, $timeout = 0)
	{
		$key = $this->calculateKey($name);
		$value = uniqid('', true);
		$deadline = microtime(true) + $timeout;
		$script = $this->redis->scriptLoad(self::PRE_LOCK_SCRIPT);
		$res = $this->retryAcquire($deadline, function () use ($key, $value, $script) {
			return $this->redis->evalsha($script, 2, "PR:$key", "EX:$key", $value, (int) ($this->expire * 1000));
		});
		if (!$res) {
			return false;
		}
		$script = $this->redis->scriptLoad(self::UPGRADE_LOCK_SCRIPT);
		$res = $this->retryAcquire($deadline, function () use ($key, $script) {
			return $this->redis->evalsha($script, 3, "SH:$key", "PR:$key", "EX:$key", (int) ($this->expire * 1000));
		});
		if (!$res) {
			$this->redis->del("PR:$key");
			return false;
		}
		$this->_lockValues[$key] = $value;
		return true;
	}

	public function releaseLock($name)
	{
		$key = $this->calculateKey($name);
		unset($this->_lockValues[$key]);
		return $this->redis->del("EX:$key") > 0;
	}

	public function acquireSharedLock($name, $timeout = 0)
	{
		$key = $this->calculateKey($name);
		$value = uniqid('', true);
		$deadline = microtime(true) + $timeout;
		$script = $this->redis->scriptLoad(self::SHARED_LOCK_SCRIPT);
		$res = $this->retryAcquire($deadline, function () use ($key, $value, $script) {
			return $this->redis->evalsha($script, 3, "SH:$key", "PR:$key", "EX:$key", $value, (int) ($this->expire * 1000));
		});
		if ($res) {
			$this->_lockValues[$key] = $value;
		}
		return $res;
	}

	public function releaseSharedLock($name)
	{
		$key = $this->calculateKey($name);
		$this->redis->zrem("SH:$key", $this->_lockValues[$key]);
		unset($this->_lockValues[$key]);
		return true;
	}

	public function upgradeLock($name, $timeout = 0)
	{
		return $this->acquireLock($name, $timeout);
	}

	public function downgradeLock($name, $timeout = 0)
	{
		$key = $this->calculateKey($name);
		$script = $this->redis->scriptLoad(self::DOWNGRADE_LOCK_SCRIPT);
		$deadline = microtime(true) + $timeout;
		return $this->retryAcquire($deadline, function () use ($key, $script) {
			return $this->redis->evalsha($script, 2, "SH:$key", "EX:$key", (int) ($this->expire * 1000));
		});
	}
}