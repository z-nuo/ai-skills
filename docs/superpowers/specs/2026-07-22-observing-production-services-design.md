# observing-production-services 设计规格

## 背景

当前仓库已有微服务接口设计、后端测试和数据一致性技能，但缺少生产运行阶段的可观测性设计与故障排查能力。新技能面向主要使用 Go、PHP 的后端研发人员，从“产生可靠信号”到“使用信号定位问题”形成闭环。

## 设计决策

- 创建单一技能 `observing-production-services`，不拆分埋点与故障排查。
- 保持厂商中立，以 OpenTelemetry 语义、Prometheus 指标模型和结构化日志为基础。
- 覆盖应用与依赖层；Kubernetes 只作为运行环境和证据来源，不覆盖集群运维。
- 同时支持新服务可观测性设计、线上故障排查和现有方案评审。
- `SKILL.md` 保留统一工作流和交付契约，语言与组件细节使用按需加载的 reference。

## 适用范围

技能在以下场景触发：

- 为 Go/PHP 服务设计日志、指标、Trace、Dashboard、告警和采样策略。
- 排查延迟升高、错误率上升、请求超时、资源耗尽和消息积压。
- 评审现有观测信号能否支持值班、定位和恢复。
- 故障后补充埋点、告警、Runbook 和验证方案。

组件范围包括 HTTP/gRPC、Go runtime、PHP-FPM/CLI worker、MySQL、Redis、Kafka 和 RabbitMQ。允许读取 Pod 状态、重启次数、资源 request/limit 等运行证据，但不设计 Kubernetes 集群、网络插件、调度器或控制面运维。

## 文件结构

```text
observing-production-services/
├── SKILL.md
├── agents/openai.yaml
└── references/
    ├── telemetry.md
    ├── go.md
    ├── php.md
    ├── dependencies.md
    └── incidents.md
```

各文件职责：

- `SKILL.md`：触发条件、统一工作流、任务模式、默认交付物、引用路由和红线。
- `telemetry.md`：结构化日志、指标类型、Trace/span、correlation、采样、基数、敏感数据和告警原则。
- `go.md`：Go HTTP/gRPC、runtime、goroutine、GC、连接池和安全使用 pprof 的诊断信号。
- `php.md`：PHP-FPM、CLI worker、异常、内存、请求生命周期及长驻进程的诊断信号。
- `dependencies.md`：MySQL、Redis、Kafka、RabbitMQ 的关键症状、指标关联和常见误判。
- `incidents.md`：分诊、时间线、假设验证、止损、恢复验证、Runbook 和复盘模板。

reference 只由 `SKILL.md` 直接引用，避免嵌套引用。超过 100 行的 reference 必须在开头提供目录。

## 统一工作流

1. 明确用户可见影响、时间窗口、受影响服务或租户、正常基线和成功恢复标准。
2. 检查服务拓扑、调用路径、近期发布或配置变更、运行环境和已有日志/指标/Trace。
3. 以时间线关联入口请求、服务实例、下游依赖和异步消费者信号。
4. 建立包含“假设、支持证据、反证、下一检查”的诊断表，按风险和验证成本排序。
5. 先执行可回滚且影响可控的止损措施，并明确审批、停止与回滚条件。
6. 使用错误率、延迟、吞吐、饱和度和积压验证恢复，区分症状缓解、临时恢复与根因修复。
7. 补充缺失埋点、Dashboard、告警、Runbook、容量边界和回归测试。

缺少关键证据时，输出已知事实、未知项和最小下一步，不使用猜测填补时间线或根因。

## 任务模式与交付物

### 可观测性设计

按顺序交付：

1. 服务目标、关键用户路径及 SLI/SLO 草案。
2. 结构化日志事件与字段。
3. 指标名称、类型、单位、label 和聚合维度。
4. Trace span 边界、属性、传播和采样策略。
5. Dashboard 视图与每个视图要回答的问题。
6. 告警条件、持续时间、严重级别、抑制和 Runbook 入口。
7. 成本、基数、敏感数据和验证清单。

