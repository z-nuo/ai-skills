# Kafka 与 RabbitMQ 性能诊断

## 目录

- 消息性能实验契约
- 统一速率与正确性口径
- Kafka 指标关联与瓶颈定位
- Kafka consumer 并发实验
- Kafka batch、fetch、linger 与压缩实验
- Kafka commit、rebalance 与背压
- RabbitMQ 指标关联与瓶颈定位
- RabbitMQ prefetch、并发与 batch ack 实验
- RabbitMQ 队列扩展与背压
- 积压恢复、故障余量与重新校准
- 验证、停止与回滚

## 消息性能实验契约

消息系统调优先定义业务目标和不可破坏条件，再调整参数。目标至少包含正确完成消息的吞吐、端到端 p95/p99、错误率、最老消息年龄、允许积压和恢复窗口；不可破坏条件至少包含持久化、重复、丢失、顺序、幂等、重放及业务副作用边界。

每次实验记录以下条件，任一关键条件变化时不得直接比较提升百分比：

| 类别 | 固定并记录的内容 |
|---|---|
| workload | topic/queue 与消息类型占比、持续到达率、突发形态、消息大小分布、key/routing key 分布、失败消息比例 |
| 应用 | producer/consumer 版本、实例与线程/进程数、序列化、重试、timeout、batch、commit/ack 配置 |
| broker | 产品与版本、broker/node、partition/queue、replica、磁盘、网络、持久化和流控配置 |
| 下游 | 数据库、缓存、HTTP 服务的延迟、错误、配额、连接池及允许并发 |
| 运行阶段 | warm-up 判据、steady state、saturation、故障阶段和 recovery 阶段 |
| 统计 | 窗口、重复次数、offered/completed logical messages、attempts、percentile 和时间对齐方式 |

生产环境默认只读取已有指标、trace、日志和历史实验。参数扫描、阶跃负载、断连、进程终止、rebalance 和故障注入只在隔离环境或经过明确授权的有限范围执行，并预先设定资源与业务停止阈值。

单变量实验按以下顺序执行：

1. 写成可证伪假设，例如“consumer 并发不足导致处理率低于到达率，而非下游饱和”。
2. 固定 workload、环境、消息数据集、版本、观测窗口和正确性不变量。
3. 采集多轮基线，覆盖 steady state；恢复问题还要覆盖相同的积压起点和 recovery 阶段。
4. 一次只改变一个关键变量，采用交错或随机顺序重复 baseline/candidate。
5. 同时比较吞吐、p95/p99、积压、错误/重试、CPU、内存、网络、磁盘、broker 与下游饱和度。
6. 运行重复、丢失、乱序、业务副作用与对账检查；任一不变量失败即判候选失败。

## 统一速率与正确性口径

在同一对齐窗口同时报告三套计数：

- `offered logical messages`：首次进入系统的业务消息数，producer 重试不新增 logical message。
- `completed logical messages`：完成业务终态并满足正确性条件的消息数；失败、timeout 和窗口结束未完成项分列。
- `attempts`：初次投递/处理和全部重试尝试数；`重试放大 = attempts / offered logical messages`。

到达率和处理率必须说明分子、时间窗口和单位。推荐以 `messages/s` 报告持续到达率，以“业务副作用成功且可对账的 logical messages/s”报告有效处理率。若改用 `attempts/s` 做资源预算，必须把到达 attempts 按重试放大换算，不能把 attempts 容量与 logical messages 到达率直接相减。

lag 或 queue depth 是存量，速率是流量，不能单看其中一个判断健康：

- 到达率高于处理率且存量持续上升，说明当前容量不足或处理退化。
- 存量下降但最老消息年龄上升，可能存在热点 partition、毒消息、顺序阻塞或饥饿。
- lag 突降而 completed logical messages、业务对账没有对应增长，优先排查提前 commit、错误 ack、消息过期或丢弃。
- 吞吐上升但 attempts、重复副作用或失败率增长，必须计入重试成本并判定正确性是否仍成立。

## Kafka 指标关联与瓶颈定位

Kafka 诊断必须在同一时间线同时观察：

