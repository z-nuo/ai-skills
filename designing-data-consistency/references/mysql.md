# MySQL 一致性参考

## 目录

- [事务边界](#事务边界)
- [隔离与锁](#隔离与锁)
- [约束与幂等](#约束与幂等)
- [死锁与重试](#死锁与重试)
- [Outbox原子写](#outbox原子写)
- [评审清单](#评审清单)

## 事务边界

- 以一个业务不变量为单位划定 InnoDB 本地事务，不以 Repository 方法数量划分。
- 事务内只执行必须原子提交的数据库操作；HTTP、RPC、消息发布和长耗时计算移到事务外。
- 所有成功路径和错误路径都显式 commit/rollback，禁止依赖连接关闭隐式处理。
- 在事务提交后再失效缓存或触发非事务副作用；回滚事务不能留下“已成功”消息。
- 多个服务不要直接写同一业务表；通过拥有者接口或消息同步派生状态。

## 隔离与锁

遵循仓库和生产环境的隔离级别，不假设开发默认值。根据不变量分析脏读、不可重复读、幻读和写偏差，而不是盲目提升隔离级别。

- 更新已知行时使用条件 `UPDATE` 或版本列实现 optimistic concurrency。
- 必须基于当前状态做决策时，可在明确索引范围内使用 `SELECT ... FOR UPDATE`。
- 锁查询必须命中预期索引，避免无意扩大 gap/next-key lock 范围。
- 多表或多行更新保持稳定顺序，降低死锁概率。
- 不持锁等待用户输入、远程服务或消息确认。

## 约束与幂等

数据库唯一约束是并发不变量的最终防线，应用层“先查再写”不能替代：

- 业务请求 ID、外部单号或 `(tenant_id, idempotency_key)` 建立唯一约束。
- 幂等记录和业务结果在同一事务提交。
- 相同幂等键与不同请求 fingerprint 返回冲突，不复用旧结果。
- 使用受影响行数或 duplicate-key 结果区分首次执行与重复执行。
- 金额使用精确 decimal/整数最小单位，避免浮点导致对账漂移。

## 死锁与重试

InnoDB 死锁是并发控制的正常结果之一：

- 捕获明确的 deadlock/lock timeout 错误，回滚整个事务后重试。
- 使用有限次数、指数退避和 jitter；不要只重放事务中间的一条 SQL。
- 事务中的外部副作用必须幂等或移出事务，否则数据库重试会重复副作用。
- 记录稳定 error code、attempt、transaction name 和相关业务键；指标 label 不使用业务 ID。
- 重试耗尽后进入明确失败或补偿流程，不无限循环。

## Outbox 原子写

业务数据与 Outbox event 必须在同一 MySQL 事务写入。示例 DDL：

```sql
CREATE TABLE outbox_events (
    event_id CHAR(36) NOT NULL,
    aggregate_type VARCHAR(64) NOT NULL,
    aggregate_id VARCHAR(128) NOT NULL,
    event_type VARCHAR(128) NOT NULL,
    payload JSON NOT NULL,
    headers JSON NULL,
    occurred_at DATETIME(6) NOT NULL,
    available_at DATETIME(6) NOT NULL,
    published_at DATETIME(6) NULL,
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    last_error VARCHAR(1024) NULL,
    PRIMARY KEY (event_id),
    KEY idx_outbox_pending (published_at, available_at, event_id)
) ENGINE=InnoDB;
```

发布器按稳定顺序批量领取待发布行。MySQL 8 可在短事务中使用 `FOR UPDATE SKIP LOCKED` 协调多个 worker；不要持有数据库锁等待 broker。

broker confirm 成功后标记 `published_at`。发布成功但标记前崩溃会产生重复消息，因此消费者仍必须幂等。长期失败更新 `attempts`/`last_error` 并进入告警或人工处理，不删除证据。

## 观测

至少监控最老未发布事件年龄、待发布数量、发布速率、失败率、重试次数和永久失败数量。日志使用 `event_id`、`aggregate_type` 和 trace/correlation ID 关联，不记录完整敏感 payload。

## 评审清单

- 不变量是否由事务和唯一约束共同保护？
- 隔离级别、锁范围和索引是否与并发场景匹配？
- 死锁是否重试整个事务，外部副作用是否安全？
- 业务写和 Outbox 是否同事务提交？
- 发布器崩溃窗口是否由消费者幂等处理？
- Outbox backlog 是否可观测、可重放且有负责人？
