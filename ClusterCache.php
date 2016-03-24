<?php

namespace heyanlong\redis;

/**
 * Redis Cluster Cache implementation.
 * For clustered MGET/MSET usage.
 *
 * @author arkham.vm <arkham.vm@gmail.com>
 */
class ClusterCache extends \yii\redis\Cache
{
	/**
	 * Get a hash group string identifier for specified key.
	 *
	 * @param string[]|string $key
	 * @return string
	 */
	protected static function getHashGroup($key) {
		$key = is_array($key) ? $key['key'] : $key;
		// We need to keep key if it doesn't have the hash prefix
		$group = $key;

		if (1 === preg_match('~^\{(?P<group>.+)\}~Uu', $key, $preg)) {
			$group = $preg['group'];
		}

		/**
		 * Prefix needed because some hash keys may contains only numbers.
		 * Thus, such hash keys can rewrite plain keys.
		 */
		return 'gr:' . $group;
	}

    /**
	 * @inheritdoc
	 *
	 * Multi keys operations in Redis Cluster only possible while all query hash keys stored in one cluster node.
	 * Thus we need to divide query to several queries by hash key and run them separately.
	 */
	protected function getValues($keys)
    {
        $result = [];

		// Divide keys to groups by hash prefix.
		$commands = [];
		foreach ($keys as $key) {
			$commands[static::getHashGroup($key)][] = $key;
		}

		// Execute commands
		foreach ($commands as $commandKeys) {
			$commandKeys = is_array($commandKeys) ? $commandKeys : [$commandKeys];
			$response = $this->redis->executeCommand('MGET', $commandKeys);

			$i = 0;
			foreach ($commandKeys as $commandkey) {
				$result[$commandkey] = $response[$i++];
			}
		}

        return $result;
    }

    /**
	 * @inheritdoc
	 *
	 * Multi keys operations in Redis Cluster only possible while all query hash keys stored in one cluster node.
	 * Thus we need to divide query to several queries by hash key and run them separately.
	 */
    protected function setValues($data, $expire)
    {
		$expire = (int) ($expire * 1000);

        $tempData = [];
        foreach ($data as $key => $value) {
            $tempItem['key']	 = $key;
			$tempItem['value']	 = $value;
			$tempData[]			 = $tempItem;
		}
		unset($data, $tempItem);

		// Divide keys to groups by hash prefix.
		$commands = [];
		foreach ($tempData as $tempKey) {
			$commands[static::getHashGroup($tempKey)][] = $tempKey;
		}
		// Execute commands
		$failedKeys = [];
		foreach ($commands as $commandKeys) {
			$args = [];
			foreach ($commandKeys as $commandKey) {
				$args[] = $commandKey['key'];
				$args[] = $commandKey['value'];
			}

			if ($expire == 0) {
				$this->redis->executeCommand('MSET', $args);
			} else {
				// Cluster doesn't support transactions - there is no "MULTI"
				$this->redis->executeCommand('MSET', $args);
				foreach ($commandKeys as $expireKey) {
					if ('1' !== $this->redis->executeCommand('PEXPIRE', [$expireKey['key'], $expire])) {
						$failedKeys[] = $expireKey['key'];
					}
				}
			}

			unset($args);
		}

        return $failedKeys;
    }

    /**
     * @inheritdoc
	 *
	 * FLUSHDB and FLUSHALL command works only for specified connected cluster node.
	 * Thus, we need to execute it on each node.
     */
    protected function flushValues()
    {
		foreach ($this->redis->master as $node) {
			$this->redis->open($node);
			if (true !== $this->redis->executeCommand('FLUSHALL')) {
				throw new Exception('Can\'t flush values in node: ' . $node);
			}
			$this->redis->close();
		}

		return true;
    }
}
