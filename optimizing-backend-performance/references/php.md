# PHP 性能

区分 FPM 请求与长驻 CLI 的生命周期，记录实际 SAPI、PHP/扩展、ini、pool、OPcache、容器限额和负载。CLI 配置不能代替运行中 FPM 的事实。

## FPM 与容量

- 关联 active/idle/total、当前 `listen queue`、`max children reached` 窗口增量及 proxy 延迟。`max listen queue` 是启动/重置以来峰值，不是当前窗口峰值。
- busy worker 可能等待数据库、Redis、锁或网络；先分解请求时间，不能直接增加 `pm.max_children`。CPU throttling 也会使全部 worker 变慢。
- `static/dynamic/ondemand` 的启动和空闲行为不同，冷启动与突发分别测。`pm.max_requests` 回收缓解长期增长，不是根因修复。

`memory_limit` 不是 RSS 上限；扩展、allocator 保留及共享页另计。内存预算选择一种一致模型：固定共享/非 worker 成本 + worker 私有 USS 高分位 + 瞬态余量，或在受控 worker 数下实测 cgroup 总内存边际增长。不能把单 worker PSS 乘新 worker 数，也不重复计算 OPcache。

并发上界是内存、CPU和下游容量的交集。持久连接通常每 worker 独有，按实例 × worker × 每 worker 连接数计入后台进程及故障余量。

## 内存与 CLI

`memory_get_usage(false)`、allocator 的 `true` 值、RSS、cgroup 总量及平台 working set 是不同口径。请求内存释放后 RSS 未降可能是保留/碎片；CLI 的 peak 默认是进程生命周期高水位，不能当单任务峰值。

按相同任务结构记录任务前后 current usage、RSS、任务数和进程年龄。持续增长时再查 static/cache、container、listener、ORM、闭包及扩展资源；单次峰值或重启后归零不证明泄漏已修复。强制 GC 或频繁回收只能在有证据及容量边界时作为实验/缓解。

## OPcache 与热点

检查命中、内存、cached scripts、restart/pending 与发布配置。`opcache_reset()`、FPM reload/restart、CLI restart 的作用域不同，均不代表清掉 kernel page cache；验证实际进程代际、代码和配置生效。

只对已定位的序列化、autoload、正则、模板、网络或 batch 热点做实验，保持字段类型、转义、权限、错误及部分失败语义。Xdebug 等高开销追踪限隔离定位，其数字不能当生产基线；系统 profile 无 PHP 栈时不虚构函数级归因。

候选同时复验尾延迟、正确吞吐、错误、RSS、队列及下游压力。回滚记录旧参数和生效生命周期，内存修复观察须跨原增长周期。
