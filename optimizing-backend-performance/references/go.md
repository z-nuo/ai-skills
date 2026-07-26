# Go 性能诊断与优化

## 目录

- 证据路径与适用边界
- Benchmark 设计与比较
- Profile 选择与采集
- Runtime、分配与 GC
- 锁、调度与并发
- Goroutine 与取消传播
- 连接池与下游等待
- 在线 pprof 安全边界
- 从 p99 症状到同条件复验
- 执行记录清单

## 证据路径与适用边界

Go 工具只能回答其观测边界内的问题。先按核心方法论定义目标、业务不变量、代表性 workload、受控基线和可证伪假设，再选择工具：

1. 从用户可见的 p95/p99、吞吐、错误/timeout/cancel、offered/completed logical operations 和 attempts 确认目标差距。
2. 固定请求分布、输入规模、并发或到达率、数据冷热、Go 版本、依赖版本、容器 CPU/内存限额、`GOMAXPROCS` 和观测配置。
3. 先关联已有指标、trace、日志和历史 profile；主动采集只补假设所缺的最小证据。
4. 用 `runtime/metrics`、OS/container 指标和恰当的 profile 区分 on-CPU、分配、GC、锁、调度与下游等待。
5. 每个候选修改绑定一个 profile 支持的瓶颈，一次改变一个关键变量，再以相同 workload、预热、停止规则、重复次数和统计口径复验。

microbenchmark 只证明被测函数或组件边界。若它跳过序列化、网络、连接池、排队或下游依赖，就不能据此声称端到端 p99 或吞吐提升；端到端结论必须来自覆盖完整用户路径的同条件测量。

## Benchmark 设计与比较

### 选择和实现被测边界

- 选择 profile、trace 或业务指标已经指向的热路径，并写明 benchmark 包含和排除的工作。例如“编码已加载的订单”不包含数据库、HTTP 和连接池等待。
- 使用与目标 workload 一致的输入大小、字段分布、热点比例和冷热状态。把小对象、典型对象、长尾大对象拆成命名清晰的 sub-benchmark，不能只报告最有利规模。
- 把输入准备移出计时区；需要时用 `b.StopTimer`、`b.StartTimer` 或 `b.ResetTimer` 明确边界。输入准备本身是目标时，不得把它排除。
- 消费返回值并验证最小正确性，防止编译器证明结果无用后消除工作。package-level sink 会改变逃逸和写入成本；使用时要声明它属于被测边界，并用 `go test -gcflags=-m` 辅助检查逃逸假设，而不是把逃逸输出当成性能结论。
- 固定 `-cpu` 以控制 `GOMAXPROCS`。串行和 `b.RunParallel` benchmark 分开报告；`b.RunParallel` 的 worker 调度由 benchmark 和 `GOMAXPROCS` 决定，不能当作真实请求到达模型。
- 使用 `b.ReportAllocs()` 或 `-benchmem` 报告 `B/op`、`allocs/op`，并同时保留 `ns/op`。若处理字节数有明确含义，可调用 `b.SetBytes`，但吞吐单位不能替代业务完成率。

一个保持边界明确的示例：

```go
package order

import "testing"

var benchmarkBytes []byte

func BenchmarkEncodeOrder(b *testing.B) {
	input := representativeOrder(128) // 固定、代表性的输入规模
	b.ReportAllocs()
	b.ResetTimer()

	var out []byte
	var err error
	for i := 0; i < b.N; i++ {
		out, err = encodeOrder(input)
		if err != nil {
			b.Fatal(err)
		}
	}

	benchmarkBytes = out
}
```

该示例测量编码函数及其返回值逃逸的边界。若生产路径还会压缩、写 socket 或调用下游，这些成本必须由更高层测试另行覆盖。

### 可复现运行与统计

使用 `go test -bench` 运行 benchmark。在本地或隔离测试环境中固定相同机器、功耗状态、Go 版本和后台负载，先保存基线，再只修改一个关键变量：

```bash
go test ./path/to/pkg -run '^$' -bench '^BenchmarkEncodeOrder$' -benchmem -count=10 -cpu=1 > baseline.txt
go test ./path/to/pkg -run '^$' -bench '^BenchmarkEncodeOrder$' -benchmem -count=10 -cpu=1 > candidate.txt
benchstat baseline.txt candidate.txt
```