| 信号 | 需要分层或关联的证据 | 用途与限制 |
|---|---|---|
| 到达率 | producer logical records/s、send attempts、bytes/s、消息大小 | 区分业务增长与重试造成的网络放大 |
| 处理率 | 成功业务完成 records/s、poll records/s、attempts/s | poll 快不等于业务处理完成 |
| consumer lag | group/topic/partition lag 总量与变化率 | 总量会掩盖 partition 倾斜 |
| 最老消息年龄 | 每 partition 最老未完成消息年龄及总体最大值 | 识别饥饿、热点和恢复是否达到业务窗口 |
| partition 分布 | key 频率、records/bytes、lag、leader 与 consumer assignment | 判断热点 key、分配不均和可用并行槽位 |
| 处理耗时 | poll 等待、队列等待、单条/batch 业务处理、下游调用、commit | 把 broker 拉取与应用/下游瓶颈分开 |
| 错误与重试 | send/fetch/process/commit 错误、attempt、退避、最终失败 | 计算重试放大并防止隐藏错误 |
| rebalance | 原因、次数、持续时间、撤销/重新分配、暂停处理时长 | 解释处理空洞、重复窗口和 lag 跳变 |
| 资源 | consumer/broker CPU、内存、GC、磁盘、网络、连接与下游饱和 | 识别容量从一层转移到另一层 |

先按 partition 查看到达率、有效处理率、lag 和最老消息年龄，再汇总到 topic/group。若少数 partition 恶化，优先验证 key 倾斜、单条慢消息或局部 leader/broker 问题；若全部 partition 同时恶化，再检查共享下游、consumer 资源、网络、broker 或 deployment/rebalance 事件。相关性用于提出假设，不能单独宣布根因。

## Kafka consumer 并发实验

有效并行度受可分配 partition 数约束，还受 partition 倾斜、每条消息处理方式、顺序范围、consumer CPU/内存及共享下游容量约束。consumer 数超过 consumer group 当前可分配的 partition 数，只会产生空闲 consumer 或额外协调成本，不会增加有效并行度。

执行并发阶梯实验：

1. 固定 partition 数、key 分布、到达率、消息大小、处理逻辑、batch/commit 参数和下游配额。
2. 从当前 consumer 数开始逐级调整，每级完成预热并运行相同 steady-state 窗口；不要在同一实验同时改 partition 数。
3. 每级记录 assignment、活跃/空闲 consumer、每 partition 到达率/处理率/lag、最老消息年龄、p95/p99、rebalance、重复、错误和资源。
4. 对下游记录实际并发、连接池等待、限流、timeout 和饱和度，判断新增 consumer 是否只把排队转移到下游。
5. 在隔离环境执行单 consumer/实例退出，测量撤销、重新分配、恢复处理的时间及故障期间剩余有效处理率。

候选只有在正确性不变、尾延迟和资源未越界且有效处理率达到预设阈值时通过。出现空闲 consumer 增多而吞吐不变、热点 partition 的 lag 不降、rebalance 更频繁、下游 timeout/连接等待增加，或单位消息资源成本恶化时停止扩并发并回滚。

需要增加 partition 时，把它作为独立的拓扑与语义实验：验证 key 映射变化、顺序范围、历史数据分布、部署兼容、迁移/回滚和 broker 成本。不得把增加 partition 当作无成本线性扩容。

## Kafka batch、fetch、linger 与压缩实验

batch 调优同时涉及 producer 聚合、consumer fetch/poll 和业务处理批次。每次只改变一个关键变量，例如 producer `batch.size`、`linger.ms`、压缩算法、consumer fetch 上限/等待、poll 记录上限或应用 batch 大小；其余条件保持不变。

对每个候选值同时检查：

- 吞吐及 producer、broker、consumer 各段 records/s 与 bytes/s。
- 单条消息端到端 p50/p95/p99、batch 填充等待和最老消息年龄，不能只报告 batch 平均耗时。
- producer/consumer/broker 峰值与常驻内存、缓冲区、GC、单批最大记录数和最大字节数。
- 网络包/字节、请求次数、压缩比与压缩/解压 CPU，以及 broker 磁盘和请求队列。
- send/fetch/process/commit 错误、timeout、失败 batch 的重试策略和重试放大。
- commit 粒度、已完成与未完成记录边界、崩溃后的重复处理窗口。
- poll 间隔、处理时长、rebalance 次数/持续时间，以及撤销 partition 时未完成工作如何收束。

