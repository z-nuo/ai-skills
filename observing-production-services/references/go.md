# Go 生产服务诊断

本参考用于诊断 Go HTTP/gRPC 服务的延迟、错误、资源饱和和等待问题。先界定用户影响、时间窗口和正常基线，再按时间、版本、operation 与实例关联请求、runtime、连接池、下游和 profile 证据。任何单一指标只用于提出假设，不能直接宣布根因。

线上诊断默认只读。修改采样率、启用 profile、暴露 debug endpoint、重启实例、调整连接池或强制 GC 都属于有运行风险的变更，必须取得显式授权，并写明审批、停止条件和恢复方式。

## 目录

1. HTTP/gRPC 请求信号
2. runtime/metrics 关联
3. 区分常见假设
4. 延迟升高的证据顺序
5. pprof 选择与采集
6. debug endpoint 安全边界
7. Kubernetes 运行证据
8. 结论检查清单

## HTTP/gRPC 请求信号

入口和出站调用都使用 RED，并让指标、日志与 Trace 采用相同的规范化 operation。至少覆盖：

- **request rate**：请求或 RPC 数量的 counter rate；按有限基数的 service、operation、protocol 和 status/error class 聚合。
- **error**：区分调用方拒绝、业务拒绝、服务端故障、下游故障、超时和取消。HTTP 使用 status code class 或受控 outcome；gRPC 使用规范 status code，例如 `CANCELLED`、`DEADLINE_EXCEEDED`、`UNAVAILABLE` 和 `INTERNAL`。
- **duration**：使用 histogram 记录入口总时长和出站调用时长，bucket 对齐 SLO 阈值；从兼容 bucket 的分布比较 p50/p95/p99 与正常基线，并按实例和版本下钻。不要平均实例分位数，也不要把平均值或单一预计算 percentile 直接当作根因。
- **in-flight**：当前正在处理的请求或 RPC gauge。它用于判断并发堆积，必须与 request rate、完成率和容量上限一起解释。

HTTP route template 和 gRPC full method 可以作为 operation；原始 URL、request ID、trace ID、user ID、错误全文不得成为指标 label。诊断细节进入受控日志或 Trace，并通过 `trace_id` 与请求指标的时间窗口、operation 和版本关联。

同时观察入口与出站边界。入口延迟升高而出站 span 正常，优先检查服务内部排队、GC、锁和本地计算；出站 duration 同步升高时，再区分连接池等待、网络阶段和下游服务时间。入口 error 不能自动归因于下游，必须找到对应调用结果或 Trace。

## runtime/metrics 关联

使用标准库 `runtime/metrics` 读取 Go runtime 信号。具体指标集合和 histogram bucket 会随 Go 版本变化；先通过 `metrics.All()` 或部署版本的官方文档确认名称、类型和单位，再编写查询。不要假设不同 Go 版本的全部序列都存在或含义完全相同。

| 诊断问题 | 代表性 runtime 信号 | 必须关联的证据 |
| --- | --- | --- |
| goroutine 是否堆积 | `/sched/goroutines:goroutines`、`/sched/latencies:seconds` | in-flight、request rate、队列/连接池等待、重复 goroutine stack |
| heap 是否承压 | `/memory/classes/heap/objects:bytes`、`/memory/classes/heap/free:bytes`、`/gc/heap/live:bytes` | allocation rate、GC cycle、容器/进程 RSS、流量和版本 |
| allocation 是否突增 | `/gc/heap/allocs:bytes`、`/gc/heap/allocs:objects` 的窗口增量 | operation 流量、CPU、heap profile 的 alloc/in-use 视图 |
| GC 是否影响请求 | `/gc/cycles/total:gc-cycles`、`/gc/pauses:seconds`、`/cpu/classes/gc/total:cpu-seconds` | 请求延迟分布、scheduler latency、allocation 和 live heap |
| CPU 时间花在哪里 | `/cpu/classes/user:cpu-seconds`、`/cpu/classes/gc/total:cpu-seconds`、进程 CPU | request rate、throttling 运行证据和 CPU profile |
| scheduler 是否有等待 | `/sched/latencies:seconds`、goroutine 数 | 进程 CPU、可运行 goroutine、锁/阻塞 profile 和实例对比 |
| mutex 等待是否增加 | `/sync/mutex/wait/total:seconds` 的窗口增量 | mutex profile、热点 stack、请求延迟和并发量 |