- `-count` 产生多次 benchmark 结果；分析单位是一次 benchmark 运行，不是同一次运行中的 `b.N` 次迭代。记录命令、环境和原始输出。
- `benchstat` 可用时用固定版本比较多次样本，关注绝对差、相对差、变异和置信信息；不可用时保留逐轮结果并使用方法论预先规定的 run-level 分析。不得只挑最快一轮。
- 运行顺序应随机化或交错 baseline/candidate，避免温度、频率和后台任务漂移。两组环境或 workload 不可比时，只报告观察和不确定性，不计算因果“提升”。
- benchmark 通过后仍要运行功能、并发和端到端验证。`go test -race` 可发现数据竞争，但其开销使结果不适合作为性能基线。

## Profile 选择与采集

先从症状选择 profile；采集更多类型不等于证据更强，不同 profile 的累计窗口和采样开销必须记录。

| 证据 | 适合回答 | 不能直接证明 | 重点视图 |
|---|---|---|---|
| CPU profile | 采样窗口内哪些调用栈消耗 on-CPU 时间 | 锁、channel、网络或连接池等待为何耗时 | `top`、`top -cum`、flame graph、调用图 |
| heap `alloc_space` | 哪些栈累计分配字节/对象，解释 allocation rate 和 GC 压力 | 采集时仍然存活的内存 | 按 bytes/objects 查看累计分配 |
| heap `inuse_space` | 采集时哪些栈保有存活对象，解释 live heap 和 retention | 历史分配总量；单个快照也不能单独证明泄漏 | 多个同阶段快照及 live bytes 趋势 |
| mutex | 已发生的锁竞争及其阻塞时间 | 未采样事件；公平性或未来竞争 | delay/contentions、持锁相关调用栈 |
| block | channel、select 和同步原语等阻塞事件 | 普通网络调用的完整下游时间 | delay/contentions、阻塞调用栈 |
| goroutine | 采样时的 goroutine 状态与阻塞栈 | 时间占比或增长原因 | 重复栈、长时间阻塞、数量趋势 |

### 本地和测试环境采集

优先在代表性测试中一次采一种 profile，避免叠加观测开销：

```bash
go test -o ./pkg.test ./path/to/pkg -run '^TestRepresentativeWorkload$' -cpuprofile cpu.out
go tool pprof -top ./pkg.test cpu.out

go test -o ./pkg.test ./path/to/pkg -run '^TestRepresentativeWorkload$' -memprofile heap.out
go tool pprof -sample_index=alloc_space -top ./pkg.test heap.out
go tool pprof -sample_index=inuse_space -top ./pkg.test heap.out

go test -o ./pkg.test ./path/to/pkg -run '^TestRepresentativeWorkload$' -mutexprofile mutex.out -mutexprofilefraction=5
go tool pprof -top ./pkg.test mutex.out

go test -o ./pkg.test ./path/to/pkg -run '^TestRepresentativeWorkload$' -blockprofile block.out -blockprofilerate=1000000
go tool pprof -top ./pkg.test block.out
```

`./path/to/pkg` 是单个包的导入路径或目录参数，`-o ./pkg.test` 把该包的测试二进制明确保存为 `./pkg.test`；紧随每次采集的 `pprof` 命令使用同一输出二进制解析符号。实际项目可以替换这两个路径，但必须成对保存源码版本、profile 和生成它的测试二进制，避免符号与版本错配。`-mutexprofilefraction` 表示平均每 N 个 mutex contention 事件采一个；`-blockprofilerate` 以阻塞纳秒为采样尺度。数值要按 Go 版本核对并记录，不能把采样事件数当作完整事件总数。

测试服务只绑定 loopback 的 pprof 端点时，可以限时抓取 CPU profile：

```bash
curl --fail --silent --show-error --max-time 35 \
  'http://127.0.0.1:6060/debug/pprof/profile?seconds=30' \
  --output cpu.pb.gz
go tool pprof -top ./service cpu.pb.gz
```

这里的地址仅适用于本机隔离环境。不要把 `net/http/pprof` 端点直接暴露到公网或业务 listener。

### 解释规则

- CPU profile 的 flat 值表示函数自身样本，cum 值包含后代；先确认采样窗口覆盖症状，再由热点提出可证伪假设。
- `alloc_space` 高而 `inuse_space` 低通常指向短命分配压力；`inuse_space` 持续随同阶段快照增长才构成 retention 线索，仍需排除 cache、workload 和 GC 周期差异。
- mutex/block profile 默认可能未启用或采样不足。空 profile 只表示当前配置和窗口没有足够样本，不能证明不存在竞争。
- profile 百分比是采样窗口内的相对占比；某项占比下降可能只是其他成本上升。必须同时检查绝对 CPU、延迟、吞吐、错误率和资源成本。