每个 batch 同时设置最大记录数、最大字节数和最大等待时间，避免低流量下无限等待或大消息导致内存尖峰。压缩候选使用真实消息大小和可压缩性分布；压缩节省网络但使 CPU、尾延迟或故障恢复恶化时不视为净改善。

用注入唯一 `event_id` 的可对账消息验证正常、部分失败、timeout、进程退出和重试。失败 batch 必须保留逐条结果或安全地重新处理未确认范围；不得把失败记录静默丢弃，也不得为缩小重复窗口而提前 commit。

## Kafka commit、rebalance 与背压

offset commit 必须对应已经完成并满足业务不变量的处理边界。提前 commit 会在进程崩溃后丢失业务处理；过晚 commit 会扩大重复窗口。若副作用与 offset 无法原子提交，按 at-least-once 设计稳定幂等键、去重/Inbox、对账和重放，不能用吞吐数字替代语义证明。

在隔离环境用故障矩阵验证：

| 故障时点 | 必须观察的结果 |
|---|---|
| 拉取后、业务处理前退出 | 消息可再次处理，无静默丢失 |
| 业务副作用成功后、commit 前退出 | 允许重新投递，但幂等阻止重复副作用 |
| batch 部分成功时退出 | 只提交连续且已完成的安全边界，失败项可恢复 |
| commit 请求 timeout 或结果未知 | 重试/重启后可对账，重复可控且无丢失 |
| rebalance 撤销 partition | 停止接收新工作，有界等待/取消 in-flight，不提交未完成记录 |

背压必须同时限制：

- 每 consumer 和每 partition 的 in-flight 记录数与字节数。
- 应用队列、batch 缓冲和重试队列的内存上限。
- 数据库、HTTP、缓存等共享下游的实际并发、连接和请求速率。

为高/低水位设置抖动区间：达到高水位时暂停对应 partition 的新拉取或减少调度，in-flight 降到低水位且下游健康后才恢复。Kafka `pause()` 只暂停后续 fetch，不撤回 `pause()` 前一次 `poll()` 已返回的 records；应用必须把这些 records 保存在有消息数和字节数上限的队列中，达到上限时停止继续接纳，而不是丢弃或无限缓存。partition 处于 pause 时 consumer loop 仍继续 `poll()`，使相邻两次调用不超过 `max.poll.interval.ms` 并维持 group 协议；业务处理与 poll loop 解耦时也必须由 consumer owner 串行调用 consumer API。记录 pause 前已取 records、队列高低水位、poll 间隔、暂停时长、原因、lag 和最老消息年龄；恢复采用有界爬坡，避免积压瞬间冲垮下游。

进程退出、rebalance 或下游故障时停止接收新工作，给已开始工作限定收束时间；未完成项不 commit，并在重新分配后按幂等规则重投。内存或下游并发越过硬上限、pause/resume 振荡、最大消息年龄越过阈值或错误率恶化时停止实验并恢复旧参数。

## RabbitMQ 指标关联与瓶颈定位

RabbitMQ 必须关联发布、broker、投递、确认和业务完成各阶段：

| 信号 | 需要同时观察的内容 |
|---|---|
| publish/confirm | logical publishes/s、publish attempts/s、bytes/s、confirm 延迟/失败/timeout、returned/unroutable |
| deliver/ack | deliver rate、ack rate、业务成功率、nack/reject、redelivery 与重试放大 |
| queue 存量 | `messages_ready`、`messages_unacknowledged`、总量及最老消息年龄 |
| consumer | consumer utilization、consumer 数、prefetch、连接/channel、实际 in-flight 与公平性 |
| 分布 | node、queue、consumer、routing key 的 messages/bytes、积压、处理率和资源 |
| 处理路径 | queue 等待、单消息/批处理耗时、数据库/HTTP 等下游 p95/p99、错误、连接池等待 |
| broker 资源 | node/queue CPU、内存、磁盘、网络、流控、连接和持久化成本 |

