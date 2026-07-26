# PHP 性能诊断与优化

## 目录

- 证据路径与安全边界
- PHP-FPM 饱和信号
- Worker 容量与内存预算
- CPU profile 与热点定位
- PHP 内存、allocator 与 RSS
- OPcache、发布与预加载
- 应用热路径与连接容量
- CLI worker 生命周期
- PHP-FPM 饱和证据顺序
- CLI worker 内存增长证据顺序
- 同条件复验与回滚清单

## 证据路径与安全边界

先按核心方法论固定性能目标、业务不变量、代表性 workload、环境、运行阶段和停止条件，再选择 PHP 工具。PHP runtime 指标只能解释其观测边界，不能单独证明用户可见延迟的根因。

1. 从 p50/p95/p99、正确完成吞吐、错误、timeout、cancel 和 retry amplification 确认差距，并按 endpoint、任务类型与 outcome 分层。
2. 固定代码、PHP 版本、扩展、`php.ini`、FPM pool 配置、容器 CPU/内存限制、实例数、流量分布、payload、缓存状态和下游条件。
3. 对齐 proxy、PHP-FPM、应用、数据库、Redis 和外部服务时间线，先区分排队、on-CPU、应用等待和下游等待。
4. 优先读取已有指标、trace、日志、FPM status、历史 profile 和部署记录。主动 profiling 只补当前可证伪假设缺少的证据。
5. 一次改变一个关键变量，在同一 workload、预热判据、offered load、运行时长或操作数、采样方式下复验，并同时检查正确性和资源边界。

生产环境的在线 profiling 与核心技能使用同等安全边界：只有获得针对动作、实例、时长和数据范围的**显式授权**后，才能限实例、限时、限采样率采集。采集前定义 CPU、内存、延迟和错误率停止阈值；采集产物限制访问与保留时间，并保护函数名、文件路径、参数、URL、SQL、token、用户标识和业务数据。未授权时停在只读证据与采集建议。

不得在生产环境用 Xdebug 等高开销 instrumentation 得出性能结论。Xdebug 可改变函数调用、分配、I/O 和时序；即使在本地或测试环境使用，也要把观测开销写入结论，并用低开销采样或无 profiler 的端到端复验确认。不得通过关闭鉴权、异常处理、timeout、持久化或数据校验制造更好数字。

## PHP-FPM 饱和信号

### 先区分进程状态与请求阶段

从受访问控制的现有 FPM status 端点读取同一 pool 的计数和时间序列。status 可能暴露 URI、脚本和进程信息，只允许从管理网络读取，不为诊断临时公开到公网。

| 信号 | 能回答的问题 | 不能单独证明 |
|---|---|---|
| `active processes` | 当前正在处理请求的进程数 | 进程正在使用 CPU，还是等待下游 |
| `idle processes` | 当前可立即接单的进程数 | idle 多就一定容量充足；突发、调度和多 pool 仍可能排队 |
| `total processes` | 当前 pool worker 总数 | 所有进程 RSS 都是独占内存 |
| `pm.max_children` | pool 的并发处理硬上限 | 增大后吞吐一定提升 |
| `listen queue` | 当前等待可用 worker 的 FastCGI 请求数 | 排队由慢 PHP 代码还是慢依赖造成 |
| `max listen queue` | pool/master 启动或状态重置以来的历史队列峰值 | 当前观察窗口的峰值、持续时间及用户影响 |
| `listen queue len` | socket backlog 容量 | 队列未满就没有尾延迟 |
| `max children reached` | worker 上限被触达的累计次数 | 当前仍处于饱和；counter 需看时间窗口增量 |
| `request duration` | full status 中当前或最近请求的处理时长 | proxy 排队、网络时间或完整用户延迟 |

