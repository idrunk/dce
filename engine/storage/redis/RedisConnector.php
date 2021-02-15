<?php
/**
 * Author: Drunk (drunkce.com;idrunk.net)
 * Date: 2021-02-12 18:13
 */

namespace dce\storage\redis;

use ArrayAccess;
use dce\pool\PoolException;
use Redis;

class RedisConnector {
    private Redis $redis;

    public function __construct(array|ArrayAccess $config, bool $persistent = true) {
        $this->redis = new Redis();
        if ($config['password'] ?? false) {
            $this->redis->auth($config['password']);
        }
        if (
            $persistent
            ? ! $this->redis->pconnect($config['host'], $config['port'], 1)
            : ! $this->redis->connect($config['host'], $config['port'], 1)
        ) {
            throw new PoolException('生成连接实例失败, 无法连接到Redis服务');
        }
        if (isset($config['index'])) {
            $this->redis->select((int) $config['index']);
        }
        $this->redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_PHP);
    }

    public function getRedis(): Redis {
        return $this->redis;
    }
}