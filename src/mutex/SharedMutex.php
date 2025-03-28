<?php

namespace yii\wechat\mutex;

use yii\mutex\Mutex;

abstract class SharedMutex extends Mutex
{
	public const SHARED_LOCK = 0;
	public const EXCLUSIVE_LOCK = 1;
	/**
	 * Acquires a lock by name.
	 * @param string $name of the lock to be acquired. Must be unique.
	 * @param int $timeout time (in seconds) to wait for lock to be released. Defaults to zero meaning that method will return
	 * false immediately in case lock was already acquired.
	 * @return bool lock acquiring result.
	 */
	public function acquire($name, $timeout = 0)
	{
		if (!array_key_exists($name, $this->_locks) && $this->acquireLock($name, $timeout)) {
			$this->_locks[$name] = self::EXCLUSIVE_LOCK;
			return true;
		}
		return false;
	}

	/**
	 * Releases acquired lock. This method will return false in case the lock was not found.
	 * @param string $name of the lock to be released. This lock must already exist.
	 * @return bool lock release result: false in case named lock was not found.
	 */
	public function release($name)
	{
		if ($this->releaseLock($name)) {
			unset($this->_locks[$name]);
			return true;
		}
		return false;
	}
	/**
	 * Checks if a lock is acquired by the current process.
	 * Note that it returns false if the mutex is acquired in another process.
	 *
	 * @param string $name of the lock to check.
	 * @return bool Returns true if currently acquired.
	 */
	public function isAcquired($name)
	{
		return array_key_exists($name, $this->_locks) && $this->_locks[$name] === self::EXCLUSIVE_LOCK;
	}
	/**
	 * Acquires a shared lock by name.
	 * @param string $name of the lock to be acquired. Must be unique.
	 * @param int $timeout time (in seconds) to wait for lock to be released. Defaults to zero meaning that method will return
	 * false immediately in case lock was already acquired.
	 * @return bool lock acquiring result.
	 */
	public function acquireShared($name, $timeout = 0)
	{
		if (!array_key_exists($name, $this->_locks) && $this->acquireSharedLock($name, $timeout)) {
			$this->_locks[$name] = self::SHARED_LOCK;
			return true;
		}
		return false;
	}
	/**
	 * Checks if a shared lock is acquired by the current process.
	 * Note that it returns false if the mutex is acquired in another process.
	 *
	 * @param string $name of the lock to check.
	 * @return bool Returns true if currently acquired.
	 */
	public function isSharedAcquired($name)
	{
		return array_key_exists($name, $this->_locks) && $this->_locks[$name] === self::SHARED_LOCK;
	}
	/**
	 * Upgrades a shared lock to an exclusive lock.
	 * @param string $name of the lock to be upgraded. This lock must already exist.
	 * @param int $timeout time (in seconds) to wait for lock to be released. Defaults to zero meaning that method will return
	 * false immediately in case lock was already acquired.
	 * @return bool lock upgrading result.
	 */
	public function upgradeToExclusive($name, $timeout = 0)
	{
		if ($this->isSharedAcquired($name) && $this->upgradeLock($name, 0)) {
			$this->_locks[$name] = self::EXCLUSIVE_LOCK;
			return true;
		}
		return false;
	}
	/**
	 * Downgrades an exclusive lock to a shared lock.
	 * @param string $name of the lock to be downgraded. This lock must already exist.
	 * @param int $timeout time (in seconds) to wait for lock to be released. Defaults to zero meaning that method will return
	 * false immediately in case lock was already acquired.
	 * @return bool lock downgrading result.
	 */
	public function downgradeToShared($name, $timeout = 0)
	{
		if ($this->isAcquired($name) && $this->downgradeLock($name, 0)) {
			$this->_locks[$name] = self::SHARED_LOCK;
			return true;
		}
		return false;
	}
	/**
	 * This method should be extended by a concrete Mutex implementations. Acquires a shared lock by name.
	 * @param string $name of the lock to be acquired. Must be unique.
	 * @param int $timeout time (in seconds) to wait for lock to be released. Defaults to zero meaning that method will return
	 * false immediately in case lock was already acquired.
	 * @return bool lock acquiring result.
	 */
	abstract protected function acquireSharedLock($name, $timeout = 0);
	/**
	 * This method should be extended by a concrete Mutex implementations. Releases a shared lock by name.
	 * @param string $name of the lock to be released. This lock must already exist.
	 * @return bool lock release result: false in case named lock was not found.
	 */
	abstract protected function releaseSharedLock($name);
	/**
	 * This method should be extended by a concrete Mutex implementations. Upgrades a shared lock to an exclusive lock.
	 * @param string $name of the lock to be upgraded. This lock must already exist.
	 * @param int $timeout time (in seconds) to wait for lock to be released. Defaults to zero meaning that method will return
	 * false immediately in case lock was already acquired.
	 * @return bool lock upgrading result.
	 */
	abstract protected function upgradeLock($name, $timeout = 0);
	/**
	 * This method should be extended by a concrete Mutex implementations. Downgrades an exclusive lock to a shared lock.
	 * @param string $name of the lock to be downgraded. This lock must already exist.
	 * @param int $timeout time (in seconds) to wait for lock to be released. Defaults to zero meaning that method will return
	 * false immediately in case lock was already acquired.
	 * @return bool lock downgrading result.
	 */
	abstract protected function downgradeLock($name, $timeout = 0);
}
