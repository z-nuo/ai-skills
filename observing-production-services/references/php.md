# PHP 生产服务诊断

本参考用于诊断 PHP-FPM、同步请求和长驻 CLI worker 的延迟、错误、内存与容量问题。先界定用户影响、时间窗口、版本和正常基线，再把 PHP-FPM 状态、请求日志、应用事件、Trace、OPcache、进程和运行环境证据按时间关联。任何单一曲线只能提出假设，不能直接宣布根因。

线上诊断默认只读。读取 FPM 状态、日志、Trace、OPcache 状态和进程信息前，确认访问受认证、网络隔离、审计和最小权限保护。修改配置、抓取转储、终止 worker、扩容或重启都必须取得显式授权；操作前保留必要现场，写明停止条件和回滚方式。

## 目录

1. 请求信号与基线
2. PHP-FPM 容量与队列
3. 时间线与日志关联
4. slowlog 与慢请求
5. OPcache 证据
6. 内存语义与 RSS
7. CLI worker 诊断
8. 异常、超时、取消与 fatal error
9. 证据驱动的诊断顺序
10. Kubernetes 运行证据
11. 止损、回收与重启边界
12. 结论检查清单

## 请求信号与基线

入口和内部请求使用统一的规范化 `operation`、版本和实例维度，至少记录以下 RED 信号：

- **request rate**：请求数量的 counter rate；区分正常请求、健康检查和内部探针。
- **error**：分别记录 transport 结果、失败机制和发起层；不要把 HTTP status、异常类型和责任边界压成同一维度。
- **request duration**：入口总耗时 histogram，使用统一单位和覆盖 SLO 的 bucket；不要对各实例分位数取平均。
- **in-flight**：当前执行中的请求数，和 FPM active/idle process、队列及完成率一起解释。

请求事件至少带有 `service.name`、`service.version`、`deployment.environment.name`、规范化 `operation.name`、`trace_id`、`span_id`、transport 结果、失败机制和发起层/责任边界。transport 结果独立记录 HTTP status class（如 `2xx`、`4xx`、`5xx`）或等价协议结果；失败机制独立记录 `unexpected_exception`、`timeout`、`client_cancellation`、`php_fatal`、`memory_limit`、`process_signal`、`kernel_oom_kill` 等受控值；发起层记录 `client`、`gateway`、`application`、`dependency`、`kernel` 或 `runtime`，其中 runtime 专指 PHP/runtime 层。预期业务拒绝单独记录为业务结果，不与非预期异常合并。原始 URL 参数、完整请求体、用户标识、token、Cookie 和完整异常文本不得进入指标 label；敏感诊断细节只进入受控日志或 Trace，并执行脱敏和短保留。

若必须生成单一 `primary_failure_class`，只对同一请求按以下优先级归类一次，其他维度仍保留作证据：`kernel_oom_kill` > `process_signal`/`process_crash` > `memory_limit` > `php_fatal` > `timeout` > `client_cancellation` > `unexpected_exception` > `business_rejection` > `success`。只有存在对应证据才使用该级别；同级冲突标记 `unknown_failure` 并保留原始事件，不重复递增错误计数。HTTP status 和发起层永远不参与这条优先级覆盖。

先建立不低于一个正常窗口的基线：每个 operation 的流量、duration 分布、错误分类、并发量、实例数、发布版本和高峰时段。将事故窗口与未受影响实例或旧版本对照；流量结构、重试、租户分布或采集缺失变化都可能伪造“变慢”。

## PHP-FPM 容量与队列

从受保护的 FPM status 端点或等价状态采集，保留采集时间和进程池标识。常见字段及解释如下：

