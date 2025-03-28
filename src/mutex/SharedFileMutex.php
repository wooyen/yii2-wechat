<?php

namespace yii\wechat\mutex;

use Yii;
use yii\base\InvalidConfigException;
use yii\helpers\FileHelper;
use yii\mutex\RetryAcquireTrait;

class SharedFileMutex extends SharedMutex
{
	use RetryAcquireTrait;
	/**
	 * @var string the directory to store mutex files. You may use [path alias](guide:concept-aliases) here.
	 * Defaults to the "mutex" subdirectory under the application runtime path.
	 */
	public $mutexPath = '@runtime/mutex';
	/**
	 * @var int|null the permission to be set for newly created mutex files.
	 * This value will be used by PHP chmod() function. No umask will be applied.
	 */
	public $fileMode;
	/**
	 * @var int the permission to be set for newly created directories.
	 * This value will be used by PHP chmod() function. No umask will be applied.
	 * Defaults to 0775, meaning the directory is read-writable by owner and group,
	 * but read-only for other users.
	 */
	public $dirMode = 0775;
	/**
	 * @var bool|null whether file handling should assume a Windows file system.
	 * This value will determine how [[releaseLock()]] goes about deleting the lock file.
	 * If not set, it will be determined by checking the DIRECTORY_SEPARATOR constant.
	 * @since 2.0.16
	 */
	public $isWindows;
	/**
	 * @var resource[] stores all opened lock files. Keys are lock names and values are file handles.
	 */
	private $_files = [];
	/**
	 * Initializes mutex component implementation dedicated for UNIX, GNU/Linux, Mac OS X, and other UNIX-like
	 * operating systems.
	 * @throws InvalidConfigException
	 */
	public function init()
	{
		parent::init();
		$this->mutexPath = Yii::getAlias($this->mutexPath);
		if (!is_dir($this->mutexPath)) {
			FileHelper::createDirectory($this->mutexPath, $this->dirMode, true);
		}
		if ($this->isWindows === null) {
			$this->isWindows = DIRECTORY_SEPARATOR === '\\';
		}
	}
	/**
	 * Acquires specified type of lock by given name.
	 * @param string $name of the lock to be acquired.
	 * @param int $type the type of lock to be acquired.
	 * @param int $timeout time (in seconds) to wait for lock to become released.
	 * @return bool acquiring result.
	 */
	protected function _acquireLock($name, $type, $timeout = 0)
	{
		$filePath = $this->getLockFilePath($name);
		return $this->retryAcquire($timeout, function () use ($filePath, $name, $type) {
			$file = fopen($filePath, 'w+');
			if ($file === false) {
				return false;
			}
			if ($this->fileMode !== null) {
				@chmod($filePath, $this->fileMode);
			}
			if (!flock($file, $type | LOCK_NB)) {
				fclose($file);
				return false;
			}
			if (DIRECTORY_SEPARATOR !== '\\' && fstat($file)['ino'] !== @fileinode($filePath)) {
				clearstatcache(true, $filePath);
				flock($file, LOCK_UN);
				fclose($file);
				return false;
			}
			$this->_files[$name] = $file;
			return true;
		});
	}
	/**
	 * Releases lock by given name.
	 * @param string $name of the lock to be released.
	 * @return bool releasing result.
	 */
	protected function _releaseLock($name, $type)
	{
		if (!isset($this->_files[$name])) {
			return false;
		}
		if ($this->_files[$name] !== $type) {
			return false;
		}
		if ($this->isWindows) {
			flock($this->_files[$name], LOCK_UN);
			fclose($this->_files[$name]);
			if ($type === LOCK_EX) {
				@unlink($this->getLockFilePath($name));
			}
		} else {
			if ($type === LOCK_EX) {
				unlink($this->getLockFilePath($name));
			}
			flock($this->_files[$name], LOCK_UN);
			fclose($this->_files[$name]);
		}
		unset($this->_files[$name]);
		return true;
	}
	/**
	 * Acquires lock by given name.
	 * @param string $name of the lock to be acquired.
	 * @param int $timeout time (in seconds) to wait for lock to become released.
	 * @return bool acquiring result.
	 */
	protected function acquireLock($name, $timeout = 0)
	{
		return $this->_acquireLock($name, LOCK_EX, $timeout);
	}
	/**
	 * Releases lock by given name.
	 * @param string $name of the lock to be released.
	 * @return bool releasing result.
	 */
	protected function releaseLock($name)
	{
		return $this->_releaseLock($name, LOCK_EX);
	}
	/**
	 * Acquires shared lock by given name.
	 * @param string $name of the lock to be acquired.
	 * @param int $timeout time (in seconds) to wait for lock to become released.
	 * @return bool acquiring result.
	 */
	protected function acquireSharedLock($name, $timeout = 0)
	{
		return $this->_acquireLock($name, LOCK_SH, $timeout);
	}
	/**
	 * Releases shared lock by given name.
	 * @param string $name of the lock to be released.
	 * @return bool releasing result.
	 */
	protected function releaseSharedLock($name)
	{
		return $this->_releaseLock($name, LOCK_SH);
	}
	/**
	 * Upgrades a shared lock to an exclusive lock.
	 * @param string $name of the lock to be upgraded.
	 * @param int $timeout time (in seconds) to wait for lock to become released.
	 * @return bool upgrading result.
	 */
	protected function upgradeLock($name, $timeout = 0)
	{
		throw new UnsupportedException('Cannot upgrade lock in file mutex.');
	}
	/**
	 * Downgrades an exclusive lock to a shared lock.
	 * @param string $name of the lock to be downgraded.
	 * @param int $timeout time (in seconds) to wait for lock to become released.
	 * @return bool downgrading result.
	 */
	protected function downgradeLock($name, $timeout = 0)
	{
		throw new UnsupportedException('Cannot downgrade lock in file mutex.');
	}
	/**
	 * Generates path for lock file.
	 * @param string $name of the lock.
	 * @return string path to the lock file.
	 */
	protected function getLockFilePath($name)
	{
		return $this->mutexPath . DIRECTORY_SEPARATOR . md5($name) . '.lock';
	}
}