`max listen queue` 不是当前窗口 gauge。记录 pool/master 启动、reload/restart 和任何状态重置边界；用外部时间序列按固定间隔采样 `listen queue`，在对齐窗口内计算当前窗口峰值，并标注 scrape 缺口。`max children reached` 等累计 counter 按 restart-aware 规则转换为窗口增量，遇到进程代际或状态重置时另起序列，不能保留负增量。

`active = total`、`idle = 0` 只表示没有空闲 worker；同时出现持续 `listen queue > 0`、`max children reached` 增量、proxy upstream 延迟上升和目标路径 p99 恶化，才构成 pool 饱和的强证据。瞬时峰值需要结合采样间隔和持续时间，不能用一次快照外推。

### 对齐端到端时间

将一个请求的时间按可观察边界拆分：

```text
client duration
  ~= ingress/proxy queue and transfer
   + FastCGI listen queue wait
   + PHP request duration
   + response transfer

PHP request duration
  ~= on-CPU PHP/runtime work
   + database/Redis/external-service wait
   + filesystem/socket/lock wait
```

字段定义和计时起止点必须从当前 proxy、FPM 版本及 telemetry 配置确认。若 proxy 只有总 upstream wait，而 FPM 只有粗粒度 `request duration`，差值只能形成假设，不能被命名为精确 queue wait。优先用跨层 trace 或共享 request ID 对齐同一请求；无法对齐时按相同时间桶和 endpoint 分层，并记录归因不确定性。

同时检查主机或容器的 CPU utilization、CPU throttling、run queue、memory working set、memory pressure、OOM/kill、swap 和 I/O wait。CPU limit 下的 throttling 可能让所有 worker 变慢；内存压力或 swap 可能增加 `request duration`。扩大 `pm.max_children` 不会增加 CPU 配额，反而可能增加调度、内存和下游竞争。

### 解释 process manager 配置

- `pm = static`：进程数固定为 `pm.max_children`，适合稳定容量，但必须为全部 worker 预留内存和连接预算。
- `pm = dynamic`：`pm.start_servers`、`pm.min_spare_servers`、`pm.max_spare_servers` 控制空闲容量；启动不足可能放大突发排队，空闲过多会占用内存和持久连接。
- `pm = ondemand`：按需启动并由 `pm.process_idle_timeout` 回收；需要单独测量冷启动、OPcache/page cache 状态和突发恢复，不能只比较稳态。
- `pm.max_requests`：让 worker 处理一定请求后回收，可限制碎片或泄漏的长期影响，但回收会产生启动成本。它是隔离或缓解手段，不是内存增长的根因修复。

参数修改前写明所针对的信号、预期可区分结果、资源停止阈值和回滚值。若调整多个 process manager 参数才能形成有效配置，把它声明为一个组合候选，并通过可行的 ablation 区分容量与启动策略的贡献。

## Worker 容量与内存预算

`memory_limit` 不是 worker 的 RSS 上限。它约束 PHP 可计量的请求内存，但不完整覆盖 Zend/PHP allocator 保留页、扩展原生分配、线程或库、映射文件、共享 OPcache、进程代码页和内核 socket buffer。容量预算必须来自目标版本、同一 workload、同一运行阶段下的实际进程分布。

### 建立分布而不是取一个样本

1. 在 warm-up 后和稳态/峰值窗口内，按固定间隔记录每个 worker 的 PID、状态、请求类型、存活时间、处理请求数、RSS、USS/private dirty；Linux 可在获准的诊断环境读取 `/proc/<pid>/status` 或 `smaps_rollup`。
2. 将 busy 与 idle、不同 endpoint 或任务类型、不同进程年龄分层，报告 RSS 和 USS/private dirty 高分位、最大值及样本数；同时记录 pool worker 数与 cgroup working set。
3. 容量计算只选择下面一个一致模型。PSS 仅用于解释共享比例或汇总同一受控 worker 数下的 pool 总量；不得把单 worker PSS 外推到不同 worker 数。
4. 观察 workload 结束后 RSS 是否回落、是否随请求数或进程年龄单调增长，以及新 worker 是否重现相同轨迹。
5. 把峰值 payload、扩展原生内存、部署时短时双版本、sidecar 和监控开销纳入安全余量。

