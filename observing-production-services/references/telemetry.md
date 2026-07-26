# Telemetry 信号设计

本参考用于把用户路径、服务目标和生产故障假设转换为可执行的日志、指标、Trace、Dashboard 与告警设计。所有设计保持厂商中立，以 OpenTelemetry 语义、Prometheus 指标模型和结构化日志为基础；先定义要回答的问题，再选择信号和字段。

Kubernetes 只提供运行证据，例如 Pod 状态、重启次数和 resource request/limit。它不能替代用户路径、服务边界或依赖信号，也不应成为默认的可观测性模型。

## 目录

1. 从用户路径定义 SLI/SLO
2. 用 RED 与 USE 组织信号
3. 结构化日志
4. Prometheus 指标模型
5. Trace 与上下文传播
6. head sampling 与 tail sampling
7. Dashboard 下钻路径
8. 告警与 Runbook
9. cardinality、成本与敏感数据
10. 时钟与 telemetry pipeline 故障边界
11. 设计检查清单

## 从用户路径定义 SLI/SLO

先描述用户要完成的动作以及成功、失败和可接受耗时，再讨论组件指标。每条关键路径至少定义：

- **路径边界**：入口、关键同步依赖、异步完成点，以及是否包含客户端等待时间。
- **good event**：满足业务结果且延迟在阈值内的事件。
- **valid event**：进入 SLI 分母的事件；明确排除健康检查、内部探针和无效流量的规则。
- **SLI**：例如成功事件占比、低于阈值的事件占比、消息在截止时间内完成的占比。
- **SLO**：目标比例和滚动窗口，并写明测量点、数据缺失处理和低流量策略。
- **错误预算**：`1 - SLO`，用于发布、风险接受和改进优先级，而不是作为忽略单次严重事故的理由。

优先在最接近用户结果的边界测量。入口返回成功但异步任务最终失败时，入口成功率不能单独代表业务成功。客户端、边缘和服务端口径不一致时，同时保留各自 SLI，并明确哪个是 SLO 的权威口径。

低流量服务不要依赖短窗口百分比：同时检查绝对失败数、最小事件量和较长窗口。telemetry 数据缺失不得自动视为成功；应单独显示数据完整性。

## 用 RED 与 USE 组织信号

RED 用于请求或事务路径：

- **Rate**：每秒请求、事务或消费事件数，按有限基数的 `service`、`operation`、`status` 聚合。
- **Errors**：失败率和失败数；业务拒绝、调用方错误、服务故障分开建模。
- **Duration**：端到端延迟的 histogram 分布，围绕 SLO 阈值观察，而不只看平均值。

USE 用于资源及有容量上限的池：

- **Utilization**：资源繁忙或已占用的比例，例如 CPU 时间、连接池已用连接。
- **Saturation**：等待工作或逼近硬上限的程度，例如 runnable queue、线程池队列、连接等待者。
- **Errors**：分配失败、超时、丢弃和硬限制触发次数。

每个用户症状至少能下钻到一个 RED 变化、一个依赖信号或一个 USE 容量证据。资源升高只是证据；没有时间相关性和路径影响时，不应直接宣告根因。

## 结构化日志

日志采用稳定的 event name 和机器可解析结构。字段名、类型和含义应跨版本稳定；新增字段优于改变旧字段语义。

建议的核心字段：

| 目的 | 字段 | 规则 |
| --- | --- | --- |
| 事件身份 | `event.name` | 稳定、低基数，如 `checkout.completed`，不要把 ID 拼入名称 |
| 严重级别 | `severity_text`、`severity_number` | 使用统一映射；严重级别表达处理紧迫性，不等于 HTTP status |
| 服务身份 | `service.name`、`service.version`、`deployment.environment.name` | 采用 OpenTelemetry Resource 语义；版本必须可关联发布 |
| 操作 | `operation.name` | 使用规范化 route、RPC method 或 consumer operation |
| 关联 | `trace_id`、`span_id`、`correlation_id` | 保持为独立字段；不得拼进消息文本或指标 label |
| 结果 | `event.outcome`、`http.response.status_code` | 区分成功、调用方拒绝、服务错误和取消 |
| 错误分类 | `error.type` 或 `error.class` | 使用有限分类；原始异常文本放受控字段并执行脱敏 |