## Runtime、分配与 GC

使用 `runtime/metrics` 读取稳定、版本化的 runtime 指标名，并在启动时通过 `metrics.All()` 发现当前 Go 版本支持的指标。同一 metric key 的 `ValueKind` 是稳定契约；读取代码应处理 metric 在当前版本被移除、当前实现不支持，或 `metrics.Read` 后值为 `KindBad` 的情况，不能假定固定列表永远可用。常用信号包括 CPU classes、heap alloc/live/goal、GC cycles、GC pauses、scheduler latencies 和 goroutine 数。

把 runtime 信号与服务和 OS/container 指标放在同一时间线：

| 现象组合 | 优先假设 | 下一证据 |
|---|---|---|
| CPU 接近可用配额，throttling 上升，CPU profile 有稳定热点 | on-CPU 饱和或容器限额 | 热点调用栈、每请求 CPU、配额与 runnable 工作量 |
| allocation rate、`alloc_space` 和 GC frequency 同时升高，live heap 稳定 | 短命分配驱动 GC CPU | `B/op`、alloc bytes/sec、GC CPU、候选路径 microbenchmark |
| live heap 与 heap goal 上升，GC 后基线不回落 | retention、cache 或 workload 规模增长 | 同阶段 `inuse_space`、对象数、cache 基数和多次快照 |
| GC pause 或 assist 增强且 p99 同步恶化 | GC 对尾延迟有贡献 | pause histogram、GC CPU/assist、allocation rate 和请求时间线 |
| goroutine/runnable 工作上升、CPU 未满、scheduler latency 或 block 增强 | 锁、channel、调度或外部等待 | mutex/block/goroutine profile、runtime trace、依赖分段耗时 |

检查 allocation 和 GC 时同时记录：

- 每个 logical operation 的 `B/op`、`allocs/op`，以及服务级 bytes/sec、objects/sec。
- live heap、heap goal、GC cycles/frequency、GC CPU、pause 分布和尾延迟时间线。
- RSS、容器 memory working set/limit、page fault 和 OOM 事件；Go heap 不是进程全部内存。
- Go 版本、`GOGC`、`GOMEMLIMIT` 或 `debug.SetMemoryLimit`、容器限额和运行阶段。

只有 `alloc_space` 和 benchmark 同时定位到业务热路径时，才尝试减少临时对象、预分配或批量化。`sync.Pool` 中对象可随时被 runtime 移除，不能用于正确性或资源所有权；对象池还可能抬高存活内存、保留大 backing array、增加清零成本和竞争。更改 `GOGC`、memory limit 或对象池前要预先规定 RSS/heap、GC CPU、pause、p99 和 OOM 停止阈值，并检查以 CPU 换内存或以内存换 CPU 的成本。

## 锁、调度与并发

锁、channel、goroutine、batch、对象池和 `GOMAXPROCS` 都不是通用加速旋钮。修改前必须有 CPU、mutex、block、goroutine、runtime trace 或 runtime 指标支持，并明确预期可区分结果。

- 锁竞争：先确认高 contention/delay 调用栈和临界区，再考虑缩小临界区、分片或改变所有权。验证 race、原子性、顺序、公平性、饥饿和 p99，不能只看吞吐。
- channel：确认阻塞发生在 send、receive 还是 select，并区分期望背压与意外串行化。扩大 buffer 可能把等待变成内存、排队延迟和取消滞后。
- goroutine：并发只对可并行且有容量的工作有效。增加 worker 前检查 CPU 配额、下游并发/连接上限、队列、内存和失败放大。
- batch：比较每批大小分布、等待凑批时间、吞吐、p99、峰值内存、错误重试范围和部分失败语义。
- `GOMAXPROCS`：记录 `runtime.GOMAXPROCS(0)`、Go 版本、CPU affinity、cgroup quota/period 和 throttling。不要假定所有 Go 版本都以相同方式自动适配容器，也不要把逻辑 CPU 数直接当作可用 CPU 配额。

本地或测试环境可用短时 runtime trace 检查 goroutine 状态、scheduler、network blocking 和 GC 时间线：

```bash
go test ./path/to/pkg -run '^TestRepresentativeWorkload$' -trace trace.out
go tool trace trace.out
```

trace 可能体积大且包含敏感元数据；限定代表性短窗口并安全保存、清理。它解释调度时间线，但仍不能替代端到端 workload 对比。