两个模型互斥使用，不能从各自挑选保守项后相加：

**模型 A：固定成本加 worker 私有内存。** 只计一次固定共享/非 worker 成本，包括 OS/runtime reserve、FPM master、sidecar、共享映射与 OPcache；worker 项使用同 workload 和阶段下保守的 USS/private dirty 高分位：

```text
candidate total memory
  = fixed shared/non-worker cost
  + candidate workers * conservative worker USS/private-dirty percentile
  + transient/deployment safety margin
```

由 enforced host/cgroup limit 减去固定成本和安全余量，再除以 worker 私有内存预算，可得到 memory-bound 上界。不得再次扣除共享映射，也不得把 `memory_limit` 直接代入 worker 项。

**模型 B：cgroup 总内存边际增长。** 在受控 worker 数 `N1`、`N2` 及更多校验点上固定 workload、阶段和并发，测量稳态与峰值 cgroup working set，用 `delta(memory) / delta(workers)` 估计区间内边际成本，并用实测 candidate worker 数验证总量和安全余量。不得外推到未覆盖的 worker 数、流量或生命周期阶段。

若使用 pool 内 PSS 总和，它已经按观测进程分摊共享页，只能与同一 worker 数和同一时点的 cgroup 总量交叉检查；不得再次扣除共享映射，也不得用单 worker PSS 乘以新的 worker 数。两个模型都是容量上界而非建议值，还必须通过 CPU 和下游容量约束。

### 同时满足三类容量约束

- **内存**：候选 `pm.max_children` 在峰值分布和安全余量下不触发 memory pressure、swap 或 OOM，部署重叠期也有余量。
- **CPU**：目标负载下没有持续 CPU saturation/throttling。CPU 已饱和时增加 worker 通常只增加竞争和尾延迟。
- **下游连接**：按最坏和稳态两套模型计算 `实例数 x 每实例并发 worker x 每 worker 可能持有连接数`，加上 CLI worker、后台任务、管理连接和故障切换余量，不超过数据库、Redis、proxy 和外部服务的安全容量。

PHP-FPM 的持久连接通常属于单个 worker，不能假设跨进程共享连接池。先确认驱动、框架和连接复用实现：一个请求是否会同时持有主库、从库、Redis 和 HTTP 连接；连接在请求后关闭、归还还是随进程保留。扩大 worker 或实例数前，检查下游连接上限、排队、timeout、限流、错误率和 failover 容量，并与下游 owner 确认可用预算。

最终候选是内存、CPU、下游连接和正确性约束的交集。任何一项没有证据，就只报告条件性上限，不直接修改生产参数。

## CPU profile 与热点定位

CPU 高时先判断是容器被 throttling、主机争用，还是 PHP worker 真正在 on-CPU。CPU 低但请求慢时，优先检查数据库、Redis、DNS、网络、文件系统和连接池等待；CPU profile 不会显示阻塞等待的完整持续时间。

在本地或隔离测试环境，优先使用项目已经部署、开销已知的低开销 sampling profiler。采集使用代表性 workload，并记录 PHP/扩展版本、采样频率、持续时间、目标 PID/pool、是否包含 warm-up 和 profiler 自身开销。系统采样器只能在平台、符号和栈展开条件满足时使用；只看到 PHP VM/native frame 时，不能虚构 PHP 函数级归因。

示例命令仅适用于已授权且支持 `perf` 的 Linux 测试环境：

```bash
perf record -F 49 -p "$PHP_WORKER_PID" -g -- sleep 30
perf report
```

运行前必须把 `PHP_WORKER_PID` 解析为明确的非空单个 worker PID，不把未解析变量或宽泛进程匹配用于采集。生产在线采样还需满足本节开头的显式授权、实例、时长、频率、停止阈值和敏感数据要求；示例命令本身不构成执行授权。

