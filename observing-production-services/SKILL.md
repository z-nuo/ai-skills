---
name: observing-production-services
description: 设计日志、指标、trace 和告警，或排查 Go/PHP 生产故障并验证恢复。
---

# 生产可观测性与排障

从用户影响、时间窗口和正常基线开始，沿入口、服务、依赖及实例关联证据。优先复用现有日志、指标、trace 和发布记录；采用已有平台，没有约定时以结构化日志、OpenTelemetry 和 Prometheus 模型设计。

- 排障区分事实、假设、反证和未知项，先做能区分假设的最小检查。相关性与重启后改善均不足以确认根因。
- 止损限定影响、停止和回滚条件；用业务成功、延迟、错误及积压的持续变化确认恢复，区分缓解、恢复与根因修复。
- 观测设计只补能回答关键问题的信号；评审先列不可发现、不可定位或误告警的具体缺口。
- 线上操作默认只读；配置、重启、扩容、kill、清队列与重放按已有显式授权执行，不因诊断自行扩大权限。

## 按需参考

- 日志、指标、trace、采样、告警：[Telemetry](references/telemetry.md)。
- runtime、goroutine、GC、连接池：[Go](references/go.md)。
- FPM、异常、内存、长驻进程：[PHP](references/php.md)。
- 数据库、缓存与 broker：[依赖](references/dependencies.md)。
- 活跃事故、止损、恢复与复盘：[事故](references/incidents.md)。

指标 label 保持有限基数，ID、SQL、原始 URL 与错误正文留在必要且受控的日志/trace。凭据和敏感载荷不写入观测；debug 端点及转储限制访问。Kubernetes 状态作为运行证据，不扩展为集群运维任务。

按任务交付最重要的发现、证据、下一步或已验证结果；数据缺失时明确结论边界，不填造根因或恢复状态。