`messages_ready` 上升表示消息尚未投递；`messages_unacknowledged` 上升可能是处理变慢、prefetch 过大、consumer 卡住或断连尚未回收。ack rate 只有与业务成功和对账一致时才代表有效处理率。publish 返回、deliver 或 ack 单一指标都不能独立证明端到端成功。

按 queue 和 consumer 分解指标。若少数 consumer 持有大量 unacknowledged 且其他 consumer 空闲，优先验证 prefetch、公平性和处理耗时长尾；若 ready 增长且 consumer utilization、下游和 broker 资源已饱和，分别设计单变量实验区分消费、路由、单队列与共享依赖瓶颈。

## RabbitMQ prefetch、并发与 batch ack 实验

prefetch 的作用域由 `basic.qos` 的 `global` 决定：`global=false` 是 per-consumer 限制，`global=true` 是 channel-global 限制；两种限制可同时生效，此时 delivery 必须同时满足各自额度。数值太小可能使等待往返限制吞吐，太大则会放大单 consumer 囤积、内存、处理不公平、断连重投和重复窗口。每轮记录 `global` 值、channel 数、每个 channel 的 consumer 数、per-consumer 与 channel-global prefetch、每 consumer/channel 实际 in-flight 及所有 channel 的总 in-flight，不能用一个 prefetch 数直接推算总容量。consumer 并发与 batch ack 也受单消息耗时差异、顺序范围、连接/channel、单队列和共享下游约束。

按以下顺序做独立实验，不同时修改 prefetch、consumer 数和 ack 批次：

1. **prefetch 阶梯**：固定 consumer 数、ack 方式与 workload，逐级改变 prefetch；报告 ready/unacknowledged、每 consumer in-flight、处理率、p95/p99、公平性、内存和下游并发。
2. **consumer 并发阶梯**：固定 prefetch 和 ack，逐级增加 consumer；报告每 consumer 工作分布、连接/channel、queue/node 与下游资源，识别收益平台和资源转移。
3. **batch ack 阶梯**：固定 prefetch 和 consumer 数，只改变每次 ack 覆盖的消息数或最大等待时间；同时设置最大条数与最大等待时间。
4. 每级都使用包含快、慢、瞬时失败和永久失败消息的代表性分布，多轮比较稳定阶段与相同积压起点的恢复阶段。
5. 在隔离环境断开 consumer 连接，测量 unacknowledged 回收、redelivery、恢复时间、重复命中和业务对账。

公平性至少报告每 consumer 处理数、in-flight、处理时间和长期空闲/饥饿情况，而不只看总吞吐。RabbitMQ delivery tag 是 channel-scoped，必须在收到 delivery 的同一 channel ack。`multiple=true` 会确认该 channel 上所有尚未确认且 `delivery tag <= tag` 的 delivery；只有连续的较小 tag 全部业务成功时才可推进此边界。并发处理乱序完成或中间失败时，对成功项使用 `multiple=false` 单 ack，或由唯一 channel owner 维护连续成功水位后批量 ack；不能越过失败或未完成 tag。断连重投和 ack 结果未知都按重复可发生设计，幂等键、去重存储和业务写入边界必须经故障测试验证。

吞吐改善若伴随 `messages_unacknowledged`、峰值内存、consumer 间离散度、断连重投量、重复副作用、下游 timeout 或最老消息年龄越界，则候选失败。恢复旧 prefetch/concurrency/ack 配置后，继续处理或重新入队所有未确认消息，并执行对账。

## RabbitMQ 队列扩展与背压

增加 consumer 不保证线性扩容：单 queue 的 broker/node、路由、顺序、channel/连接、消息大小、持久化以及共享下游都可能先饱和。增加或分片 queue 还会改变 topology、routing key、局部顺序、故障切换、监控、迁移和回滚成本。不得把增加、迁移或分片 queue 当作无成本线性扩容，也不能按 queue 数直接乘当前吞吐。

队列扩展实验先固定 workload 和 consumer 参数，分别测量单 queue 饱和点、候选 topology 的每 queue/node 分布、路由正确性、顺序、端到端 p99、broker/下游资源及故障恢复。迁移须验证 producer/consumer 对新旧 topology 的兼容、消息排空/重放、重复与丢失对账；无法回滚的迁移不作为普通参数实验。

