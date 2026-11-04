# 依赖诊断

在同一时间窗区分应用 acquire wait、DNS/dial/TLS/网络、服务端执行与重试；对照异常/正常实例及负载构成。只有调用方错误不足以断言依赖故障。

| 依赖 | 关联证据 | 常见误判 |
|---|---|---|
| MySQL | 查询 digest 频率/总耗时、扫描/返回行、锁等待及持锁事务、CPU/I/O/活跃线程、复制状态 | 单慢 SQL 不能覆盖所有请求；连接多不等于饱和，复制延迟不自动解释主库慢 |
| Redis | 客户端分段耗时、命令耗时、hit/miss 分子分母、RSS/eviction、hot/big key、回源 | hit ratio 下降可能是负载/TTL 改变，需有回源和用户影响链 |
| Kafka | 分 partition 输入/处理率、lag、最老年龄、rebalance/暂停、broker 与下游 | lag 是数量不是延迟，积压不自动等于 broker 故障 |
| RabbitMQ | publish/confirm、deliver/ack、ready/unacknowledged、最老年龄、redelivery 与消费者 | unacknowledged 增长可能是处理慢或 prefetch 囤积；ack 不独自证明业务成功 |

总体资源有余量仍需按 query/key/partition/queue 分组找倾斜。池满而对端稳定时先查本地泄漏、持有时间和并发；client 慢而 server 正常时先查网络与本地等待。

优先交叉验证应用与对端证据，解释影响范围及替代假设。保留线上只读边界；kill、flush、purge、replay 和配置改变不作为默认诊断步骤。