| 信号 | 语义 | 解释边界 |
| --- | --- | --- |
| `active processes` | 当前正在处理请求的 FPM 子进程数 | 瞬时值；必须和流量、请求完成率及采样窗口结合 |
| `idle processes` | 当前空闲、可接收请求的子进程数 | 为零表示没有即时空闲容量，但不等于已经出现用户失败 |
| `total processes` | 当前 FPM 子进程总数 | 通常为 active 与 idle 之和；重载或扩缩容会改变分母 |
| `max children reached` | 进程池曾触及 `pm.max_children` 的累计次数 | counter 需用窗口增量；单次非零不能说明当前仍饱和 |
| `listen queue` | 正在等待 FPM 接收的连接数 | 与队列上限、请求速率、accept 速率和客户端超时关联 |
| `listen queue len` | 监听队列容量 | 需要确认采集含义和单位，不能把长度当作当前排队数 |

### 区分进程池饱和

支持 **`max_children` 饱和** 的证据组合是：`active processes` 长时间接近 `pm.max_children`，`idle processes` 持续为零，`max children reached` 在窗口内增长，并且 request duration、in-flight 或错误/超时同步恶化。若只有累计 `max children reached` 增长而当前 active 已恢复，优先视为过去的瞬时峰值。

支持 **listen queue 饱和** 的证据组合是：`listen queue` 持续接近或达到 `listen queue len`，accept/完成速率跟不上到达速率，入口或上游连接等待、超时增加。队列高而 active 未满，可能是 FPM 接受线程、socket、网络或上游突发问题；不能仅凭队列值认定 `max_children` 不足。

支持 **请求本身变慢导致堆积** 的证据是：active 接近上限前，单请求 `request duration` 已在某个 operation 或依赖阶段升高，slowlog/Trace 显示相同阻塞路径。盲目增加 `pm.max_children` 可能扩大内存和下游压力，应先确认每个子进程的 RSS 与下游容量。

将 FPM 指标与以下信号分段对齐：入口接收时间、排队时间、应用处理时间、响应写出时间和客户端等待时间。若可用，记录 FPM 请求状态、进程 PID、脚本名和最后请求开始时间；这些字段仅用于受控诊断，不进入高基数指标。

## 时间线与日志关联

按 `trace_id`、规范化 `operation`、实例、版本和时间戳建立单一请求时间线。至少尝试关联：

1. 边缘或负载均衡入口事件及客户端结果。
2. PHP-FPM 或 upstream access log 的接收、状态和 `request duration`。
3. 应用结构化日志中的业务结果、异常分类和重试。
4. 慢请求 `slowlog` 的采样/写入时间、PID、脚本和 stack。
5. Trace 中的 server span、数据库/HTTP client span、事件顺序和取消原因。
6. FPM、worker、容器进程状态以及发布或配置变更时间。

时钟不一致时记录时间源、偏差和时间窗口宽度；不要用相邻日志行代替因果关联。相同 `trace_id` 不保证事件严格按写入顺序到达，使用事件时间、span start/end 和采集延迟重建顺序。

upstream access log 的字段命名因部署而异，应明确哪些时间是连接、排队、upstream 处理或响应写出。应用结构化日志应在改变 HTTP 结果或确认业务失败的边界记录一次；不要为同一异常在每层重复告警。日志缺失、采样或异步写入延迟必须作为未知项列出。

## slowlog 与慢请求

`slowlog` 是定位超过 `request_slowlog_timeout` 后采样并写入的 PHP 执行路径证据，不是完整请求延迟的替代品。slowlog 时间戳表示达到阈值后的采样/写入事件，不是可靠的请求开始时间。先确认：slowlog 是否在事故窗口启用、采集文件是否完整、PID 与版本是否匹配、采样是否会丢失、阈值是什么，以及设置本身是否曾被变更。

请求开始时间应来自 upstream access log 的接收字段、Trace server span start、FPM full status（若提供）或其他明确来源。将 slowlog 中的 PID、脚本、调用 stack 和采样/写入时间与这些请求开始时间、upstream access log、`request duration` histogram 和 Trace span 对齐，并注明 slowlog 采集延迟、日志管道延迟和时钟偏差。支持“应用代码或依赖阻塞”的证据包括：同一 operation 在多个实例出现相似 stack，slowlog 持续覆盖高分位请求，且对应 client span、数据库等待或锁等待时间同步增加。