事件应记录发生了什么、作用于哪个规范化操作、结果是什么，以及可用于关联的标识。不要重复记录每层相同异常；负责处理或改变结果的边界记录一次，并保留异常链所需的受控信息。

日志不得包含密码、token、Cookie、私钥、完整请求体或未经脱敏的个人数据。`user ID`、原始 URL 参数、SQL 和完整错误文本即使进入受控日志，也必须按必要性最小化、脱敏、限制访问并设置短保留期；哈希并不自动等于匿名化。

## Prometheus 指标模型

### 类型选择

- **counter**：只增不减的累计事件或字节数，如 `http_server_requests_total`、`job_failures_total`。用 `rate()` 或窗口增量分析，不直接比较进程重启前后的原始值。
- **gauge**：可升可降的当前状态，如队列深度、活跃连接、在途任务。注明是瞬时值、上限还是最近观测时间；只有语义允许时才跨实例求和。
- **histogram**：请求时长、消息大小等分布。它保留 bucket、count 和 sum，支持跨实例聚合和服务端分位数估算。

延迟和大小默认选 histogram。不要对实例分位数取平均；需要跨实例计算分位数时，先按兼容 bucket 聚合 histogram，再计算 quantile。只有无法统一 bucket 且明确接受不可聚合限制时才考虑客户端 summary。

### 命名、单位与 bucket

- 名称表达领域和含义，使用 Prometheus base unit，例如 seconds、bytes；counter 使用 `_total` 后缀。
- help 文本说明事件边界、单位和 label 含义，避免同名指标在不同服务中语义漂移。
- histogram bucket 围绕 SLO 阈值、典型值和最坏可接受值设置；至少有一个 bucket 对齐关键 SLO 阈值。
- 同一聚合组使用一致 bucket 边界。变更 bucket 视为指标契约变更，先评估查询、告警和成本影响。
- 不同时钟窗口、不同事件边界或不同单位的序列不得直接相加。

### label 与聚合

优先使用有限基数、可枚举的 `service`、`operation`、`status`、`dependency` 和 `error_class`。仅保留会改变查询、告警或容量决策的维度；新 label 上线前估算笛卡尔积和实例数。

以下内容不得进入指标 label：

- request ID、trace ID 或 span ID；
- user ID、account ID、email 或其他个人标识；
- SQL、完整错误文本或 stack trace；
- 原始 URL 参数、未规范化 path、消息正文；
- 时间戳、随机值或任何无上限自由文本。

`status` 应使用有限结果分类或 status code class；`operation` 使用 route template 或稳定 RPC method；`error_class` 使用受控枚举。无法约束基数的诊断细节进入受控日志或 Trace，并通过 `trace_id` 关联。

聚合时保留回答问题所需的最少维度：全局 SLI 通常先跨实例求和；定位时再按 version、zone 或 instance 下钻。instance 维度不进入长期 SLO 聚合，除非用户体验确实以实例为边界。

## Trace 与上下文传播

### span 边界

在有独立等待时间、失败语义或所有权变化的边界创建 span：服务入口、外部依赖调用、持久化操作、消息生产与消费、关键业务阶段。不要为普通函数逐个创建 span；过细 span 增加成本并掩盖关键路径。

- span name 使用低基数规范名称，不包含 ID、原始 URL 或 SQL。
- kind 与边界匹配，如 `SERVER`、`CLIENT`、`PRODUCER`、`CONSUMER`、`INTERNAL`。
- status 只表达该 span 操作是否失败；预期业务拒绝不应一律标为 `ERROR`。
- attribute 遵循 OpenTelemetry semantic conventions，值保持有界；高基数诊断属性受采样和访问控制约束。
- error event 记录必要的异常类型和脱敏信息，避免复制完整敏感 payload。