### 线上故障排查

按顺序交付：

1. 影响摘要和当前状态。
2. 已确认事实与证据来源。
3. 时间线。
4. 假设表及下一检查。
5. 止损方案、风险、审批和回滚条件。
6. 根因结论及其证据；证据不足时明确标记未确认。
7. 恢复验证和持续观察窗口。
8. 后续修复、观测补强和负责人建议。

### 现有方案评审

先按严重程度列出会造成故障不可发现、不可定位或误告警的问题，并指向具体日志事件、指标、span、Dashboard 或告警。随后给出最小修正方案和验证方式；没有发现时说明剩余盲区和未验证场景。

## 信号关联原则

- 使用有限基数的 service、operation、status、dependency 和 error class 作为指标维度。
- request ID、trace ID、user ID、SQL、原始 URL 参数和完整错误文本只进入受控日志或 Trace，不进入指标 label。
- HTTP/gRPC、数据库、缓存和消息消费使用一致的 trace/correlation 传播规则。
- 错误、慢请求和关键业务路径使用 tail sampling 或等价策略提高保留率。
- 日志、指标和 Trace 的时间、实例、版本及 operation 命名必须能够相互关联。
- Dashboard 按“用户症状 -> 服务 RED/USE -> 依赖 -> 实例/runtime”逐层下钻。

## 安全与操作边界

- 不记录密码、token、Cookie、完整请求体、私钥或未经脱敏的个人数据。
- 不把相关性直接认定为因果，不在证据不足时宣布根因。
- 不为每条异常创建告警；告警面向用户症状、SLO 消耗、容量耗尽和需要人工处理的状态。
- 不以重启作为默认诊断方法；重启可能清除关键现场，必须先保留必要证据。
- 线上操作默认只读。修改配置、扩容、重启、kill、清队列、重放消息或执行高负载查询需要显式授权。
- pprof、debug endpoint 和诊断转储必须受认证、网络隔离、访问审计和暴露时限保护。

## 与现有技能的边界

- API/gRPC 契约或兼容性问题使用 `designing-microservice-interfaces`。
- 需要实现回归、集成或故障注入测试时使用 `testing-backend-services`。
- 发现 Outbox、Inbox、缓存、重复消费或 Saga 一致性缺陷时使用 `designing-data-consistency`。
- 本技能可以提出补强需求，但不重复这些技能的详细实现指导。

## 验证策略

在创建技能正文前建立 RED 契约，确认目标目录和关键内容尚不存在。GREEN 阶段至少验证以下场景可以由技能覆盖：

- Go 服务 p99 延迟升高，区分 GC、goroutine 堆积、连接池和下游延迟。
- PHP-FPM active process 饱和或 CLI worker 长驻内存增长。
- MySQL 慢查询、锁等待或连接池耗尽。
- Redis 延迟、驱逐、热点 key 或回源放大。
- Kafka consumer lag 或 RabbitMQ ready/unacked 积压。
- 跨 HTTP/gRPC 与消息队列的 trace/correlation 断链。
- 没有足够信号时输出不确定性和下一项最小检查，而不是臆测根因。

结构校验包括：YAML frontmatter、UI 元数据、所有 reference 直接可达、长文目录、关键词、无占位符、无尾随空白和代码围栏平衡。官方 validator 无法运行时，记录具体依赖错误，并使用可用 YAML 解析器完成等价结构校验。

## 完成标准

- 技能能在可观测性设计、故障排查和方案评审三个模式间正确选择输出契约。
- Go、PHP 分别具有独立 reference；MySQL、Redis、Kafka、RabbitMQ 在 `dependencies.md` 中具有清晰、可定位的独立章节。
- 文档明确区分事实、假设、止损、恢复和根因修复。
- 指标基数、敏感数据、采样和线上操作边界具有明确红线。
- 所有静态契约验证通过，并记录无法执行的环境相关验证。