slowlog 没有样本不能证明没有慢请求：窗口可能太短、阈值过高、进程在超时前退出或日志管道丢失。不要在事故中未经授权临时大幅降低阈值或开启高开销追踪；如确需调整，先记录原配置、限定实例和时长，并设置停止条件。

## OPcache 证据

OPcache 状态用于判断代码缓存和共享内存，不直接代表请求逻辑正确性。按版本和实例采集并关联：命中与 miss 计数、命中率、已用/可用内存、缓存脚本数、interned strings、重启次数、`restart_pending`、时间戳校验状态和配置摘要。

解释边界：

- 命中率下降只有在请求 operation、脚本加载时间和发布/配置时间线同步变化时，才支持缓存失效或代码路径变化假设。
- OPcache 内存接近上限、频繁重启或 `restart_pending` 持续存在，可能造成脚本重新编译和延迟抖动；仍需用请求 duration、CPU 和 PHP-FPM 进程证据确认影响。
- 代码发布、文件时间戳校验、预加载、CLI 与 FPM 使用的缓存池可能不同。发布后的自然 miss 或一次受控重启不等于故障。
- OPcache restart 只清理/重建缓存，不等同于 PHP-FPM 子进程重启；两者的 counter、PID 和现场影响不同。

不要以“命中率低”单独解释 5xx、内存增长或队列饱和。比较新旧版本、受影响与未受影响实例，并检查代码文件数量、缓存容量、部署顺序和 `validate_timestamps` 等配置变化。若状态数据不完整，结论应标记为未确认。

## 内存语义与 RSS

请求内存和进程内存回答不同问题，必须分别记录：

| 信号 | 典型来源 | 能回答的问题 | 不能直接回答 |
| --- | --- | --- | --- |
| `memory_get_usage()` | PHP 请求上下文 | 当前脚本分配的 PHP memory | 进程全部 RSS、共享页和扩展分配 |
| `memory_get_usage(true)` | PHP allocator 保留量 | PHP allocator 向进程保留的内存 | 操作系统实际驻留页 |
| `memory_get_peak_usage()` | PHP 运行时峰值 | 短请求可近似请求内峰值；长驻 CLI 默认是进程生命周期累计峰值 | 不能默认当作单个 job 峰值，也不能说明请求结束后是否归还 RSS |
| 进程 RSS（如 `VmRSS`） | OS/process 视图 | FPM 子进程或 CLI worker 的驻留总量 | 哪个请求或对象造成增长 |
| cgroup `memory.current` | cgroup 记账 | cgroup 当前总内存使用量 | 不等于平台派生的 working set，也不能定位单个 PHP 请求 |
| 平台派生 working set | 运行平台指标 | 按平台定义的活跃/工作集口径 | 口径可能排除或包含不同 cache，不能与 `memory.current` 直接互换 |

在受控应用埋点中记录短请求开始/结束的 `memory_get_usage` 和峰值，使用有限 operation、版本和实例维度；原始 PID、trace ID 仅留在受控日志。对长驻 CLI 不把 `memory_get_peak_usage()` 直接当作 per-job 峰值，而记录每个任务前后的 current usage、任务序号、进程年龄和 RSS 斜率。以固定时间间隔分别观察每个 FPM 子进程 RSS、worker 进程 RSS、cgroup `memory.current`、平台 working set 和 OOM 证据，并记录采样时间。

**请求峰值高但 RSS 稳定** 更像单次大请求或正常 allocator 复用；**请求内存回落但 RSS 持续上升** 支持 allocator 碎片、扩展分配、缓存或泄漏假设；**RSS 与 OPcache 共享内存一起上升** 需要区分共享页计入方式。只有跨多个相同流量窗口、请求完成后仍不回落且有对象/扩展证据，才提高泄漏优先级。

不要把一次 RSS 峰值、一次 `memory_get_peak_usage` 或容器 limit 接近直接称为泄漏。比较同版本和相同任务结构的对照进程，观察进程年龄、任务数、请求量和回收后的趋势。PHP 8.2+ 且部署构建明确支持时，可在每个 job 开始边界调用 `memory_reset_peak_usage()` 以重置当前进程的 peak 计数；它不会回收内存，也不提供历史 job 峰值，版本未知或调用边界不受控时不要使用。强制 GC、读取完整 heap dump 或终止进程会改变现场，默认不执行。