counter 必须使用窗口增量或 rate，不能比较进程重启前后的原始累计值。histogram 观察分布和高位 bucket，并确认采集端没有错误转换单位。gauge 的单次峰值也不能代表持续饱和。

heap 需要区分 Go heap、runtime 保留内存、进程 RSS 和容器工作集。GC 后 live heap 持续增长比单次 heap 峰值更支持保留对象假设，但仍需 heap profile 的对象类型、分配路径和跨时间对比才能定位。RSS 增长还可能来自 goroutine stack、cgo、mmap 或未及时归还的页。

判断 GC 是否影响请求，不要求 allocation rate 或 live heap 必须上升。应综合比较 allocation/live heap、`GOGC`/Go memory limit 等 GC 配置、CPU 供给/throttling 与 GC cycle/CPU/pause 相对基线的变化，并用时间和实例对照验证 GC 信号与请求延迟是否相关。只有一次 GC pause、CPU spike 或 heap 高点，不足以证明 GC 是根因。

## 区分常见假设

### GC 压力

支持证据可以来自 allocation/live heap、`GOGC`/Go memory limit 配置或 CPU 供给/throttling 中任一驱动因素相对基线的变化，以及 GC cycle 变密、GC CPU 占比或 pause 分布恶化；这些信号需要与请求 duration、scheduler latency 形成时间相关和实例对照。CPU profile 或 heap allocation profile 可进一步指出 CPU 或分配路径。allocation/live heap 稳定不能单独排除 GC；只有候选驱动因素与 GC cycle/CPU/pause 相对基线均稳定，且时间和实例对照都不支持关联时，才降低 GC 假设优先级。

### goroutine 堆积与泄漏

goroutine 堆积通常随 in-flight、流量、队列或连接池 waiters 增长，并在压力解除后排空。goroutine 泄漏更支持以下模式：流量和 in-flight 回到基线后，goroutine 数仍跨多个观察窗口单调增长；重复采集的 goroutine profile 显示同一组 stack 持续累积；这些 stack 无法由稳定的长连接、后台 worker 或预期并发解释。

单次 goroutine 数量高不能区分正常并发、短时堆积和泄漏。必须比较相同流量与配置下的基线，并按 stack 聚类；仅凭 goroutine profile 的一个快照也不能证明泄漏。

### 锁竞争

锁竞争需要 mutex wait 增量、mutex profile 热点或 block profile 中同步等待 stack 的支持，并与并发量和请求延迟相关。CPU 可能不高，因为 goroutine 在等待；scheduler latency 上升也可能来自 CPU 饱和或 throttling，不能替代锁证据。

如果热点只出现在短暂发布启动阶段，或 profile 采样量过低，应延长观察窗口或使用实例对照，而不是直接把某个函数名定为根因。

### 连接池等待

为数据库、缓存和 HTTP/gRPC client 连接池分别观察：池上限、in-use、idle、等待者、获取连接 duration、获取 timeout、连接创建失败和复用率。连接池等待的强证据是池接近上限、waiters 与 acquisition duration 同步上升，且 goroutine stack 停在获取连接路径。

连接池饱和不等于下游一定变慢。它也可能由流量突增、连接泄漏、过长事务、重试放大或池配置变小造成。把“获取连接等待”与“取得连接后的下游 duration”分段测量；前者高而后者稳定更支持本地池容量或持有时间问题，二者都高则继续检查下游。

### context deadline 与 cancellation

分别计数 `context deadline exceeded`、`context canceled`，以及 gRPC `DEADLINE_EXCEEDED`、`CANCELLED`；记录规范化 operation、发起边界、剩余 deadline bucket 和有限 error class。不要把原始错误文本或 deadline 时间戳放入指标 label。