### context propagation 与 link

同步调用传播 W3C Trace Context 或等价标准上下文，并在信任边界验证格式。`baggage` 仅允许经过审批的短字段白名单，禁止传播凭证、个人数据和无界值。

消息队列中，producer 将 trace context 写入消息 metadata，consumer 提取上下文：

- 单条消息形成直接因果链时，可让 consumer span 继承远端上下文。
- 批量消费、合并、重试或一个操作关联多个消息时，使用 span link 关联原始 message context，避免伪造单一父子关系。
- 消息 ID 可进入受控 Trace attribute 或日志用于定位，但不得进入指标 label。
- 明确记录 publish、broker wait、consume 和 process 的边界，区分排队时间与处理时间。

传播失败应形成可计数的有限分类和诊断事件，但不能阻断业务请求。跨服务关联既依赖传播成功，也依赖各服务使用同一 `trace_id` 编码和采样约定。

## head sampling 与 tail sampling

head sampling 在 Trace 开始时决策，开销可预测，适合稳定比例或按服务、operation 的确定性采样。它尚不知道最终错误和延迟，因此单独使用时可能丢失最重要的异常路径。

tail sampling 在观察到完整或足够长的 Trace 后决策，应提高以下数据的保留率：

- error status 或明确失败结果；
- 超过 SLO 阈值的慢请求；
- 低频但关键的业务路径；
- 与事故窗口、指定受控租户分组或异常依赖相关的 Trace。

采样策略必须说明正常流量基准率、优先规则、每服务配额、最大等待时间和超限降级。tail sampler 需要缓冲，必须监控其内存、丢弃数、决策延迟和队列深度；过载时采用明确的确定性降级，而不是静默丢弃全部异常样本。

采样只降低 Trace event/span 的存储量，不能对 SLI counter 随机采样后直接计算真实错误率。日志采样必须为被丢弃事件保留计数或权重，安全审计日志不得依赖普通诊断采样策略。

## Dashboard 下钻路径

Dashboard 按“用户症状 → 服务 → 依赖 → 实例/runtime”组织，避免从主机资源图开始：

1. **用户症状**：SLI/SLO、错误预算消耗、请求量、成功率、端到端延迟和数据完整性。
2. **服务**：按 operation、status、version 展示 RED，并标出发布和配置变更时间。
3. **依赖**：展示下游调用的 rate、error、duration，以及连接池、超时、重试和断路状态。
4. **实例/runtime**：按实例、zone 和版本检查 USE、进程重启、GC、worker/thread/goroutine、文件描述符与池饱和。

每个面板写明单位、聚合方式、时间窗口和“它要回答的问题”。共享一致的时间范围与筛选维度；从聚合面板可跳到相关 Trace 或受控日志查询。空图必须区分“确实为零”“无流量”“查询错误”和“telemetry 缺失”。

Kubernetes Pod phase、restart count、OOMKilled 和 resource request/limit 只放在实例/runtime 层作为运行证据。它们可以支持资源或发布假设，但不能单独证明用户影响或根因。

## 告警与 Runbook

告警只针对需要人工判断或操作的状态，优先覆盖：

- 基于 SLO burn rate 的用户路径告警，使用快慢多窗口兼顾快速事故和持续消耗；
- 用户可见错误率、延迟或业务完成率异常；
- 即将耗尽的饱和资源，并有明确的处理时限；
- 超过自动恢复能力、需要人工处理的积压或死信；
- telemetry pipeline 大面积缺失，导致关键 SLI 无法判断。