解释 CPU profile 时：

- 同时报 inclusive 与 exclusive/self cost；调用者成本高不等于函数体本身慢。
- 按 endpoint、任务类型和阶段拆分，避免高频轻请求淹没低频慢路径。
- 将 profile 样本比例与进程 CPU-seconds、完成工作数对账；样本比例不是毫秒，也不能直接等于端到端收益。
- 对序列化、autoload、正则、模板或用户代码热点提出单一变量实验，并用端到端指标确认。
- profile 无明确热点时，检查是否主要在 I/O、锁、下游等待或采样无法展开的 native/JIT frame，而不是盲目微调语法。

Xdebug、逐函数 tracing 或全请求参数捕获只能在隔离环境用于定位和功能理解；它们的结果不能作为生产性能基线或收益结论。

## PHP 内存、allocator 与 RSS

以下指标回答不同问题，必须并列解释：

| 指标 | 含义 | 主要限制 |
|---|---|---|
| `memory_get_usage(false)` | 当前 PHP 代码请求自 Zend Memory Manager 获得的内存 | 不代表 allocator 向 OS 保留的全部内存，也不含所有原生扩展分配 |
| `memory_get_usage(true)` | PHP allocator 当前向系统取得的内存块规模 | 可能包含保留但暂未使用的页，仍不等于进程 RSS |
| `memory_get_peak_usage(false)` | 当前 PHP 执行生命周期的已用内存峰值；FPM 通常按请求，CLI worker 按进程 | 长驻 worker 中会保留历史高水位，不能自动代表单个任务 |
| `memory_get_peak_usage(true)` | 当前执行生命周期内 allocator 获取内存的峰值 | 不能覆盖所有 native allocation，也不能与 RSS 直接相减归因 |
| 进程 RSS | 当前驻留物理页，含 private 与 shared 页 | 跨进程相加会重复计算共享页，且不能说明由哪个 PHP 对象持有 |
| PSS/cgroup working set | 按比例分摊共享页或容器实际内存压力 | 采集成本、内核定义和 cgroup 计费口径需记录 |
| OPcache shared memory | FPM 进程共享的 opcode、interned strings 等缓存 | 不应按每个 worker 完整重复计费，仍会计入主机或 cgroup 总预算 |

请求模型下，FPM worker 在请求结束时释放请求级变量，但 allocator 可能保留 arena，扩展可能有 process-level cache，碎片也可能让 RSS 不立即下降。因此“请求结束后 RSS 未回到启动值”不自动证明泄漏。比较同类型请求重复执行后的平台期、增长斜率、对象/扩展证据和 worker 回收后的重现性。

长驻 CLI worker 的生命周期不同：service container、静态变量、全局 cache、闭包、事件 listener、ORM identity map、批量结果和扩展资源可能跨任务保留。`memory_get_peak_usage` 是生命周期高水位；需要单任务峰值时，仅在当前 PHP 版本支持且语义已验证的隔离环境考虑 `memory_reset_peak_usage()`，并仍以任务边界 RSS、PHP usage 和业务对象计数交叉验证。

不要把 `gc_collect_cycles()` 后的短期下降当作修复。先证明循环引用是增长来源，并测量主动 GC 的 CPU 与延迟成本。通过降低 `memory_limit`、缩短 `pm.max_requests` 或频繁重启隐藏增长，只能作为有回滚的临时 containment；根因仍需由稳定复现和对象/分配证据确认。

## OPcache、发布与预加载

先分别读取 FPM SAPI 与 CLI SAPI 的实际配置；它们可能加载不同 `php.ini` 和 `conf.d`，且 `opcache.enable_cli` 可能与 FPM 的 `opcache.enable` 不同。使用运行中 SAPI 的只读诊断页面或受控脚本读取 `opcache_get_configuration()`、`opcache_get_status(false)`，不要用本地 CLI 输出代替 FPM 事实。

需要验证并留存：

