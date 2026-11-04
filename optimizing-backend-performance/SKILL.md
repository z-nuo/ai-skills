---
name: optimizing-backend-performance
description: 诊断 Go/PHP、存储和消息瓶颈，设计可复现的性能实验、压测与容量评估。
---

# 后端性能优化

先确认目标路径、当前差距和业务不变量，用代表性负载与可比基线验证瓶颈。事实、相关性和待验证假设分开，不凭代码外观宣称性能提升。

1. 从已有指标、trace、日志或 profile 缩小瓶颈，区分计算、排队、下游和网络等待。
2. 选择一个有证据的假设，固定负载、版本外的环境及统计口径；一次改变一个关键变量。
3. 同时比较尾延迟、有效吞吐、错误/重试、正确性和资源成本，记录停止条件与回滚。
4. 容量结论用满足目标的稳态能力，另计增长、故障余量和下游约束；负载或环境变化后重新校准。

## 按需参考

- 基线、实验和统计：[方法论](references/methodology.md)。
- benchmark、pprof、GC、并发与连接池：[Go](references/go.md)。
- FPM、OPcache、内存与 CLI worker：[PHP](references/php.md)。
- 查询、锁、Redis 命令与 pipeline：[MySQL/Redis](references/data-stores.md)。
- consumer、batch、背压与积压恢复：[Kafka/RabbitMQ](references/messaging.md)。
- 负载模型、压测和容量推导：[压测](references/load-testing.md)。

生产默认使用已有只读证据。在线 profiling 须在已有授权范围内限定实例、时长、采样与资源停止阈值，结束恢复配置；诊断端点和产物限制访问并脱敏。压测与故障注入使用隔离资源。

输出当前证据支持的结论、最小优化或下一实验及未验证边界。microbenchmark 不证明端到端收益；不通过隐藏错误、减少必要校验或弱化鉴权、事务、顺序和幂等换数字。
