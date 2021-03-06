<?php
/**
 * Author: Drunk
 * Date: 2019-2-7 4:40
 */

namespace dce\pool;

use drunk\Utility;

abstract class Pool {
    private static string $channelClass = CoroutineChannel::class;

    private static array $instMapping = [];

    /** @var ChannelAbstract[] */
    private array $channelMapping = [];

    private PoolProduct $productMapping;

    /** @var PoolProductionConfig[] */
    private array $configs = [];

    private string $configClass;

    private function __construct(string $configClass) {
        if (! is_subclass_of($configClass, PoolProductionConfig::class)) {
            throw new PoolException(PoolException::INVALID_CONFIG_CLASS);
        }
        if (! is_subclass_of(self::$channelClass, ChannelAbstract::class)) {
            throw new PoolException(PoolException::INVALID_CHANNEL);
        }
        $this->productMapping = PoolProduct::new();
        $this->configClass = $configClass;
    }

    /**
     * 设置实例池类
     * @param string $channelClass
     */
    public static function setChannelClass(string $channelClass) {
        if (is_subclass_of($channelClass, ChannelAbstract::class)) {
            self::$channelClass = $channelClass;
        }
    }

    /**
     * 取实例
     * @param string $configClass
     * @param string ...$identities
     * @return static
     * @throws null
     */
    final protected static function getInstance(string $configClass, string ... $identities): static {
        array_push($identities, $configClass);
        $identity = implode('-', $identities);
        if (! key_exists($identity, self::$instMapping)) {
            self::$instMapping[$identity] = new static($configClass);
        }
        return self::$instMapping[$identity];
    }

    /**
     * 设置配置 (会以新的配置替换老的, 若新的在老的中存在, 则会复用, 若不存在则会新建, 若老的在新的中不存在, 则会移除)
     * @param array $configs
     * @param bool $forceReplace
     * @return static
     */
    public function setConfigs(array $configs, bool $forceReplace = false): static {
        // mark 若后续做热更新, 则也需同时处理InstanceMapping的记录
        if (! $forceReplace && $this->configs) {
            // 如果无需强制更新配置, 且当前已有配置, 则不继续操作
            return $this;
        }
        // 支持单池配置
        if (! Utility::isArrayLike(current($configs))) {
            $configs = [$configs];
        }
        $channelMapping = [];
        foreach ($configs as $k => $config) {
            $matchedInstanceConfig = null;
            foreach ($this->configs as $i => $instanceConfig) {
                if ($instanceConfig->match($config)) {
                    $matchedInstanceConfig = $instanceConfig;
                    $channel = $this->channelMapping[$i];
                    break;
                }
            }
            if ($matchedInstanceConfig) {
                // 若新配置匹配到配置实例, 则扩展该实例
                $matchedInstanceConfig->extend($config);
            } else {
                $matchedInstanceConfig = new $this->configClass($config);
                $channel = new self::$channelClass;
            }
            $configs[$k] = $matchedInstanceConfig;
            $channelMapping[$k] = $channel;

        }
        $this->configs = $configs;
        $this->channelMapping = $channelMapping;
        return $this;
    }

    /**
     * 取生产用配置, 若已无剩余配额, 则返回null
     * @param int|null $configIndex
     * @return PoolProductionConfig
     */
    private function getProductionConfig(int|null & $configIndex = null): PoolProductionConfig {
        if (null === $configIndex) {
            /*
             * 			                按生产算						                    按容量算
             *   容量	生产量	生产率	使用量	使用率	使用量	使用率	使用量	使用率	使用量	使用率	使用量	使用率	使用量	使用率
             *   10	        0	0.00%	    0	#DIV/0!	    0	#DIV/0!	    0	#DIV/0!	    0	0.00%	    0	0.00%	    0	0.00%
             *   10	        1	10.00%	    0	0.00%	    0	0.00%	    1	100.00%	    0	0.00%	    0	0.00%	    1	10.00%
             *   10	        2	20.00%	    0	0.00%	    1	50.00%	    2	100.00%	    0	0.00%	    1	10.00%	    2	20.00%
             *   10	        5	50.00%	    0	0.00%	    2	40.00%	    5	100.00%	    0	0.00%	    2	20.00%	    5	50.00%
             *   10	        8	80.00%	    0	0.00%	    4	50.00%	    8	100.00%	    0	0.00%	    4	40.00%	    8	80.00%
             *   10	        10	100.00%	    0	0.00%	    5	50.00%	    10	100.00%	    0	0.00%	    5	50.00%	    10	100.00%
             */
            // 取最小容量使用率配置集
            // 取最小生产率配置集 (省略)
            // 随机取其中一个配置
            $minSalesRate = -1;
            foreach ($this->configs as $i => $config) {
                $salesRate = $config->getSalesRate();
                // 如果未初始化最小消费率, 则初始化为第一个, 即时后面有更小的, 也会重新初始化, 所以不会出现都有第一个的问题
                if ($minSalesRate < 0 || $salesRate <= $minSalesRate) {
                    if ($salesRate !== $minSalesRate) {
                        // 如果当前为最小使用率, 则且暂无相同的值, 则表示为第一个, 需初始化最小使用率索引集, 并更新最小使用率值
                        $minSalesRateIndexes = [];
                        $minSalesRate = $salesRate;
                    }
                    $minSalesRateIndexes[$i] = 0;
                }
            }
            // 随机取一个最小使用率的配置, 实现负载均衡的生产/消费实例
            $configIndex = array_rand($minSalesRateIndexes);
        }
        return $this->configs[$configIndex];
    }

