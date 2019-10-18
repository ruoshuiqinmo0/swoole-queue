<?php

class RedisPool
{
    /**
     * @var \Swoole\Coroutine\Channel
     */
    protected static $pool = null;
    public $redisHost = '127.0.0.1';
    public $redisPort = 6379;

    /**
     *
     * RedisPool constructor.
     * @param $redisHost
     * @param $redisPort
     * @param int $size 连接池的尺寸
     */
    protected function __construct($redisHost, $redisPort, $size)
    {
        if (null != $redisHost) {
            $this->redisHost = $redisHost;
        }
        if (null != $redisPort) {
            $this->redisPort = $redisPort;
        }
        self::$pool = new Swoole\Coroutine\Channel($size);
        for ($i = 0; $i < $size; $i++) {
            go(function(){
                $redis = new Swoole\Coroutine\Redis();
                $res = $redis->connect($this->redisHost, $this->redisPort);
                if ($res == false) {
                    throw new RuntimeException("failed to connect redis server.");
                } else {
                    self::$pool->push($redis);
                }
            });
        }
    }

    /**
     * 实例化 并返回一个redis链接
     * @param string $redisHost
     * @param int $redisPort
     * @param int $size
     * @return mixed
     */
    public static function getIntance($redisHost = '127.0.0.1', $redisPort = 6379, $size = 10)
    {
        if (null == self::$pool) {
            new static($redisHost, $redisPort, $size);
        }
        return self::$pool;
    }
}