## Goroutine 与取消传播

goroutine 数是存量，不等于泄漏。把 `/sched/goroutines:goroutines` 趋势与重复 goroutine 栈、请求完成后是否回落、队列和依赖状态结合起来；在相同 steady state workload 下持续增长且终止条件缺失，才形成泄漏假设。

每个启动 goroutine 的调用方都要能回答：谁拥有它、何时结束、结果和错误如何回收、阻塞 send/receive 如何退出。并发 fan-out 必须设置有界并行度，并在 channel 操作和依赖调用中监听调用方 context。`context cancellation` 只有被 goroutine、数据库 driver 和 HTTP 调用实际传播并观察到时才会释放工作；仅创建 context 不会自动停止 goroutine。

保持原有 timeout、deadline 和取消语义：

- 从入口 `r.Context()` 向下传递，不用 `context.Background()` 截断调用链。
- 数据库使用支持 context 的调用，例如 `QueryContext`/`ExecContext`；HTTP 请求用 `NewRequestWithContext` 或 `WithContext`。
- 不为改善表面吞吐而提前取消慢请求、缩短统计窗口、忽略 `ctx.Err()` 或丢弃错误。timeout/cancel 仍要按 logical operation 口径分类并进入 offered 分母。
- 优化后检查取消传播耗时、未结束 goroutine、未归还连接、部分写入和重试行为；提前返回不能留下无界后台工作。

## 连接池与下游等待

先分离三个阶段，避免把所有等待归因于连接池：

1. **应用池等待**：获取连接前的排队。对 `database/sql` 记录 `DB.Stats()` 的 `WaitCount`、`WaitDuration`、`InUse`、`Idle` 和 `MaxOpenConnections`，并用 trace 标记 acquire 阶段。
2. **下游服务时间**：连接取得后，数据库或服务执行、排队、锁和限流耗时。结合下游指标、query/operation 类型和服务端 trace。
3. **网络等待**：DNS、dial、TLS、socket read/write 和连接复用。使用分阶段 trace 或客户端 hook，不能仅由总请求时长反推。

只有应用池等待显著且下游仍有容量时，才把扩大连接池作为假设。对 `database/sql` 的 `SetMaxOpenConns`、`SetMaxIdleConns`、`SetConnMaxIdleTime` 和 `SetConnMaxLifetime` 一次只改一个关键变量；扩大前检查数据库总连接预算、各实例池上限之和、proxy 限额、事务时长、服务端 queue/CPU/I/O、failover 余量和扩容后的过载放大。更多连接可能降低本地等待，却把队列推到下游并恶化全局 p99。

HTTP 客户端同样要记录连接获取与复用，结合 `Transport` 的 `MaxConnsPerHost`、`MaxIdleConns`、`MaxIdleConnsPerHost` 和各阶段 timeout。响应 body 必须按协议正确消费并关闭；不得通过泄漏连接、取消后继续读取或忽略错误制造吞吐改善。

连接池实验同时报告池等待分布、下游服务时间、网络阶段、端到端 p99、吞吐、错误/timeout/cancel、连接数、下游饱和度和资源成本。连接上限只是容量约束，不是单实例 benchmark 得出的常量。

## 在线 pprof 安全边界

本地或隔离测试环境优先。生产环境先读取已有指标、trace、日志和历史 profile；在线 pprof 只有在证据缺口无法离线补齐时才可申请，且以下条件缺一不可：

- 获得显式授权，记录工单、责任人、目标假设、采集窗口和数据保留期。
- 只选单实例，限时采集；不得同时对全部实例启用，不得在生产执行压测配合采集。
- 预先限定采样类型、采样频率或比例和最大样本量。CPU、heap、mutex、block、goroutine 或 trace 只启用回答当前假设所需的最小集合，不叠加无关采样。
- 预先规定 CPU、内存、延迟、错误率和实例健康停止阈值；触发任一阈值立即停止采样，并将流量或实例恢复到批准前状态。
- 采集前保存 profiler、`MemProfileRate`、mutex/block rate 等原配置；结束或触发停止条件后关闭采样并恢复配置，验证服务健康。
- pprof 端点置于认证后的管理面，绑定 loopback 或隔离管理网络，并使用防火墙/allowlist 和最小权限；`net/http/pprof` 本身不提供完整生产认证，禁止公网暴露或复用无认证业务入口。
- 对访问、配置变更、采集开始/结束、下载和删除留审计记录。profile、goroutine dump 和 trace 按敏感数据处理，限制访问和短期保留，避免把 token 写入命令历史，按期安全清理本地、对象存储和临时副本。

