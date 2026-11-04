# 消息性能与积压恢复

以最终业务完成率、端到端尾延迟和最老消息年龄为目标，同时观察到达、处理、重试、ack/commit 与 broker/下游资源。拉取、投递或提前确认不代表有效完成。

## Kafka

- 按 partition 看输入、lag、最老年龄、处理耗时与 assignment；总体平均会掩盖热点。consumer 增加受 partition、顺序及下游容量约束。
- batch/fetch/linger/压缩分别实验，同时看组批等待、字节、内存、CPU 和故障重试范围。
- 只能提交连续已完成 offset。rebalance 收束 in-flight，不能越过未完成项。
- `pause()` 不撤回已经 `poll()` 的记录；应用有界保存这些工作，暂停期间继续按 group 要求 poll，consumer API 由 owner 串行调用。高低水位恢复防止抖动和下游洪峰。

## RabbitMQ

- `ready` 是等待投递，`unacknowledged` 是已投递未确认；结合 deliver/ack、最老年龄与业务结果判断瓶颈。
- prefetch 记录 `basic.qos global` 作用域、consumer/channel 数和真实 in-flight；过大会囤积、失公平并扩大重投，不能只以一个 prefetch 数估总容量。
- delivery tag 属于 channel；`multiple=true` 确认该 channel 至指定 tag 的全部未确认消息，只有此前都成功才可批量推进，否则单项 ack 或维护连续成功水位。
- 增加 consumer、拆 queue 或改路由分别验证；拓扑改变还影响顺序、迁移和回滚，不按 queue 数线性外推。

## 恢复估算

统一存量与速率口径。使用 logical events 时按稳定 ID 去重，Kafka committed lag 已含未提交 in-flight，不能再加；Rabbit ready/unacknowledged/retry/DLQ 同一事件只计一次。无法去重时使用一致的 attempt 模型，不把结果说成业务事件已清空。

仅当有效处理率 `μ > λ`（到达率）时，稳定近似 `T = B / (μ - λ)`；目标窗口反推 `μ_required = λ + B / T_target`。`μ` 已是含重试成本的净业务完成时不重复扣减重试。

恢复阶段重试放大用相同事件 cohort 从开始到终态跟踪，不能套稳态窗口的 attempts/offered 比率。变化负载分段更新积压，故障接管空窗先计新增积压，再按剩余 deadline 算能力，不能沿用原始 `B/T_target`。

增长与故障余量分开，故障后的 partition/queue/下游能力用实际验证，不能线性折算。负载、消息分布、拓扑、runtime/broker 或配额变化时重新测量。

## 实验边界

并发、batch、prefetch 和 ack 一次改变一个关键变量。覆盖正常/部分失败、重投、退出及重平衡，对账无丢失、重复副作用或顺序破坏；in-flight、字节、缓冲和下游并发均有上限。达到错误、内存、最老年龄或资源停止阈值即停止，恢复旧配置后继续核对未确认工作。
