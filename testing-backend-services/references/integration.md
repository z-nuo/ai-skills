# 集成测试

真实依赖用于验证 SQL/事务、Redis Lua/TTL/pipeline、消息 routing/ack 等边界；纯计算不需容器。复用仓库已有 helper 和环境，确需容器时再选现有 Testcontainers 等工具。

## 环境与数据

- 镜像固定可复现版本并匹配生产主要版本；端口动态分配，网络、database/schema、Redis 前缀、topic/queue/group 相互隔离。
- readiness 用协议检查（如 `SELECT 1`、`PING`），而非只等端口；异步结果用带 deadline 的条件轮询。
- schema 使用生产同源 migration，fixture 只含当前场景数据。多连接、异步与 DDL 不能依赖单连接 rollback 清理。
- 只清理本次拥有的资源，不对共享环境执行 `FLUSHALL` 或全局清队列。

## 结果与清理

断言最终持久化、副作用及错误语义，不只断言调用成功或容器已启动。测试退出时停止消费者并清理连接与隔离资源；失败保留脱敏日志、版本、阶段和最后状态。

Docker/依赖不可用时明确失败或跳过，不换成替身后声称真实集成通过。避免自动重试整个测试掩盖不稳定性，先定位 readiness、隔离还是业务失败。