在线证据仍是观测而不是实验因果证明。报告采样窗口、覆盖请求量、profile rate、观测开销、停止阈值是否触发和未覆盖的不确定性。

## 从 p99 症状到同条件复验

以下是完整路径示例，数字仅表示实验记录格式，不是可套用的目标值。

### 1. 目标、workload 与基线

症状：某 Go HTTP 服务的订单读取路径在峰值窗口 p99 超出既定 SLO。先冻结：

- offered load 和 open/closed 模型、endpoint 占比、订单行数/payload 分布、缓存命中率、读写比、依赖延迟分布与数据集版本。
- Go/依赖版本、实例数、容器 CPU request/limit、memory limit、`GOMAXPROCS`、连接池和 timeout 配置。
- warm-up 稳定判据、steady state 窗口、固定时长或固定 offered logical operation 数、随机化运行顺序和独立重复次数。
- 每轮 offered/completed logical operations、attempts、outcome 分类、吞吐、p50/p95/p99、CPU、内存、allocation/GC、goroutine、scheduler、连接池等待和下游资源。

先验证序列化结果、鉴权、timeout、幂等和数据对账不变量。已有时间线若显示 p99 上升时 allocation rate、GC CPU 和 GC frequency 同步上升，但下游服务时间和池等待稳定，只能生成“订单编码的短命分配促使 GC 工作增加并贡献尾延迟”的待验证假设，不能直接归因。

### 2. Runtime 与 profile 补证

在相同 workload 的隔离环境采集 `runtime/metrics`、CPU profile 和 heap profile。若 CPU profile 显示编码栈占据稳定 on-CPU 热点，heap 的 `alloc_space` 指向同一栈，而 `inuse_space` 在 GC 后保持稳定，则证据支持短命分配假设；若 CPU 热点、allocation 和 p99 时间线不一致，就记录反证并转向锁、调度或下游等待假设。

只为已定位的编码函数增加 microbenchmark，覆盖典型与长尾订单规模，并用前述固定 `-count`、`-cpu`、`-benchmem` 和 `benchstat` 流程比较。它用于验证该函数的 `ns/op`、`B/op` 和 `allocs/op`，不承担端到端证明。

### 3. 单变量候选与停止条件

候选只改变编码路径的一项分配行为，例如依据已测输出大小分布预分配目标 slice；其他代码、workload、连接池、`GOMAXPROCS` 和 GC 配置保持不变。实验前声明：

- 预期结果：同一编码输出下 `B/op`、allocation rate 和 GC CPU/frequency 向预定方向变化，端到端 p99 的 effect interval 达到业务最小有意义效应。
- 反证：分配下降但 p99 区间无可区分变化，或热点移到网络/下游，说明不能声称端到端收益。
- 停止/失败：错误、timeout/cancel、输出对账、RSS/live heap、CPU、p99 或下游负载超过预设边界，立即停止并回滚候选。

### 4. 同条件复验与结论

交错运行 baseline/candidate 多轮，使用相同预热、停止规则、workload、环境、采样设置和 logical operation 统计。合并兼容 histogram 或按预先规定的 run/block 单位计算配对变化和 effect interval，同时报告绝对值、相对变化、每轮变异、正确性和资源成本。

只有候选在端到端 p99 达到预先业务阈值、effect interval 支持该方向，错误/timeout/cancel 与正确性不恶化，且 CPU、内存和下游成本可接受时，才在该版本、环境和 workload 范围内给出因果结论。若只有 microbenchmark 改善，结论必须限定为编码边界；若环境或采样条件漂移，只报告观察和不确定性并安排可比复验。

## 执行记录清单

每次 Go 性能工作至少保留：

- 目标/SLO、业务不变量、代表性 workload、环境、Go 版本和容器限额。
- baseline/candidate 的源码版本、配置、`GOMAXPROCS`、连接池、timeout/deadline 和 GC 设置。
- 原始 benchmark、`benchstat`、profile、`runtime/metrics`、trace 和服务/下游指标位置，以及采样率和观测开销。
- 假设、支持证据、反证、单变量、预期可区分结果、成功/失败/停止条件和回滚。
- offered/completed logical operations、attempts、outcome、p99、吞吐、错误率、资源成本及同条件统计比较。
- goroutine 终止、context cancellation、锁公平性、队列/连接池等待、下游容量和敏感 profile 清理结果。
