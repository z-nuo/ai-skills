# PHP 生产诊断

对齐客户端/proxy、FPM、应用、依赖及发布的时间线，按规范 operation、版本、实例、PID/trace ID 找证据。日志写入相邻不代表同一请求或因果顺序。

## FPM 与慢请求

active/idle/total、`max children reached` 窗口增量和 `listen queue` 联合判断。累计触顶不代表现在饱和；队列高不能单独归因于 max_children。先查 busy worker 在计算还是等待依赖，增加 worker 可能扩大下游和内存压力。

slowlog 是超过阈值后的栈采样/写入时间，不是请求开始。用 access log、server span 或 full status 确认起点，记录阈值、时钟与导出延迟；无样本也可能因阈值、退出或丢失。

## 错误分离

分别记录 transport 结果、失败机制及发起层，避免把 `5xx` 当根因或给同一请求重复计数：

| 机制 | 主要证据与边界 |
|---|---|
| PHP fatal | PHP error log/stderr；请求可终止而 FPM child 仍存活 |
| `memory_limit` 耗尽 | `Allowed memory size ... exhausted`；不是 kernel OOM，也不证明 child 退出 |
| process crash/signal | FPM child exit、signal、PID 与 master 日志，可能无 PHP 日志 |
| kernel/cgroup OOM kill | kernel、`memory.events`/`oom_kill` 或平台 termination reason；不能由 PHP fatal 替代 |
| timeout / cancellation | deadline 来源、先后事件和下游剩余执行；status 单独不足以归责 |

业务拒绝与非预期异常分别建模；网关 `499` 等只作为平台 transport 证据。重启记录不能反向证明 fatal/OOM。

## 内存、OPcache 与 CLI

PHP current usage、allocator `true`、进程 RSS、cgroup `memory.current` 和平台 working set 口径不同。CLI peak 是生命周期高水位；按任务前后 current usage、RSS、进程年龄和相同任务分布看持续增长，排除平台期与共享页后再判断泄漏。

OPcache 命中、内存、restart/pending 与发布时间关联；CLI/FPM 配置和作用域分别确认。opcode cache reset 与进程重启不是同一操作，命中率低不能独自解释代码错误。

worker 关联生产/消费/ack、任务耗时、heartbeat、最后成功时间、重试与最老消息年龄；心跳缺失不等于进程死亡。回收先停领新任务，在安全边界处理 in-flight，验证未确认消息与幂等。周期重启只是缓解，修复须跨原增长/高峰周期验证。