- OPcache 是否启用，命中数、miss、hit rate、cached scripts/keys 和 key/script 上限。
- used/free/wasted memory、wasted percentage、interned strings 使用量，以及 cache full 或 restart pending 信号。
- oom/hash/manual restart 计数和时间，与发布、FPM reload/restart、流量和延迟变化对齐。
- timestamp validation、revalidate 频率、file update protection 等代码变更检测策略是否与代码发布的原子性和一致性契约匹配。
- 预加载脚本、`opcache.preload_user`、启动失败、类/函数可用性、权限以及 FPM master 生命周期。
- CLI worker 是否在启动后长期保留旧代码；其部署和优雅退出策略不能照搬每请求重新进入应用的假设。

预加载会把代码放入 server 启动生命周期，收益必须在同版本、同 workload 下用 cold start、warm-up、steady state、OPcache memory、启动时间和 RSS 共同衡量。预加载脚本改变后通常需要对应生命周期的重载或重启才能生效；执行前必须有显式授权、容量余量、健康检查、逐批范围、停止条件和回滚版本。

这些操作的作用域不同，必须逐项记录实际操作、目标 SAPI/pool/PID、signal、进程代际、开始结束时间和验证结果：

- `opcache_reset()` 仅失效相应 SAPI 的 opcode cache；它不等于 worker restart，也不能据此断言进程内对象、扩展状态或持久连接已清除。
- FPM reload/restart 按实际 signal 和进程管理器行为逐批或整体替换 worker，因而可能重建被替换进程的进程内缓存与持久连接；验证旧/新 PID、配置生效和请求排空，不能只记录命令名。
- CLI worker restart 只影响该 worker 的进程状态和连接；其他 CLI worker、FPM pool 与其 OPcache 作用域需要分别验证。
- kernel page cache 通常保留；上述 PHP/FPM/CLI 操作本身不等于主机 reboot、内存压力回收或显式 drop cache。

重启后的暂时改善只能说明对应作用域的状态重置与症状相关，不能直接认定为修复。必须观察跨越原增长周期或原峰值窗口的复现，并证明错误率、代码版本一致性和业务正确性未退化。

## 应用热路径与连接容量

只有在 profile、trace 或分层 benchmark 指向对应成本后，才检查以下候选；优化前先声明边界和不变量：

- **序列化**：比较真实 payload 大小和字段分布，保留数值/字符串类型、编码、字段顺序契约、错误处理与兼容性；不能删除必要字段换取速度。
- **autoload**：测量文件查找、class map 和启动成本，保持部署原子性、类解析规则和动态加载正确性；不要用 warm cache microbenchmark 外推 cold start。
- **正则**：用代表性及对抗性长尾输入测量时间和回溯风险，保持匹配语义、输入上限和拒绝行为；修改后做差分正确性测试。
- **模板**：区分编译、加载和渲染，检查模板 cache 的版本键、失效、转义和租户/语言维度，不能牺牲 XSS 防护或 freshness。
- **网络客户端**：把 DNS、connect、TLS、TTFB、body read 和连接池等待分开，保留 connect/read/total timeout、证书验证、鉴权、错误分类和重试预算。
- **连接复用**：验证连接作用域、空闲/最大寿命、服务端关闭、故障切换和凭据轮换；复用减少握手不代表允许无限持有连接。
- **批处理**：记录 batch size 分布和等待组批时间，同时测吞吐、单项 p99、峰值内存、最大事务、锁时间和部分失败；保留顺序、幂等、ack 和重试语义。

PHP-FPM 中每个 worker 的客户端状态和持久连接通常互不共享；CLI 框架若提供进程内连接池，也必须以实现和指标证明其上限、等待和回收行为。为每类下游建立连接预算表：调用路径、每 worker 最大并发连接、实例/worker 数、总上界、下游限额、故障切换余量、timeout 和队列停止阈值。连接容量不满足时，增加 PHP 并发不是可接受实验。