每条告警必须包含：受影响的 service/operation、当前值和阈值、持续时间、SLO 或容量语义、相关 Dashboard、Runbook、初始只读检查、升级路径和抑制条件。严重级别由用户影响与响应时限决定，不由单个错误日志级别决定。

SLO 告警使用 burn rate 而不是固定错误数：短窗口捕获快速消耗，长窗口确认持续性；同时满足两个窗口可以减少瞬时噪声。低流量路径需要绝对事件数或最小分母保护。

不要为每条异常、每个 Pod 重启或每个高资源点创建告警。无法给出明确人工动作、停止条件或 Runbook 的告警，应先作为 Dashboard 诊断信号。

## cardinality、成本与敏感数据

成本预算同时覆盖采集、网络、处理、索引和保留：

- 指标成本近似由序列数决定；按每个 label 的可能值和组合估算 cardinality，设置每服务序列预算。
- 日志成本由事件量、字段大小、索引字段和保留期决定；高量成功事件优先使用指标计数或受控采样。
- Trace 成本由请求量、每 Trace span 数、attribute/event 大小、采样率和保留期共同决定。
- Dashboard 和告警只查询必要维度，避免大范围正则、无限时间窗口和高基数 group-by。

在 collector 或后端设置 cardinality 上限、速率限制和丢弃计数。新增信号上线前估算正常与峰值成本，上线后比较实际序列、字节和拒绝量；超过预算时先保护 SLI 和错误诊断信号。

敏感数据采用默认拒绝和字段白名单。采集前完成删除、规范化或不可逆脱敏，传输与存储加密，按角色限制访问并审计查询，按用途设置最短保留期。删除规则必须同时覆盖日志正文、Trace attribute/event 和 exporter 失败时的本地缓冲。

## 时钟与 telemetry pipeline 故障边界

所有节点同步时钟并监控 offset。跨主机 wall clock 可用于近似时间线，但 duration 应优先由同一进程的 monotonic clock 测量。显著 clock skew 会造成负 duration、span 顺序错乱和错误窗口归属，必须作为数据质量问题呈现。

telemetry pipeline 与业务路径隔离：

- SDK/exporter 使用有界队列、批处理、超时和资源上限；默认异步导出，失败不得阻断业务请求。
- collector 暴露自身接收、拒绝、丢弃、队列、重试、导出延迟和后端错误指标。
- exporter 失败采用有上限的重试和退避；本地缓冲必须有容量、保留时限和敏感数据保护。
- pipeline 过载时按优先级保护 SLI 聚合、错误和关键路径，记录每类丢弃量；不得静默丢弃。
- 应用、collector 和后端设置独立资源预算，避免 telemetry 放大故障或与业务争抢全部 CPU、内存和网络。

Dashboard 显示 freshness、ingestion lag、缺失比例和最后成功导出时间。数据缺失时明确降级结论可信度，使用独立健康通道告警，不能把缺失序列解释为零错误或零流量。

## 设计检查清单

- SLI/SLO 是否从用户路径和业务完成点定义，并明确分母、窗口与数据缺失处理？
- 请求路径是否有 RED，有限资源是否有 USE，二者能否通过时间和版本关联？
- 日志是否使用稳定 event name、severity、service、version、operation、trace/correlation 和 error class 字段？
- counter、gauge、histogram 的类型、单位、命名、bucket 和聚合是否明确？
- Trace 是否定义 span 边界、context propagation、status、attribute、link 和消息队列关联？
- head sampling、tail sampling 是否保留错误、慢请求和关键业务路径，并有容量降级策略？
- Dashboard 是否按用户症状、服务、依赖、实例/runtime 下钻，并解释空数据？
- 告警是否面向 SLO burn rate、用户症状、饱和度或人工积压，并关联 Runbook？
- 指标 label 是否保持有限 cardinality，且排除 request ID、user ID、SQL、原始 URL 参数和完整错误文本？
- 成本、敏感数据、时钟以及 telemetry pipeline 自监控和故障隔离是否得到验证？