## CLI worker 诊断

长驻 CLI worker 需要独立的消费 RED 和存活信号：

- 消费速率、成功/失败/重试 counter，以及当前 in-flight 数和队列 age。
- 任务耗时 histogram（含等待、业务处理和确认/ack 阶段），超时和取消分类。
- heartbeat 发送与响应、最后成功消费时间、worker process age、进程 RSS 和每任务内存增量。
- 重试原因、退避、死信/丢弃、并发上限和下游限流；消息 ID 只进受控日志或 Trace，不进指标 label。

把 worker 的消费速率与生产速率、队列深度、失败/重试和下游 duration 同时画出。消费速率下降而任务耗时和下游等待同步升高，更支持处理路径变慢；速率正常但队列 age 增长，检查生产突发、并发上限或 ack/提交阻塞。heartbeat 丢失不等于进程死亡，需与进程状态、最后任务事件和 broker 证据结合。

对每个 worker 记录启动版本、优雅停止开始/完成、当前任务是否可重试和未确认任务数。安全回收顺序是停止领取新任务、等待可恢复任务到达安全边界、确认或转移未完成任务、验证队列没有异常丢失，再按批准的策略退出。任何 max-tasks 或 max-age 回收都必须有容量、重试和停机窗口依据。

长驻进程 RSS 随处理任务数单调增长、流量结构恢复后仍不回落，并在重复进程或对象 profile 中显示相同保留路径，才支持 worker 泄漏。周期性回收可以是有审批的临时止损，但不能替代修复泄漏、无限重试或阻塞依赖。

## 异常、超时、取消与 fatal error

HTTP/transport 结果、失败机制和发起层/责任边界是三个独立维度。一个请求可以同时得到 HTTP `504`、`timeout` 机制和 `gateway` 发起层，也可能得到 HTTP `500`、`php_fatal` 机制和 `application` 发起层；不要把它们合并成互斥的 HTTP 错误类别。预期业务拒绝单独记录为业务结果，不与非预期异常合并。

### 维度一：transport 结果

记录最终可见的 HTTP status class（`2xx`、`3xx`、`4xx`、`5xx`）或等价协议结果，并保留 status 产生边界。`5xx` 只说明协议结果，不说明是 PHP 代码、依赖、资源还是进程终止；`4xx` 也可能是预期业务拒绝、认证策略或客户端输入错误。网关记录的非标准 status（例如 `499`）应标为平台特定 transport 证据，不改写服务内部机制。

### 维度二：失败机制

| 机制 | PHP/应用证据 | 进程/FPM 证据 | HTTP/upstream 结果边界 |
| --- | --- | --- | --- |
| 预期业务拒绝 | 受控业务结果、业务错误码或验证事件 | 进程正常完成 | 常见为 `4xx`，是否计入 SLO 取决于业务定义 |
| `unexpected_exception` | 未处理异常、异常类、Trace error event、受控 stack | 通常无 child 退出要求 | 可能是 `5xx`，但 status 仍是独立维度 |
| `timeout` | 明确 deadline、阶段耗时、超时发起层 | 进程通常仍存活；需看请求是否被终止 | 可能是 `504`、应用自定义结果或上游连接超时 |
| `client_cancellation` | client span/连接关闭、服务观察到取消的事件 | worker 可能继续或停止当前工作 | 可能没有服务端 status，网关也可能记录 `499` |
| `php_fatal` | PHP `E_ERROR`、`E_PARSE`、`E_COMPILE_ERROR`、`E_CORE_ERROR` 及 PHP error log | 脚本/请求通常终止；不以 FPM child 重启为必要条件 | 可能为 `500`、上游 `502` 或连接中断 |
| `memory_limit` | PHP error log 中 `Allowed memory size ... exhausted` 等记录 | 当前脚本通常终止；不能据此推断 child 退出 | 可能为 `500`、上游 `502` 或客户端超时 |
| `process_signal`/`process_crash` | 可能没有 PHP 应用日志 | FPM child exit code、signal、master 日志和 PID 生命周期 | 常见为 `502`、连接重置或上游 timeout，需以 access log 为准 |
| `kernel_oom_kill` | 可能没有 PHP fatal 日志 | child 被外部终止，可能伴随重启；不是 PHP fatal 证据 | 可能为 `502`、`504`、连接中断或客户端取消 |