所有候选同时验收异常类型和可见性、timeout/cancel、鉴权主体、数据结果、事务/幂等、缓存 freshness、错误率和回滚。异常被吞掉、timeout 延长到观察窗外或错误请求被排除时，吞吐与延迟数字无效。

## CLI worker 生命周期

长驻 CLI worker 的容量单位是任务及其生命周期，不是 HTTP request。按 queue/topic、任务类型、payload 分位和 retry 次数记录：

- 单任务 queue wait、执行耗时、下游等待、CPU time、PHP usage/peak 和任务边界 RSS。
- worker 启动时 RSS、每 N 个任务后的 RSS、增长斜率、回落平台、进程年龄和累计任务数。
- offered、started、succeeded、failed、retried、dead-lettered 和 acked 数，保证任务可对账。
- heartbeat 间隔、事件循环延迟、broker visibility/lease、并发槽位和队列 lag。
- 回收阈值的触发原因、完成中的任务、退出耗时、新 worker 就绪时间和重投递情况。

内存或任务数回收阈值必须低于容器/主机限制并保留单任务峰值、并发任务和优雅退出余量。阈值来自增长分布和原故障窗口，不是任意常数。回收只能限制 blast radius；若增长来自未清理 identity map、listener、静态 cache、批量数组或扩展资源，仍需修复所有权和清理边界。

heartbeat 不得因单个长任务、CPU 饱和或 stop-the-world 行为静默超时。验证任务执行模型能否独立刷新 lease/heartbeat；若不能，任务最大时长、broker timeout 和分片策略必须相容。延长 visibility timeout 可能推迟故障恢复，需与重复执行风险一起评估。

重试与 ack 语义必须先于性能修改确定：

1. 写明 ack 是在接收、业务效果持久化还是外部副作用后发生。
2. 进程在每个边界退出时，验证任务会被重投、完成、重复还是丢失。
3. 保持幂等键范围、事务边界、最大尝试、退避和 dead-letter 策略。
4. 批量或并发增加后，按业务 key 验证顺序、重复副作用和部分失败。

优雅退出时先停止领取新任务，继续 heartbeat 或延长当前任务 lease，在预定 grace period 内完成或按已声明语义放弃当前任务，再关闭连接并退出。超时后的强制终止是停止条件的一部分，不能静默改变 ack。发布、配置或预加载变化后，分批替换 worker，确认新版本 ready、队列 lag、错误率和内存后再继续；保留可执行的旧版本回滚。

## PHP-FPM 饱和证据顺序

按以下顺序可减少把等待误判为 worker 不足：

1. **确认用户症状**：目标 endpoint 的 offered/completed、p95/p99、错误/timeout/cancel、吞吐和发生窗口确实偏离基线。
2. **确认 FPM 排队**：同窗口的 `active/idle/total processes`、`listen queue`、`max children reached` 增量和 proxy upstream wait 一致变化。
3. **确认硬限制**：记录实际生效的 pool、`pm`、`pm.max_children`、实例数、CPU/内存 limit 和容器 throttling/OOM 信号。
4. **分解 busy worker**：用 trace、slow request 证据、低开销 CPU profile 和下游指标区分 on-CPU、数据库、Redis、网络与锁等待。
5. **建立容量交集**：在同 workload 下选择固定成本加 worker 私有内存模型，或 cgroup 总内存边际增长模型计算内存上界；核对 CPU 余量和所有下游连接/限流/failover 容量。
6. **选择单一候选**：优先修复已证实的慢路径或等待；只有每 worker 服务时间合理且容量有余量时，才实验性增加并发。
7. **同条件复验**：覆盖 warm-up、steady state、saturation 和 recovery，确认队列、p99、正确吞吐改善且 CPU、RSS、连接、错误率未越界。

反证示例：`listen queue` 持续为 0 且 idle worker 有余量，则“`pm.max_children` 太小导致当前 p99”缺少支持；增加 worker 不应进入优先实验。若队列随数据库连接池等待同时上升，减少下游等待或限制并发可能比增加 worker 更符合证据。

