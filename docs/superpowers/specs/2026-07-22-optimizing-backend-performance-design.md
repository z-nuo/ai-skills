# optimizing-backend-performance 设计规格

## 背景

当前仓库已经覆盖接口设计、后端测试、数据一致性和生产可观测性，但尚未提供从性能症状到可重复实验、瓶颈归因、优化验证和容量规划的完整方法。新技能面向主要使用 Go、PHP 的后端研发人员，覆盖同步请求、MySQL/Redis 数据访问以及 Kafka/RabbitMQ 异步消费性能。

## 设计决策

- 创建单一技能 `optimizing-backend-performance`，不拆分 profiling、压测和容量规划。
- 性能结论必须建立在代表性 workload、可复现基线和同条件对比上。
- 本地或测试环境可以执行 profiling、benchmark 和压测；生产环境默认只读取已有证据。
- 在线 profiling 仅在显式授权后以单实例、限时、限采样率方式执行。
- 禁止直接对生产环境施加压测流量。
- 同时覆盖 Go、PHP、MySQL、Redis、Kafka 和 RabbitMQ，但不重复已有技能的观测、一致性或功能测试细节。
- 首版只提供工作流和按需加载的 reference，不提供 scripts 或 assets；性能工具、部署拓扑和指标系统差异较大，固定脚本容易制造错误假设。

## 适用范围

技能在以下场景触发：

- Go/PHP 服务的延迟、吞吐、CPU、内存、allocation 或 GC 不符合目标。
- 需要设计 benchmark、profiling、负载测试或容量实验。
- 优化 MySQL 查询/索引、Redis key/命令或客户端连接池。
- 调整 Kafka/RabbitMQ consumer 并发、batch、背压和积压恢复能力。
- 评审已有性能优化是否具有基线、证据、同条件验证和回滚条件。
- 发布前评估单实例能力、安全余量、扩容阈值和增长容量。

技能不负责生产事故分诊、集群运维、功能测试实现或分布式一致性方案设计。发现这些问题时路由到已有技能。

## 文件结构

```text
optimizing-backend-performance/
├── SKILL.md
├── agents/openai.yaml
└── references/
    ├── methodology.md
    ├── go.md
    ├── php.md
    ├── data-stores.md
    ├── messaging.md
    └── load-testing.md
```

文件职责：

- `SKILL.md`：统一工作流、任务模式、默认交付物、reference 路由、技能边界和红线。
- `methodology.md`：性能目标、基线、假设、实验控制、瓶颈归因和前后对比方法。
- `go.md`：Go benchmark、pprof、runtime、allocation、GC、锁、并发和连接池。
- `php.md`：PHP-FPM、CLI worker、OPcache、CPU、内存和生命周期性能。
- `data-stores.md`：MySQL 查询/索引/锁/连接池及 Redis 命令/key/内存/客户端行为。
- `messaging.md`：Kafka/RabbitMQ 吞吐、batch、consumer 并发、背压和积压恢复。
- `load-testing.md`：workload 模型、压测执行、coordinated omission、容量估算和停止条件。

所有 reference 由 `SKILL.md` 直接引用，不创建 reference 间的嵌套引用。超过 100 行的 reference 必须在开头提供 `## 目录`。

## 统一工作流

1. 定义性能目标、业务不变量、关键路径、目标流量和代表性 workload。
2. 在本地或测试环境建立基线，记录版本、配置、数据规模、并发、预热、持续时间、重复次数和方差；除下一步明确授权的在线 profiling 外，生产环境只读取已有证据。
3. 在相同 workload 下采集 application profile、runtime、数据库、缓存和消息处理证据；需要在线 profiling 时先满足显式授权及限时、限实例、限采样率约束。
4. 建立“瓶颈假设、支持证据、反证、实验、预期可区分结果”的表格，按收益、风险和验证成本排序。
5. 一次只改变一个关键变量，优先优化占总耗时或资源比例最大的已证明瓶颈。
6. 使用相同 workload 对比优化前后结果，同时验证错误率、p95/p99、吞吐、CPU、内存、资源成本和功能正确性。
7. 根据饱和点、目标利用率、增长率和故障余量生成容量模型、扩容阈值及回滚条件。

如果环境、数据规模或 workload 不可比，只能报告观察结果和不确定性，不给出性能提升百分比。

## 任务模式与交付物

### 性能诊断

按顺序交付：

1. 性能目标、当前基线和差距。
2. workload、环境、版本及数据规模。
3. 已确认事实和 profile/指标证据。
4. 瓶颈假设表及下一实验。
5. 优化候选、预期收益、风险和回滚方式。
6. 同条件验证方案及停止条件。

### 实验设计

按顺序交付：

1. 被验证的单一假设和业务不变量。
2. 环境、数据集、workload 与流量模型。
3. 预热、持续时间、重复次数和噪声控制。
4. 采集 profile、指标和正确性证据的方式。
5. 成功、失败、停止和回滚判据。
6. 结果记录与可复现命令。

### 优化评审