### 维度三：发起层与责任边界

对 timeout、cancellation 和 termination 记录谁先发起或观察到事件：`client`、`gateway`、`application`、`dependency`、`runtime` 或 `kernel`。按 Trace span start/end、deadline 设置点、连接关闭事件、下游是否继续执行、FPM child 生命周期和 kernel/cgroup 事件重建顺序。只有在发起层和时间顺序都有证据时，才把责任边界写入结论；单条 `context canceled` 或 HTTP `5xx` 文本不足以归因。

### PHP fatal、进程终止与 OOM 的分离

以下四类必须分别核对，禁止用 worker restart 替代 PHP fatal 证据：

1. **PHP-level fatal**：查 PHP error log/stderr、异常类型、脚本和 Trace；FPM child 可以继续存活，`total processes` 下降不是必要证据。
2. **PHP `memory_limit` exhaustion**：查 PHP error log 的 `Allowed memory size` 文本、请求/任务和 `memory_get_usage`；它通常终止当前脚本，但不等同于 OS/cgroup OOM，也不能仅凭该日志推断 child 退出。
3. **process crash/signal**：查 FPM child exit code、signal、master 日志、PID 生命周期和 core/crash 记录；可能没有 PHP error log，且不必然是 OOM。
4. **kernel/cgroup OOM kill**：查 kernel 日志、cgroup `memory.events`/`oom_kill`、平台 termination reason（如 `OOMKilled`）和 child signal；可能没有 PHP fatal 日志，不能由应用 `memory_limit` 文本替代。

超时诊断按事件顺序回答：谁设置 deadline、谁先观察到超时、各阶段实际耗时、请求取消后下游是否仍运行。客户端先断开时，后续服务日志可能只看到 cancellation；服务先超时后客户端断开时，时间线相反。将 PHP error log、FPM child 退出/信号、kernel/cgroup termination reason 与 HTTP/upstream 结果分别列出，避免同一请求重复计数。

将 PHP error_reporting 与生产日志策略的变更记录纳入时间线，避免把日志级别变化误判为错误率变化。worker restart 只能作为进程生命周期和恢复动作证据，不能反向证明发生了 PHP fatal、`memory_limit` exhaustion 或 kernel OOM。

## 证据驱动的诊断顺序

按用户影响和风险排序，每一步记录支持证据、反证、未知项和下一检查：

1. 确定受影响 operation、时间窗口、SLO、版本、租户范围和成功恢复标准。
2. 对齐 request rate、error、`request duration`、in-flight 与客户端结果，确认影响是否真实且持续。
3. 检查 FPM `active processes`、`idle processes`、`total processes`、`max children reached` 增量和 `listen queue` 趋势。
4. 将 upstream access log、应用结构化日志、Trace 和 `slowlog` 按 PID/`trace_id`/时间线关联。
5. 检查 OPcache 命中、内存、重启和发布变化，区分缓存事件与请求逻辑故障。
6. 分别比较 `memory_get_usage`/峰值、FPM/worker RSS、cgroup `memory.current`、平台 working set 和进程年龄，验证泄漏或 allocator 假设。
7. 对 CLI worker 检查消费速率、任务耗时、失败/重试、heartbeat、队列 age 和每任务 RSS 斜率。
8. 分开记录 HTTP status、失败机制、发起层/责任边界；按唯一优先级生成 `primary_failure_class`，再核对 PHP fatal、`memory_limit`、process signal/crash 和 kernel/cgroup OOM 证据。
9. 只在已有明确假设时提出低风险止损；保留现场并设定停止、回滚和观察窗口。

