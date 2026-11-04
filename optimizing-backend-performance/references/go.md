# Go 性能

## 工具边界

| 证据 | 能定位 | 解释限制 |
|---|---|---|
| CPU profile | on-CPU 热点 | 不解释网络/锁等完整等待 |
| heap `alloc_space` | 累计分配 | 不代表仍存活内存 |
| heap `inuse_space` | 当前保留 | 单次快照不证明泄漏 |
| mutex / block | 竞争、同步等待 | 空样本不证明无争用 |
| goroutine / runtime trace | 栈与调度时间线 | 快照数量不等于泄漏 |

profile 与生成它的二进制、版本、采样窗口一起保存。flat 与 cum、占比与绝对 CPU 分开解释。`runtime/metrics` 名称/类型按项目 Go 版本及 `metrics.All()` 确认，不假定所有指标都可用。

## Benchmark

只测证据指向的热路径，使用真实输入分布；准备工作移出计时区，目标本身包含准备成本时例外。消费结果防优化消除，同时注意 sink 改变逃逸。串行与 `RunParallel` 分开，后者不是业务到达模型。

```bash
go test ./path/to/pkg -run '^$' -bench '^BenchmarkName$' -benchmem -count=10 -cpu=1 > baseline.txt
```

候选使用相同命令和条件另存结果；现有 `benchstat` 可用时比较多轮，不只挑最快值。`-race` 用于并发正确性，结果不作性能基线。局部 `ns/op`、`B/op`、`allocs/op` 不能外推端到端收益。

## 资源与等待

- allocation rate、live heap、GC CPU/pause、RSS 与容器限制一起看；Go heap 不等于 RSS。`sync.Pool` 可丢对象，不承担正确性或所有权，也可能保留大内存。
- 锁、channel buffer、worker、batch 和 `GOMAXPROCS` 仅在对应证据支持时调整；后者须核对 Go 版本、CPU 配额与 throttling。
- 每个 goroutine 明确结束与回收；并行度有界，context 传到实际数据库/HTTP 调用及 channel 等待，避免提前返回后遗留后台工作。
- `DB.Stats()` 的 `WaitCount/WaitDuration/InUse/Idle` 帮助区分池等待；连接取得后的服务时间和 DNS/dial/TLS 另量。扩大池前核对全实例下游连接预算，避免把排队推给下游。

用同负载的功能、并发和端到端结果复验候选，观测工具按主技能的线上授权与数据保护边界使用。