    /**
     * 匹配配置索引
     * @param array $config
     * @return int|null
     */
    protected function getConfigIndex(array $config): int|null {
        foreach ($this->configs as $i => $configInstance) {
            if ($configInstance->match($config)) {
                return $i;
            }
        }
        return null;
    }

    /**
     * 获取通道
     * @param int $index
     * @return ChannelAbstract
     */
    public function getChannel(int $index): ChannelAbstract {
        return $this->channelMapping[$index];
    }

    /**
     * 回收到实例池中
     * @param object $instance
     * @return bool
     * @throws null
     */
    final public function put(object $instance): bool {
        $product = $this->productMapping->get($instance);
        // 归还一个销量
        $product->config->return();
        return $product->channel->push($instance);
    }

    /**
     * 取实例 (建议在子类中定义fetch方法限定返回类型返回此方法的调用)
     * @param array $config
     * @return object
     * @throws PoolException
     */
    final protected function get(array $config = []): object {
        if ($config) {
            $configIndex = $this->getConfigIndex($config);
            if (null === $configIndex) {
                throw new PoolException(PoolException::CONFIG_PRODUCTION_NOT_MATCH);
            }
            $config = $this->getProductionConfig($configIndex);
        } else {
            $config = $this->getProductionConfig($configIndex);
        }
        $channel = $this->getChannel($configIndex);
        // 根据此产销率, 实现了惰性生产的特性, 只有当生产的实例被销售完后, 才尝试生产新的
        if ($config->getYieldSalesRate() >= 1) {
            // 如果生产的实例都被消费了, 则准备生产新实例
            if ($config->getYieldRate() >= 1) {
                // 如果实例已生产完, 则尝试清除由于异常等情况导致未能正常回收的实例
                // 如果还是没有剩余配额就表示实例都正常, 在短时间内被消费完了, 且实例已满, 无需新建实例, 待别人归还再消费
                // 此机制间接惰性的解决了连接断开后重连的问题, 因为连接断开后再获取查询时会抛出异常, 抛出异常则无法被回收, 无法被回收时会被PHP垃圾回收, 之后再进入此处逻辑则会被clear
                $this->productMapping->clear($config);
                $config = $this->getProductionConfig($configIndex);
            }
            if ($config->getYieldRate() < 1) {
                $config->yield();
                // 刚生产不应该增加销量或退还量, 但put会增加退还量, 所以这里先调个消费配平下吧
                $config->consume();
                // 如果当前通道为空, 且当前生产方案未生产满, 则新建实例
                $this->yield($config, $channel);
            }
        }
        // 记录一次消费
        $config->consume();
        // 使用Swoole协程通道时, 若通道中无实例, 则会挂起, 等到有消费方归还后, 再取出, 并继续后续动作
        $instance = $channel->pop();
        if (null === $instance) {
            throw new PoolException(PoolException::CHANNEL_BUSYING);
        }
        $this->productMapping->refresh($instance);
        return $instance;
    }

    /**
     * 根据实例取其映射的配置通道等
     * @param object $object
     * @return PoolProduct
     * @throws PoolException
     */
    final public function getProduct(object $object): PoolProduct {
        return $this->productMapping->get($object);
    }

    /**
     * 实例生产器 (负载均衡)
     * @param PoolProductionConfig $config
     * @param ChannelAbstract $channel
     */
    private function yield(PoolProductionConfig $config, ChannelAbstract $channel): void {
        $this->setConfigsInterface();
        $instance = $this->produce($config);
        $this->productMapping->set($instance, $config, $channel);
        $this->put($instance); // 入池
    }

    /**
     * 子类可以覆盖此方法, 用于从配置中心动态取配置
     */
    protected function setConfigsInterface(): void {}

    /**
     * 生产实例
     * @param PoolProductionConfig $config
     * @return mixed
     */
    abstract protected function produce(PoolProductionConfig $config): object;

    /**
     * 从池中取实例, 建议子类实现限定返回类型, 用于ide上下文分析
     * @return object
     */
    abstract public function fetch(): object;

    /**
     * 取实例
     * @param string ...$identities
     * @return static
     */
    abstract public static function inst(string ... $identities): static;
}