## CLI worker 内存增长证据顺序

1. **复现轨迹**：在同版本和代表性任务分布下，按任务边界记录 PHP usage、allocator usage、RSS/PSS、任务数和进程年龄。
2. **排除 workload 漂移**：按任务类型、payload、batch size、retry 和异常路径分层，比较固定序列或交错重复运行。
3. **区分高水位与持续增长**：确认是单次大任务后的稳定平台、allocator 保留/碎片，还是同类型任务后持续正斜率。
4. **缩小所有者**：检查跨任务 container/static/cache/listener/ORM/闭包/缓冲区和扩展资源，并用最小单一变量实验验证。
5. **验证生命周期**：比较新进程相同任务序列、回收前后和超过原故障周期的曲线；重启后归零只说明状态属于进程生命周期。
6. **设置 containment**：只有获得授权且有任务峰值余量、优雅退出、ack/retry 验证和回滚时，才调整任务数或内存回收阈值。
7. **验证修复**：在不依赖更频繁回收的条件下，增长斜率、任务 p99、CPU、错误、duplicate/lost/ack 和队列恢复都达到预设标准。

若 `memory_get_peak_usage` 保持高位但 RSS 和任务边界 usage 已稳定，不能据此断言仍在泄漏；若 RSS 增长而 PHP 指标稳定，则优先调查 allocator、扩展 native allocation、映射和 I/O buffer。

## 同条件复验与回滚清单

每次 PHP 性能实验至少记录：

```text
目标：路径或任务 / p95-p99 / 正确完成吞吐 / 错误率 / 资源成本 / 阈值
正确性：异常 / timeout-cancel / 鉴权 / 幂等 / 顺序 / ack / 数据与缓存 freshness
workload：endpoint 或任务分布 / payload / 并发或到达率 / batch / retry / 冷热 / 下游
环境：代码 / PHP 与扩展 / php.ini / FPM pool / OPcache / 预加载 / 容器限额 / 实例数
阶段：cold start / warm-up 判据 / steady state / saturation / recovery / worker age
证据：FPM status / proxy 与 trace / CPU profile / PHP allocator / RSS-PSS / OPcache / 下游
假设：已测事实 / 支持证据 / 反证 / 单一候选 / 预期可区分结果
容量：pm.max_children 或 CLI concurrency / 内存预算 / CPU / DB-Redis-外部连接与限额
控制：同条件 / 单一变量 / 运行顺序 / 停止规则 / profiler 开销 / 显式授权
结果：offered-completed-attempts / outcome / p50-p95-p99 / 吞吐 / CPU / RSS / queue / connections
生命周期：request 或 task 边界 / pm.max_requests 或回收阈值 / 发布 / OPcache / 优雅退出
停止：CPU-throttling / memory pressure-OOM / 连接耗尽 / 错误率 / 正确性 / profiler 开销
回滚：原参数或版本 / 触发条件 / 执行范围 / 完成信号 / 回滚后的观察窗口
结论：已证实 / 强支持 / 待验证 / 已排除 / 适用版本、workload 与阶段
```

复验必须使用与基线相同的 PHP SAPI、版本、配置、容器限额、实例拓扑、workload、预热、停止规则、重复次数和统计口径。同时报告 p50/p95/p99、正确完成吞吐、错误/timeout/cancel、CPU、RSS/working set、FPM queue 或 CLI lag、OPcache 状态和下游连接。任何候选若越过内存、CPU、下游容量、错误率或正确性边界，立即停止并回滚，即使某个延迟 percentile 改善。

只有在重复受控实验中达到预先声明的业务阈值、effect interval 支持目标方向、正确性守恒且资源仍有安全余量，才能声称候选在该范围内带来性能改善。单次 profile、一次 status 快照、重启后短期好转或本地 microbenchmark 只能产生待验证假设。
