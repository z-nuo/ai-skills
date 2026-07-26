# Testcontainers 与集成测试参考

## 目录

- [适用边界](#适用边界)
- [容器生命周期](#容器生命周期)
- [隔离策略](#隔离策略)
- [数据库](#数据库)
- [Redis与消息系统](#redis与消息系统)
- [Readiness与异步等待](#readiness与异步等待)
- [失败诊断](#失败诊断)
- [CI环境](#ci环境)
- [评审清单](#评审清单)

## 适用边界

以下行为优先使用真实依赖集成测试，而不是内存 fake：

- SQL、约束、索引、事务隔离、锁和 ORM mapping。
- Redis 数据结构、TTL、Lua、pipeline 和原子操作。
- 消息序列化、routing、ack/retry、消费位置和死信行为。
- 外部协议客户端与真实 server 的编码、连接和错误映射。

纯领域规则不需要容器。Testcontainers 用于验证基础设施边界，不要把所有单元测试升级成集成测试。

优先复用仓库已有 container helper。Go 通常使用 `testcontainers-go`；PHP 使用仓库已采用的 Testcontainers/Docker client 封装。不要同时维护多套生命周期代码。

## 容器生命周期

1. 检查 Docker 或兼容 runtime 是否可用；不可用时明确失败或按仓库策略跳过，不能静默变成 fake。
2. 选择与生产主版本一致的镜像，并固定明确版本或 digest，禁止使用浮动 `latest`。
3. 使用随机名称、动态端口和隔离网络，禁止假定本机端口空闲。
4. 使用 health check、日志条件、端口连接加协议探测等 readiness strategy 等待服务可用。
5. 使用生产同源的数据库迁移 (migration) 工具和顺序建立 schema，不维护测试专属建表逻辑。
6. 创建最小且显式的测试数据，执行测试并收集结果。
7. 通过测试框架 cleanup/teardown 终止资源；失败时先保留或输出必要日志。

容器启动成本高时可在 suite/package 范围复用容器，但每个测试仍必须隔离数据。不要跨独立 CI job 假定容器或 volume 可复用。

## 隔离策略

按依赖选择最小可靠隔离：

- 每个测试创建独立 database/schema，或在确认所有连接都参与同一事务时使用 rollback。
- 使用唯一 key prefix、tenant ID、topic/queue 名称或 consumer group。
- 动态端口由容器运行时分配，并从容器对象读取映射，不能写死 `localhost:3306` 等地址。
- 容器间通信使用测试网络和 network alias；宿主进程连接使用 mapped host/port。
- 并行测试共享容器时，数据命名、连接、migration 和清理必须无竞争。
- 不调用全局 `FLUSHALL`、删除公共 topic 或清空共享环境来实现隔离。

测试结束后验证关键副作用，而不是只依赖清理。例如事务失败后确认没有部分写入，幂等重试后确认只有一条业务记录。

## 数据库

- migration 必须与生产执行路径相同，并在测试开始前完成。
- seed 只包含当前场景必要数据；factory 显式设置影响行为的列。
- 验证唯一约束、外键、空值、精度、字符集、时区和 transaction boundary。
- transaction rollback 不能清理由其他连接、异步消费者或自动提交 DDL 写入的数据。
- 并发和锁测试使用独立连接及条件同步，设置有界 timeout，禁止用固定 sleep 猜测顺序。
- 测试失败时输出数据库版本、migration 状态和必要查询结果，避免输出敏感数据。

## Redis 与消息系统

### Redis

- 测试实际 key、TTL、过期、序列化和原子语义。
- 使用唯一 prefix 或独立实例隔离；Redis logical database 不适用于 cluster，也不能隔离所有全局命令。
- TTL 测试优先使用可控时钟或有界条件等待，不断言极窄的毫秒窗口。
- Lua 和事务测试同时验证返回值与最终状态。

### 消息系统

- 每个测试使用唯一 topic/queue 和 consumer group，避免读取其他测试消息。
- 验证 payload schema、headers、routing key、ack/nack、重试和重复投递下的幂等结果。
- 使用业务结果或消费位置判断完成，不以“等待几秒”代替条件。
- 测试结束时确认消费者停止并释放连接，防止后续测试收到遗留消息。

## Readiness 与异步等待

容器端口开放不一定代表服务已可用。readiness 至少验证一种协议级条件，例如数据库执行 `SELECT 1`、Redis `PING` 或 broker metadata 查询。

异步断言使用有界轮询：

1. 定义可观察条件。
2. 使用短间隔轮询并设置总 deadline。
3. 条件满足立即继续。
4. 超时时输出最后状态和依赖日志。

禁止固定 `sleep`。固定等待既拖慢成功路径，也会在慢机器和 CI 上产生偶发失败。

## 失败诊断

失败时收集最小但充分的证据：

- 镜像和服务版本、容器状态、退出码及 health 状态。
- mapped host/port、测试网络和连接目标，不输出密码或 token。
- 容器日志的相关尾部、migration 输出和应用错误链。
- 测试使用的唯一 database/schema/key prefix/topic 标识。
- timeout 是发生在启动、readiness、业务调用还是清理阶段。

不要用自动重试整个测试掩盖资源泄漏、竞争条件或 readiness 错误。

## CI 环境

- 确认 runner 提供 Docker socket、DinD、rootless runtime 或受支持的远程 daemon。
- 不假定 `localhost`、固定端口、宿主网络或特权模式可用。
- 根据容器启动成本设置 job timeout 和并行度，但不放宽单个业务断言。
- 固定镜像版本并考虑 registry 限流、代理和离线缓存。
- CI 失败必须保留测试报告和必要容器日志；成功路径避免产生大量无用日志。
- 本地和 CI 使用相同启动 helper、migration 和测试命令，减少环境分叉。

## 评审清单

- 该测试是否真的需要真实依赖，边界是否选得足够小？
- 镜像版本是否固定并与生产兼容？
- readiness 是否验证协议可用而不是只看端口？
- migration 是否来自生产路径，测试数据是否最小明确？
- database、Redis key、topic、端口和网络是否支持并行隔离？
- 是否存在固定 sleep、共享环境历史数据或全局清理？
- 失败时能否定位容器、连接、migration 或业务断言中的具体阶段？
- 本地和 CI 是否运行相同路径，并明确未支持的 runtime？