deadline 证据需要说明谁设置截止时间、哪一层首先观察到超时、各阶段耗时之和以及下游是否仍在执行。父 context 取消、客户端断开和服务主动止损可能产生 cancellation；它们不是同一根因。通过 Trace 的 client/server span、事件顺序和日志中的因果字段区分“调用方先取消”与“服务或下游先超时”。

### 下游延迟

下游假设需要对应 client span 或依赖指标显示 connect、TLS、请求写入、server processing、响应读取中的一个阶段变慢，并有对端或跨实例证据支持。只有本服务看到 `context deadline`，无法证明下游慢；本地连接池等待、DNS、网络、重试和过短 deadline 都可能产生相同表象。

优先比较同一时间窗口内多个调用方、多个下游实例和未受影响版本。若对端 server span 正常而 client span 变慢，检查网络阶段、连接复用和客户端本地等待；若对端 span 同步变慢，再检查对端 RED、资源和发布证据。

## 延迟升高的证据顺序

按以下顺序缩小范围，每一步都记录支持证据、反证和下一检查：

1. **发布变化**：标出代码版本、配置、Go 版本、依赖和 feature flag 的变更时间；比较新旧版本或受影响与未受影响实例。时间接近只建立相关性。
2. **流量与错误**：确认用户影响、operation、request rate、status/error class、duration 分布和 in-flight。先排除流量结构变化、重试放大和 telemetry 缺失。
3. **runtime**：关联 goroutine、heap、allocation、GC pause/CPU、进程 CPU 和 scheduler 信号。寻找持续趋势与实例对照，不从单次峰值定因。
4. **pool**：检查连接池 in-use/limit、idle、waiters、acquisition duration 和 timeout，并把池等待与实际下游调用分段。
5. **下游**：检查 client/server span、依赖 RED、网络阶段、deadline/cancellation 顺序，以及多个调用方是否同时受影响。
6. **profile**：当已有明确假设、目标实例和时间窗口时，选择最低开销的 pprof profile 验证代码路径；profile 是验证证据，不是第一步搜索工具。

当证据不一致时保留多个假设。例如 goroutine 与延迟同时上升，既可能是下游变慢导致堆积，也可能是泄漏增加资源压力；下一步应使用 stack 分组、in-flight、pool wait 和下游 span 区分，而不是选择最显眼的曲线。

## pprof 选择与采集

| profile | 适用问题 | 解释边界与风险 |
| --- | --- | --- |
| CPU | CPU 时间热点、忙循环、序列化或分配路径 | 只采样 on-CPU 工作，不能解释锁、网络或连接池的 off-CPU 等待 |
| heap | 当前保留对象和累计 allocation 热点 | 区分 in-use 与 alloc 视图；单次快照不能证明泄漏，强制 GC 会改变现场 |
| goroutine | 阻塞位置、等待模式和 stack 分组 | 瞬时快照；需与基线、in-flight 和重复采集对比，输出可能含敏感参数或标签 |
| mutex | 已发生的锁竞争和持锁热点 | 依赖 mutex profile 采样配置；启用或提高采样率会增加开销 |
| block | channel、select、锁等阻塞热点 | 依赖 block profile 采样配置；不代表当前所有阻塞，启用属于运行变更 |

采集遵循最低开销原则：先确定一个异常实例和一个对照实例，选择能验证当前假设的 profile；使用最短但足以覆盖症状的限时窗口；避免并发采集多个 CPU profile、无限重复下载或在所有实例同时启用高采样率。记录实例、版本、开始/结束时间、采样配置、流量和文件校验信息。

默认不为获得“更干净”的 heap 快照而强制 GC，不在未授权时调用 `runtime.GC()`、修改 `runtime.SetBlockProfileRate` 或 `runtime.SetMutexProfileFraction`。`runtime.SetMutexProfileFraction` 返回旧采样率，但 `runtime.SetBlockProfileRate` 不返回旧值且无 getter；调整 block rate 前，必须从受控配置或应用自维护状态记录可信原值，原值不明时不得临时调整。确需调整采样时，先评估 CPU/内存开销，限定实例与持续时间，观察停止条件，并在窗口结束后恢复已记录的原配置。