RabbitMQ 背压设置三类硬上限：

- per-consumer/channel prefetch 与实际 in-flight 消息数、字节数。
- 应用工作队列、重试缓冲和未确认消息所占内存。
- 数据库、HTTP、缓存等下游并发、连接和速率。

达到高水位时停止从本地调度更多 delivery、降低消费并发或按既定机制取消/暂停 consumer；降到低水位且下游健康后有界恢复。暂停/取消前说明已投递消息如何完成、nack/requeue 或等待断连重投，避免无限 `requeue` 热循环。故障时停止接收新工作，已成功项才 ack，未成功项进入有退避和上限的重试或隔离路径。

同时观察 broker publish 流控、producer confirm 延迟和应用消费背压。只限制 consumer 而让 producer 无限缓存会把内存风险转移到 producer；只限制 producer 也不能保护已经积压的下游恢复流量。

## 积压恢复、故障余量与重新校准

恢复估算先统一口径。使用 logical messages 口径时定义：

- `B`：以 `event_id` 对 logical event 去重后的可处理积压量，单位 `messages`。同一 `event_id` 在 ready/lag、in-flight/unacknowledged、延迟重试和 DLQ 等互斥状态间只计一次：Kafka committed lag 已包含 fetched 但尚未 commit 的 in-flight records，不再另加 in-flight；RabbitMQ 对 ready、unacknowledged、retry 与 DLQ 按 `event_id` 跨状态去重。无法跨全流程按 `event_id` 去重时，切换为互斥的 attempt-backlog 模型：定义待处理 attempt 存量 `B_attempt`，并全程使用 attempt 到达率 `λ_attempt`、attempt 处理率 `μ_attempt`、`R_attempt = μ_attempt - λ_attempt` 和 `T_attempt = B_attempt / R_attempt`；所有 main、in-flight、retry 和纳入恢复范围的 DLQ work 按 attempt 口径对账。此时不能继续使用 logical `B`、logical 到达率或 logical 恢复时间，也不能把 attempt 结果表述为 logical backlog 已恢复。
- `λ`：恢复窗口内的持续到达率，单位 `messages/s`，使用峰值/预测曲线而非长期平均值。
- `μ`：在相同 workload、正确性、错误率和资源阈值内测得的有效处理率，单位 `messages/s`；应已包含重试消耗后的净业务完成。
- `R = μ - λ`：净消化速率，单位 `messages/s`。
- `T = B / R`：恢复时间，单位 `s`；仅当 `R > 0` 时计算。

若 `R = 0`，积压不会收敛；若 `R < 0`，积压继续增长。此时不得报告有限恢复时间，应先降低到达率或提高经验证的有效处理能力。到达率随时间变化时按短窗口分段计算 `B_next = B_current + (λ - μ) × Δt`，直到积压归零；不能用单个平均 λ 掩盖峰值。

若使用 attempts 容量预算，amplification 必须按同一组 `event_id` cohort 计算 `A = cohort attempts / cohort logical events`，从首次处理跟踪到成功、最终失败或预先声明的删失边界。任意时间窗口的 `attempts / offered logical messages` 只有在 steady state、窗口边界流入流出近似平衡时才能作为窗口比率，不能用于恢复阶段。恢复模型分别报告新到达 attempt work `λ_attempt,new(t)` 与历史 backlog attempt work；后者使用 backlog cohort 的实测剩余 attempts，不把 steady-state `A` 直接乘到全部 `B`。失败比例、退避和毒消息分布会改变两类工作；不要在 `μ` 已是净 logical completion 时再次乘除重试放大而重复扣减。

目标恢复窗口 `T_target` 反推出最低有效处理率：

```text
μ_required = λ + B / T_target
```

所有量分别列出测量值、业务预测和假设值，并保留单位、窗口、消息大小/类型分布和置信范围。示例只用于校验公式：若 `B = 900000 messages`、`λ = 2000 messages/s`、`μ = 2500 messages/s`，则 `R = 500 messages/s > 0`，`T = 1800 s = 30 min`；若重试或故障使 `μ <= 2000 messages/s`，则不能计算有限恢复时间。

