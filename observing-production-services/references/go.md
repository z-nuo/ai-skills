# Go 生产诊断

同窗口关联 operation/版本/实例的请求速率、结果、延迟与 in-flight，再分解内部、连接池、网络和下游等待。`DEADLINE_EXCEEDED`、`CANCELLED` 或 `context` 错误只说明结果，须追谁设置 deadline、谁先取消及下游是否继续执行。

## 区分假设

| 假设 | 需要的证据 |
|---|---|
| GC 影响尾延迟 | allocation/live heap、GC 配置、CPU 供给与 GC cycle/CPU/pause 的同窗变化及实例对照 |
| goroutine 泄漏 | 压力解除后持续增长、重复栈累积，排除正常长连接和后台并发 |
| 锁竞争 | mutex wait、mutex/block 栈与请求并发关联，scheduler latency 单独不足 |
| 连接池等待 | in-use/limit、waiters、acquire duration 与获取栈，区分取得连接后的服务时间 |
| 下游慢 | client/server span 与对端证据，另查 DNS、dial、TLS、重试及本地等待 |

allocation/live heap 稳定不能单独排除 GC；`GOGC`、memory limit 或 throttling 变化也可能驱动。heap、RSS、cgo/mmap 与容器总量分开，单快照不证明泄漏。runtime 指标名/类型按当前 Go 版本确认，累计量按重启边界处理。

## Profile

CPU 只解释 on-CPU；heap 分清累计 alloc 与存活 in-use；goroutine 是瞬时栈；mutex/block 受采样率与窗口限制，空样本不证明没有问题。

仅对明确假设补最小 profile，保存实例、版本、窗口及采样配置。不要强制 GC 改现场。调整采样按已有授权限定范围并恢复原值；`SetMutexProfileFraction` 返回旧值，`SetBlockProfileRate` 无 getter，后者原值未知时不临时调整。

pprof/debug 只在受认证的隔离管理面限时访问，产物受控保留。Pod restart/OOM/throttling 作为运行证据与请求对齐，不能直接当业务根因。性能实验另用性能技能，本参考只判断当前诊断证据支持什么。