常见误判包括：把累计 `max children reached` 当作当前饱和，把 listen queue 高归因于 `max_children`，把 5xx 全部归因于下游，把 RSS 高等同于 PHP 请求泄漏，把低 OPcache 命中率等同于代码错误，把客户端取消计为服务端错误，以及用周期性重启掩盖长驻 worker 泄漏。

## Kubernetes 运行证据

Kubernetes 仅作为运行环境证据：读取 Pod phase、restart count、termination reason、`OOMKilled`、CPU throttling、resource request/limit、节点迁移、版本分布和容器启动时间，并与 PHP-FPM/worker 信号按时间对齐。

这些证据可以支持“进程因 OOM 退出”“实例被 CPU limit 限流”“新版本集中异常”或“重启造成 counter 重置”等假设，但不能单独证明用户影响、FPM 饱和、OPcache 故障、PHP 泄漏或下游变慢。不要把集群控制面、调度器、网络插件或扩缩容操作作为本参考的默认诊断步骤；线上默认只读。

## 止损、回收与重启边界

默认先保留以下现场：时间线、实例/版本清单、FPM 状态快照、队列和 worker 统计、关键日志索引、Trace 采样、OPcache 状态、RSS/工作集趋势以及发布/配置变更记录。现场应脱敏、加密传输、限制访问并设置保留期限。

优先考虑不改变现场的止损，例如限制高成本 operation、暂停有明确重试风险的生产、降低并发或流量分配；每项都要写明审批、影响范围、停止条件、回滚条件和恢复验证信号。扩容或提高 `pm.max_children` 不是无条件修复：先估算单进程 RSS、容器 limit、下游容量和队列增长。

重启不是默认诊断方法。只有在用户影响持续、现场已充分保留、替代止损不足且获得显式授权时，才考虑平滑重载或 worker 回收；记录目标实例、原因、预期影响和回滚步骤。重启后验证错误率、`request duration`、FPM queue、worker 消费速率、RSS 和下游信号持续恢复，并将其标记为症状缓解，不能据此宣布根因修复。

对疑似泄漏的 CLI worker，优先使用可审计的优雅 drain 和有界回收策略；不要通过无证据的周期重启掩盖泄漏、阻塞或无限重试。任何 kill、清队列、重放消息或修改 OPcache/FPM 参数都必须单独审批，并在操作前记录可恢复的状态。

## 结论检查清单

- 是否先界定用户影响、operation、时间窗口、基线、版本和恢复标准？
- 是否同时使用 request rate、error、`request duration`、in-flight 与客户端结果？
- 是否用 active/idle/total、`max children reached` 增量和 listen queue 区分 FPM `max_children` 与队列饱和？
- slowlog、upstream access log、应用结构化日志和 Trace 是否按 PID、`trace_id`、版本和事件时间关联？
- OPcache 命中、重启、内存和代码发布变化是否有时间与实例对照，且未被单一命中率替代？
- 是否明确区分 `memory_get_usage`、短请求峰值、CLI 进程生命周期 peak、进程 RSS、cgroup `memory.current`、平台 working set 和长驻进程趋势？
- CLI worker 是否覆盖消费速率、失败/重试、heartbeat、任务耗时、队列 age 和安全回收？
- 是否将 HTTP/transport 结果、失败机制、发起层/责任边界分开，并用唯一优先级避免同一请求重复计数？
- 是否分别核对 PHP-level fatal、`memory_limit` exhaustion、process crash/signal 和 kernel/cgroup OOM kill，且没有用 worker restart 替代 fatal 证据？
- slowlog 时间是否标为阈值后的采样/写入时间，并用 access log、Trace server span 或 FPM full status 获取请求开始，同时注明阈值、采集延迟和时钟偏差？
- 是否避免把 Kubernetes 状态、单次峰值或一次重启直接当作根因？
- 线上操作是否默认只读，且所有变更均保留现场、获得授权并写明停止与回滚条件？