容量余量必须把增长余量与故障余量分开：

1. 用 steady-state 实测能力检查日常与峰值到达率，不使用瞬时最高样本。
2. 在隔离环境分别模拟一个 consumer/实例退出、目标可用区或 broker/node 故障场景；记录故障期间实际可分配 partition/queue、rebalance/重投空窗和有效处理率 `μ_fault`。
3. 以故障开始为时间原点 `t = 0`，目标 deadline 为 `t = T_target`。若 rebalance、重连或接管产生处理空窗 `D`，先按空窗内实际到达与处理增加积压：`B_D = B_0 + integral_0^D (λ_fault(t) - μ_fault(t)) dt`；完全停顿时 `μ_fault = 0`。恢复从 `t = D` 开始后，常量近似使用剩余窗口 `T_target - D`，检查 `μ_required = λ_after + B_D / (T_target - D)`，且必须满足 `D < T_target`。到达率或处理率变化时按以 `t = 0` 为共同时间原点的分段 recurrence 更新 `B`，逐段验证 deadline；不能先用旧 `B/T_target` 再另报空窗。
4. 单独保留预先声明的容量余量，覆盖到达率预测误差、消息大小/处理耗时长尾、重试放大和扩容/重平衡生效时间；不得把增长余量重复计作故障余量。
5. Kafka consumer 容量仍受故障后可分配 partition 限制；RabbitMQ 容量使用故障 topology 实测，不按剩余 queue 或 consumer 数线性折算。

恢复演练使用相同起始积压、持续到达曲线和正确性检查，记录 predicted 与 actual 恢复时间、各阶段处理率、最老消息年龄、重试放大、资源峰值和下游压力。偏差超出预先容忍范围时停止外推，更新模型并重新实验。

出现以下任一变化时重新校准单 consumer/实例能力、饱和点、净消化速率和故障余量：

- 消息类型组合、大小、压缩率、key/routing key 分布或热点显著变化。
- 到达率、突发持续时间、失败比例、重试放大或目标恢复窗口越过预设范围。
- 应用、runtime、client、broker 版本或 batch/commit/ack/prefetch 配置变化。
- partition、queue、replica、broker/node、consumer topology、硬件、网络或持久化策略变化。
- 数据库/HTTP 等下游延迟、错误、配额、连接池或允许并发变化。
- 实测吞吐、p99、资源成本、rebalance/断连恢复或预测恢复时间偏离基线容忍区间。

## 验证、停止与回滚

每个候选方案使用同一验收表：

| 维度 | 必须报告的结果 |
|---|---|
| 输入与完成 | offered/completed logical messages、attempts、到达率、有效处理率、重试放大 |
| 用户时间 | 端到端 p50/p95/p99、最老消息年龄、目标恢复窗口 |
| 存量 | Kafka partition lag；RabbitMQ ready/unacknowledged；延迟重试/死信是否计入 |
| 正确性 | commit/ack 时点、幂等命中、重复、丢失、乱序、重放、业务副作用与对账 |
| 故障 | rebalance、断连、重投、单实例/节点故障时的处理空窗、`μ_fault` 与恢复时间 |
| 资源 | 应用/broker CPU、内存、GC、磁盘、网络、连接、下游并发与饱和度 |
| 可比性 | workload、版本、配置、topology、数据集、预热、窗口、重复与原始结果位置 |

预先定义停止条件：错误率或 timeout 恶化，出现丢失/重复副作用/顺序不变量失败，p99 或最老消息年龄越界，in-flight/内存/磁盘/网络/连接/下游并发越过硬上限，rebalance 或 pause/resume 持续振荡，confirm/commit/ack 无法确定，或积压在预期应恢复时不再收敛。触发任一条件立即停止增加负载或参数阶梯。

回滚记录旧的 consumer、batch、fetch、linger、compression、commit、prefetch、concurrency、ack 和背压参数，并说明配置生效边界。回滚后验证 producer/consumer 连接、assignment/queue topology、未确认消息的重投、lag/ready/unacknowledged、错误率、下游健康和业务对账。只有受控重复实验同时通过性能、正确性、故障与资源门槛，才可把候选称为已证实改善。
