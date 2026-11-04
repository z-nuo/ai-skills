# MySQL / Redis 性能

先分离应用排队、连接获取、网络和服务端执行，在同窗口按查询/命令、参数分布及实例对照，避免把全部等待归为中间件慢。

## MySQL

- 查询按 digest 的次数、总耗时、rows examined/returned、排序与临时表分析，兼顾高频中等耗时。`EXPLAIN` 是估算；`EXPLAIN ANALYZE` 实际执行，放在受控数据环境。
- 锁等待追到持锁事务及其完整生命周期，区分行锁、metadata lock、死锁和 I/O；不要只改等待方 SQL。
- 索引按谓词、join、排序、覆盖和真实数据倾斜设计，同时测写放大、存储与复制。唯一/外键索引还有正确性职责，不能当普通重复索引删除。
- 消除 N+1、改分页或批写时保留缺失/重复行、稳定排序、权限、快照、原子与部分失败语义。SQL 次数减少不自动更快。
- 在线 DDL 仍有资源及 metadata lock 风险；生产变更需要真实部署/回滚边界，不能当诊断命令执行。

扩大池前核对 acquire 等待、所有实例连接上限及数据库资源；事务归还连接前完成提交/回滚并清理 session 状态。参数改变一次只验证一个关键变量。

## Redis 诊断成本

先用已有 exporter、客户端 trace 和代理摘要，补证只读必要范围。命令返回慢不等于服务端执行慢，`SLOWLOG` 不含客户端排队、网络和响应读取。

| 工具 | 关键限制 |
|---|---|
| `INFO <section>` / `SLOWLOG GET <count>` | 小范围、低频；slowlog 参数可能敏感 |
| `LATENCY LATEST` | 只读已有采样，不为补证擅自开启配置 |
| `MEMORY USAGE key SAMPLES 5` | 嵌套结构的估算；`SAMPLES 0` 完整遍历仅用于可销毁隔离副本 |
| `SCAN` | 全轮 O(N)，`COUNT` 非硬上限且可能重复；限定 shard、时间、调用、key 与字节预算 |
| `--bigkeys` / `--hotkeys` | 扫描有成本；前者主要是逻辑大小，后者依赖 LFU 且不是精确 QPS |
| `KEYS` / `MONITOR` | 不用于生产诊断；前者发出后断开客户端不能中止服务端遍历，后者有开销与敏感数据暴露 |

## Redis 候选

hot key 看频率与倾斜，big key 分清成员/响应字节与实际内存。结合 CPU、RSS、fragmentation、fork/COW、eviction、miss 与回源，不能仅凭命中率认定故障。

pipeline 减往返，不减命令复杂度，也不自动原子；批次限定命令数、字节和等待，核对请求/响应顺序及部分成功。不能盲目重放整批写入。

本地缓存、TTL、拆 key、压缩和请求合并须同时验证允许陈旧、read-after-write、失效恢复、内存、CPU及回源能力；扩连接核对全实例 `maxclients`/proxy 预算。失败或结果未知不通过兜底和无限重试隐藏。