先按严重程度列出缺少基线、workload 不可比、只看平均值、隐藏错误、牺牲一致性、未控制变量或没有回滚条件的问题。随后给出最小修正实验；没有发现问题时说明剩余噪声和未覆盖负载区间。

### 容量规划

按顺序交付：

1. 当前单实例稳定吞吐和目标利用率。
2. 延迟、错误率或积压开始非线性增长的饱和点。
3. 峰值流量、增长率、故障余量和部署冗余。
4. 所需实例/consumer 数、扩容提前量和触发阈值。
5. Kafka/RabbitMQ 积压产生与恢复时间估算。
6. 模型假设、敏感性范围和重新校准条件。

容量计算必须声明单位和口径。基础估算使用 `所需健康实例数 = ceil(目标峰值到达率 / (单实例稳定吞吐 × 目标利用率))`；部署实例数再叠加明确的故障余量和冗余策略。积压恢复仅在处理速率大于持续到达率时计算，基础估算使用 `恢复时间 = 积压量 / (处理速率 - 持续到达率)`。存在分区上限、连接上限、共享下游或非线性饱和时，不得只套用线性公式，必须列出约束并以实验重新校准。

## 测量与统计原则

- 同时报告请求量、错误率、吞吐、p50/p95/p99、CPU、内存和关键饱和度。
- 从兼容 histogram bucket 聚合 percentile；不平均实例预计算的 percentile。
- 报告多次运行的分布、方差或置信范围，不以一次最佳结果作为结论。
- 区分 cold start、warm-up、steady state、saturation 和 recovery 阶段。
- 使用相同版本、配置、数据集、workload 和观测开销做前后对比。
- profiler、日志和 tracing 本身的开销必须测量或保持一致。
- 优化结果必须包含绝对值和相对变化，并说明样本量和不确定性。

## 安全与正确性边界

- 没有基线和代表性 workload，不宣称性能提升。
- 禁止向生产环境直接压测。
- 生产环境默认只读；在线 profile 需要单实例、限时、限采样率、显式授权和恢复原配置。
- 不通过降低超时、丢弃请求、关闭校验、弱化鉴权或牺牲数据一致性制造吞吐提升。
- 不只优化平均延迟；必须检查尾延迟、错误率和资源成本。
- 不在 benchmark 中跳过真实序列化、网络、数据规模或依赖行为后声称端到端提升。
- 调整并发、batch、连接池或队列参数时必须检查背压、内存上限、公平性和故障恢复。
- profiling 产物可能包含函数名、路径或业务数据，必须受访问控制、短保留和安全清理保护。

## 与现有技能的边界

- `observing-production-services` 负责观测信号设计、生产症状分诊和线上恢复；本技能使用其证据进行受控实验和优化验证。
- `testing-backend-services` 负责功能、集成和回归测试实现；本技能定义性能验证时必须保持的正确性检查。
- `designing-data-consistency` 负责事务、缓存、消息和补偿不变量；性能优化不得绕过这些约束。
- `designing-microservice-interfaces` 负责接口契约；本技能只分析接口运行开销和 payload/调用模式的性能影响。

## 验证策略

创建技能正文前运行不加载新技能的 RED 基线场景，保留代理输出，确认其会遗漏关键证据、实验控制、安全边界或交付字段；仅确认目标文件不存在不算 RED。GREEN 阶段对同类场景加载新技能重新执行，并至少覆盖：

- Go 服务 p99 升高，使用 benchmark、CPU/heap/mutex/block profile 区分 allocation、GC、锁和下游等待。
- PHP-FPM 饱和或长驻 CLI worker 内存增长，区分请求峰值、进程 RSS、OPcache 和 worker 生命周期。
- MySQL 查询/索引/连接池瓶颈与 Redis hot key、big key、命令复杂度和回源压力。
- Kafka/RabbitMQ consumer batch、并发、partition/queue 分布、背压和积压恢复时间。
- open/closed workload、warm-up、coordinated omission、错误率和尾延迟均被纳入压测设计。
- 容量模型包含单实例吞吐、目标利用率、故障余量、增长率、饱和点和重新校准条件。
- 优化方案无法保持正确性或实验不可比时，拒绝给出虚假提升结论。

每个 RED/GREEN 场景使用可观察断言判定，而不是主观评价文风；断言至少检查交付物完整性、同条件对比、正确性守恒、生产安全和不确定性表达。结构校验包括 YAML frontmatter、UI 元数据、六个 direct reference、长文目录、关键词、文件集、占位符、尾随空白、代码围栏和 reference 无嵌套引用。官方 validator 无法运行时，记录具体依赖错误，并使用可用 YAML 解析器完成等价结构校验。

## 完成标准

- 技能能在性能诊断、实验设计、优化评审和容量规划四种模式间选择正确交付契约。
- Go、PHP、data stores、messaging、load testing 和方法论均有独立 reference。
- 每个性能结论包含可比较基线、证据、实验控制、正确性检查和不确定性。
- 生产压测、在线 profiling、数据一致性和高风险参数变更边界明确。
- 所有静态结构与场景契约通过，并如实记录环境相关验证限制。