mutex/block 采样只影响调整后的事件，不能回溯事故窗口。短暂采集未出现热点可能只是采样窗口未覆盖争用或样本不足；低样本不能作为否定结论，必须结合 runtime wait 指标、请求延迟、并发量和已有基线判断。

profile 只能回答采样窗口内“时间或对象主要落在哪些 stack”。它仍需与请求、runtime、连接池和下游时间线关联。函数在 profile 中占比高可能只是高流量路径，并不自动表示缺陷。

## debug endpoint 安全边界

`/debug/pprof/` 及其他 debug endpoint 不得直接暴露到公网或普通业务入口。即使只读，它们也可能泄露函数名、请求标签、内存内容线索和服务拓扑，并可能通过长时间 profile 消耗资源。

任何访问必须同时满足：

- **认证与授权**：使用强身份认证和最小权限授权，例如受控管理身份或双向 TLS；共享 URL、仅靠隐藏路径或匿名集群内访问不算认证。
- **网络隔离**：仅允许专用管理面、受限跳板路径或临时端口转发访问；默认拒绝公共 ingress、跨租户和不必要的 namespace/网段。
- **审计**：记录申请人、审批人、访问者、目标 service/instance、endpoint、参数、开始/结束时间和结果；审计记录不得包含 profile 内容或凭证。
- **限时暴露**：优先使用按需、自动过期的访问窗口，设置 profile duration、并发和响应大小上限；窗口结束后自动关闭路由或权限并验证不可访问。

profile 与 dump 视为敏感诊断产物：加密传输和存储，限制下载者，设置短保留期，到期删除并记录审计。不要在工单、聊天或普通日志中粘贴原始 goroutine dump、heap 内容、token、Cookie 或个人数据。

临时开放 endpoint、扩大网络策略或提高采样率不是默认只读操作，必须显式授权并保留回滚步骤。若不能满足认证、网络隔离、审计和限时暴露，则不采集 endpoint 数据，改用已有指标、Trace 和受控日志形成下一步。

## Kubernetes 运行证据

Kubernetes 仅作为运行证据来源：读取 Pod 状态、restart count、termination reason、OOMKilled、CPU throttling、resource request/limit、节点迁移和版本分布，并与服务信号按时间对齐。它可以支持“实例被限流”“进程重启导致 counter 重置”或“新版本集中异常”等假设。

Pod restart、OOMKilled 或 CPU limit 本身不能证明 HTTP/gRPC 用户影响，也不能单独解释 GC、goroutine、锁、连接池或下游延迟。不要把 Kubernetes 控制面、调度或网络运维操作作为本参考的默认诊断步骤；线上默认只读。

## 结论检查清单

- 是否先界定用户影响、operation、时间窗口、基线和版本，而不是从 runtime 图开始？
- HTTP/gRPC 是否同时覆盖 request rate、error、duration、in-flight 与有限 status/error class？
- runtime/metrics 的名称、类型、单位和 Go 版本是否已确认，counter 是否按窗口计算？
- GC 结论是否同时有 allocation/live heap、cycle/CPU/pause 与请求延迟的时间关联？
- goroutine 堆积或泄漏是否由压力解除后的趋势、重复 stack 和预期并发反证区分？
- 锁竞争、连接池等待、context deadline/cancellation 和下游延迟是否有各自边界证据？
- profile 是否针对已有假设、限定实例与时长，并说明采样盲区和开销？
- pprof/debug endpoint 是否具备认证、网络隔离、审计和限时暴露，诊断产物是否受控？
- Kubernetes 是否只作为运行证据，而未被当作服务根因？
- 是否避免从单次 heap、goroutine 数或 CPU spike 直接宣布根因，并记录反证与未知项？
