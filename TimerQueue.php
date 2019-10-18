<?php
/**
 *定时异步队列
 * Created by PhpStorm.
 * User: pc
 * Date: 2019/10/12
 * Time: 11:51
 */
require_once './RedisPool.php';
class TimerQueue
{
    public $chan = null;
    public $pool = null;

    /**
     * 生产者
     * $key list的key   domain:port:intval_time  key='www.baidu.com:80:1' www.baidu.com 域名  80 端口  1表示1s,  0.5s 表示0.5秒
     * $params   string|array  多个任务为数组 ,单个任务为字符串
     * $params = [
     *    '/index.php?id=1',
     *    '/index.php?id=2',
     *    '/index.php?id=3',
     *    '/index.php?id=4',
     * ]
     * 或者
     * $params = '/index.php?id=1',
     */
    public function product($key,$params)
    {
        go(function() use($key,$params){
            $number =0;
            $redis = RedisPool::getIntance()->pop();
            if(empty($params)){
                $this->error= '请传队列的值';
                return false;
            }else if(is_array($params)){
                foreach($params as $k=>$v){
                    echo $url = $redis->lpush($key,$v);
                    $number++;
                }
            }else if(is_string($params)){
                echo $url = $url = $redis->lpush($key,$params);;
                $number=1;
            }
            RedisPool::getIntance()->push($redis);
            return $number;
        });
    }

    /**
     * 消费者
     * redis list key='www.baidu.com:80:1' www.baidu.com 域名  80 端口  1表示1秒,  0.5 表示0.5秒
     * redis list value=
     */
    public function consumption($key)
    {
        list($domain,$port, $intavl) = explode(':', $key);
        \Swoole\Timer::tick($intavl * 1000, function () use ($key,$domain,$port) {
            go(function () use ($key,$domain,$port) {
                $redis = RedisPool::getIntance()->pop();
                $paramas = $redis->rpop($key);
                $cli = new Swoole\Coroutine\Http\Client($domain, $port);
                $cli->set(['timeout' => 0,'keep_alive' => false]);
                $cli->setHeaders([
                    'Host' => $domain,
                    "User-Agent" => 'Chrome/49.0.2587.3',
                ]);
                $cli->get($paramas);
                echo "\r\n";

                echo $cli->body;
                echo "\r\n";
                $taskTimes = $redis->llen($key);
                echo $cli->statusCode;
                echo "\r\n";
                echo $key.'剩余任务:'.$taskTimes;
                if($taskTimes <1){
                    exit('执行完毕....');
                }
                echo "\r\n";
                echo date('Y-d-m H:i:s');
                $cli->close();
                RedisPool::getIntance()->push($redis);
            });
        });
    }
}

// 例子
$model = new TimerQueue();
$prams= [];
for ($i=1;$i<=200000;$i++){
    $prams[] = '/index.html?id='.$i;
}
$model->product('www.baidu.com:80:1',$prams);
$model->consumption('www.baidu.com:80:1');
