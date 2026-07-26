# 消息投递一致性参考

## 目录

- [交付语义](#交付语义)
- [消息契约](#消息契约)
- [重复与幂等](#重复与幂等)
- [顺序与乱序](#顺序与乱序)
- [Kafka](#kafka)
- [RabbitMQ](#rabbitmq)
- [重试与死信](#重试与死信)
- [重放与恢复](#重放与恢复)
- [观测](#观测)
- [评审清单](#评审清单)

## 交付语义

跨数据库、broker 和消费者的流程默认采用 at-least-once：消息允许重复，但不能静默丢失，消费者必须幂等。不要把 producer 的一次调用成功、broker 的事务能力或 consumer 的一次 ack 解释为端到端 exactly-once。

逐段说明可靠性边界：

1. 业务数据与待发布事件是否原子提交。
2. producer 如何确认 broker 已持久化消息。
3. consumer 在什么时点确认消息。
4. 业务副作用与消费进度能否原子提交。
5. 任一进程在确认前后崩溃会留下什么状态。

无法形成共同事务的两个系统之间，总会存在“动作已成功但确认状态未记录”的窗口。使用重复投递、幂等、对账和恢复关闭该窗口，而不是用 exactly-once 标签掩盖它。

## 消息契约

为所有事件定义稳定 envelope，业务 payload 独立演进：

```json
{
  "event_id": "9f4bb1c4-6f0f-4b07-8ab8-97b627f935cb",
  "event_type": "order.created",
  "event_version": 1,
  "occurred_at": "2026-07-22T10:00:00.123Z",
  "producer": "order-service",
  "aggregate_type": "order",
  "aggregate_id": "ord_123",
  "partition_key": "ord_123",
  "correlation_id": "req_456",
  "causation_id": "cmd_789",
  "payload": {}
}
```

- `event_id` 是一次事实事件的稳定 ID；重试发布不能生成新 ID。
- `event_type` 表达已发生的业务事实，不使用模糊的 `data.changed`。
- `event_version` 显式标记 schema 版本；优先做向后兼容的字段新增。
- `aggregate_id`/`partition_key` 用于同一业务实体的局部顺序。
- `correlation_id` 关联一次业务流程，`causation_id` 指向直接原因。
- payload 只包含消费者需要的数据；敏感字段按数据分级处理。

发布前验证 schema。删除字段、改变含义或收紧约束时，采用扩展-迁移-收缩，先让消费者兼容新旧版本，再切换 producer。

## 重复与幂等

重复可能来自 producer 超时重试、confirm/ack 丢失、consumer 崩溃、rebalance、死信重放或人工修复。幂等边界必须覆盖真实业务副作用：

- 使用 `event_id` 或稳定业务幂等键建立数据库唯一约束。
- Inbox 去重记录与消费者业务写入在同一本地事务提交。
- 外部支付、邮件或第三方 API 使用其幂等键；不支持时保存调用状态并对账。
- 已处理重复消息直接返回成功，不再次执行副作用。
- 相同幂等键但 payload fingerprint 不同视为冲突并告警。

不要只在内存或短 TTL Redis key 中去重需要长期保证的副作用。去重保留期必须覆盖消息保留期、最大重试期、死信存放期和允许的人工重放期。

## 顺序与乱序

全局顺序通常代价高且没有必要。先明确业务需要的顺序范围，通常是同一 `aggregate_id`：

- producer 对同一实体使用稳定 partition/routing key。
- 事件携带实体版本或单调 sequence。
- consumer 记录最后应用版本；旧版本重复到达时忽略。
- 发现版本间隙时暂停该实体、延迟重试或从事实来源重建。
- 不同实体之间不要依赖发布时间形成业务顺序。

只有“忽略旧事件”会丢掉非覆盖型变化，例如增量扣减。此类事件使用唯一事件去重并按实体串行应用，或改为携带可重建的目标状态和版本。

## Kafka

- partition key 决定局部顺序和负载分布；避免热点 key 和随机 key 破坏实体顺序。
- producer 开启与可靠性要求匹配的 acknowledgment 和幂等 producer 配置，并明确重试及超时。
- consumer 完成幂等业务事务后再 commit offset；提前 commit 会丢业务处理，过晚 commit 会重复。
- rebalance、进程退出和批处理部分失败时，确保未完成记录不会被错误提交。
- 监控 consumer lag、最老待处理事件年龄、partition 倾斜和重复命中。

Kafka transactions/EOS 可在受支持的 Kafka read-process-write 链路中原子提交 output record 与 offset，但不能自动把外部 MySQL、Redis、HTTP 或第三方副作用纳入同一事务。跨这些边界仍按 at-least-once + 幂等设计。

## RabbitMQ

- producer 使用 publisher confirm 判断 broker 是否接受消息；连接成功或 basic.publish 返回不代表消息已确认持久化。
- 需要持久化时同时验证 durable exchange/queue、persistent message 和集群/副本策略；confirm 只在对应 broker 配置边界内提供保证。
- 需要路由失败可见时启用 mandatory/return 处理，并验证 exchange、queue 和 binding。
- consumer 在业务事务成功后 ack；临时错误可 nack/reject 后进入受控重试。
- 明确 consumer 断连、ack 丢失和 unacked 消息重新入队导致的重复。
- 配置合理 prefetch，避免单个 consumer 囤积过多未确认消息或放大热点。

不要对永久错误无限 `requeue=true`，这会形成无退避的热循环并阻塞正常消息。

## 重试与死信

先分类再重试：

| 错误类型 | 示例 | 处理 |
|---|---|---|
| 瞬时错误 | 超时、限流、依赖短暂不可用 | 有界指数退避 + jitter |
| 竞争错误 | 乐观锁冲突、实体暂时乱序 | 按实体有界重试或延迟 |
| 永久错误 | schema 不兼容、非法状态 | 直接进入死信/隔离 |
| 未知错误 | 未分类异常 | 少量重试后隔离并告警 |

- 重试必须记录 attempt、next retry time 和稳定 error code。
- 总重试时长受业务时限约束，不能无限延长不一致窗口。
- 死信保留原始 envelope、失败阶段、错误分类、首次/末次失败时间和尝试次数。
- 死信队列必须有负责人、告警、查询、修复、重放和审计流程。
- payload 含敏感数据时，死信存储执行相同或更严格的数据保护策略。

## 重放与恢复

重放是正常恢复能力，不是临时脚本：

1. 先修复永久错误或部署兼容 consumer。
2. 选择 event ID、业务键、时间窗口和目标 consumer 范围。
3. 在隔离环境或 dry-run 中估算数量和副作用。
4. 限速重放并保留原始 `event_id`，让 Inbox/幂等逻辑生效。
5. 观察 lag、错误率、下游容量和业务差异，达到停止条件立即暂停。
6. 对账确认结果，并记录操作人、条件、版本和结果。

不能通过修改 ID 绕过去重来“强制重放”；需要重新执行业务动作时，创建有审计记录的新命令，并使用新的幂等边界。

## 观测

至少提供：发布确认失败率、发布延迟、consumer lag/积压、最老消息年龄、处理成功/失败率、重试分布、幂等命中、乱序/版本间隙、死信数量和重放结果。

日志携带 `event_id`、`event_type`、consumer、attempt、partition/queue、业务键和 correlation ID。指标 label 只使用有限集合；event ID、业务 ID 和完整错误文本只能进入受控日志或 trace。

## 评审清单

- 是否明确采用 at-least-once，并为每个副作用提供幂等边界？
- 消息 envelope、schema 兼容、幂等键和顺序范围是否清楚？
- Kafka offset 或 RabbitMQ ack 是否在业务成功之后？
- producer confirm 前后崩溃是否可通过 Outbox 和重复消费恢复？
- 重试是否有分类、上限、退避、jitter 和业务时限？
- 死信是否可查询、可修复、可审计、可限速重放且有负责人？
- 是否避免把 Kafka EOS 或 broker 事务描述为跨 MySQL 的 exactly-once？
