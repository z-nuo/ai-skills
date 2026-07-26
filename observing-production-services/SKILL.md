---
name: observing-production-services
description: Use when designing observability for Go or PHP backend services, diagnosing production latency, errors, saturation, or message backlog, reviewing logs, metrics, traces, dashboards, and alerts, or strengthening telemetry after an incident.
---

# 生产服务可观测性与故障排查

## 核心原则

- 先影响后组件：先界定用户可见影响、范围和时间窗口，再下钻服务、实例和依赖。
- 先事实后假设：区分已确认事实、待验证假设和未知项，不用相关性替代因果证据。
- 先可回滚止损后永久修复：优先选择影响可控、审批明确且能够停止或回滚的措施。
- 恢复必须以信号验证：使用错误率、延迟、吞吐、饱和度和积压确认恢复，并区分症状缓解、临时恢复与根因修复。

所有方案保持厂商中立，以 OpenTelemetry 语义、Prometheus 指标模型和结构化日志为基础。仅当用户明确指定平台时，将通用设计映射到对应查询、Dashboard 或告警能力；不得默认绑定商业厂商或特定后端。

## 统一工作流

1. **范围与基线**：明确用户可见影响、时间窗口、受影响服务或租户、正常基线和成功恢复标准。
2. **上下文**：检查服务拓扑、调用路径、近期发布或配置变更、运行环境，以及已有日志、指标和 Trace。
3. **时间线**：按时间关联入口请求、服务实例、版本、下游依赖和异步消费者的信号。
4. **假设表**：记录“假设、支持证据、反证、下一检查”，按风险与验证成本排序。证据不足时输出已知事实、未知项和最小下一步。
5. **止损**：提出可回滚且影响可控的措施，注明风险、所需审批、停止条件和回滚条件。
6. **恢复验证**：用关键业务信号和持续观察窗口验证恢复，不因单一指标回落而宣布根因已修复。
7. **观测补强**：补充缺失埋点、Dashboard、告警、Runbook、容量边界和回归测试需求。

Kubernetes 仅作为运行环境和证据来源，可读取 Pod 状态、重启次数、resource request/limit 等运行证据。本技能不提供 Kubernetes 集群、网络插件、调度器或控制面运维设计或操作。

## 直接引用

仅加载当前任务直接相关的 reference，不从 reference 继续递归加载其他文档：

- 日志、指标、Trace、Dashboard、告警和采样读取 [references/telemetry.md](references/telemetry.md)。
- 分析 Go HTTP/gRPC、runtime、goroutine、GC、连接池或 pprof 信号时，读取 [go.md](references/go.md)。
- 分析 PHP-FPM、CLI worker、异常、内存、请求生命周期或长驻进程时，读取 [php.md](references/php.md)。
- 涉及 MySQL、Redis、Kafka 或 RabbitMQ 的症状、指标关联与常见误判时，读取 [dependencies.md](references/dependencies.md)。
- 处理活跃事故、分诊、时间线、假设验证、止损、恢复验证、Runbook 或复盘时，读取 [incidents.md](references/incidents.md)。

## 任务模式

### 可观测性设计

按以下顺序交付：

1. 服务目标、关键用户路径及 SLI/SLO 草案。
2. 结构化日志事件与字段。
3. 指标名称、类型、单位、label 和聚合维度。
4. Trace span 边界、属性、传播和采样策略。
5. Dashboard 视图及每个视图要回答的问题。
6. 告警条件、持续时间、严重级别、抑制和 Runbook 入口。
7. 成本、基数、敏感数据和验证清单。

### 线上故障排查

按以下顺序交付：

1. 影响摘要和当前状态。
2. 已确认事实与证据来源。
3. 时间线。
4. 假设表及下一检查。
5. 止损方案、风险、审批和回滚条件。
6. 根因结论及其证据；证据不足时明确标记“未确认”。
7. 恢复验证和持续观察窗口。
8. 后续修复、观测补强和负责人建议。

### 现有方案评审

先按严重程度列出会造成故障不可发现、不可定位或误告警的问题，并指向具体日志事件、指标、span、Dashboard 或告警。随后给出最小修正方案和验证方式；没有发现时，说明剩余盲区和未验证场景。

## 与现有技能的边界

- API/gRPC 契约或兼容性问题使用 `designing-microservice-interfaces`；本技能只使用接口运行信号定位影响。
- 需要实现回归、集成或故障注入测试时使用 `testing-backend-services`；本技能只提出测试与恢复验证需求。
- 发现 Outbox、Inbox、缓存、重复消费或 Saga 一致性缺陷时使用 `designing-data-consistency`；本技能只呈现症状、证据和补强需求。

## 默认交付物与红线

默认交付物必须明确影响与基线、事实及证据来源、未知项、假设与下一检查、操作风险与回滚条件、恢复信号和后续负责人建议。不要用猜测填补时间线或根因。

- **线上操作**：默认只读。修改配置、扩容、重启、kill、清队列、重放消息或执行高负载查询必须取得显式授权；操作前保留必要现场，并写明审批、停止和回滚条件。
- **安全**：不得记录密码、token、Cookie、私钥、完整请求体或未经脱敏的个人数据。pprof、debug endpoint 和诊断转储必须受认证、网络隔离、访问审计和暴露时限保护。
- **高基数**：指标 label 只使用有限基数且会改变查询、告警或容量决策的维度，例如 service、operation、status、dependency 和 error class；这些示例不是封闭白名单。instance/version/partition 只用于受控诊断聚合，不进入长期全局 SLO。request ID、trace ID、user ID、SQL、原始 URL 参数和完整错误文本只能进入受控日志或 Trace，不得进入指标 label。
- **告警与采样**：告警面向用户症状、SLO 消耗、容量耗尽和需要人工处理的状态；不得为每条异常创建告警。对错误、慢请求和关键业务路径使用 tail sampling 或等价策略提高保留率。
