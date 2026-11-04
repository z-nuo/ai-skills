# Skills

仓库地址：[GitHub](https://github.com/z-nuo/ai-skills) ｜ [Gitee 镜像](https://gitee.com/z-nuo/skills)

面向 Go/PHP 后端工程与高质量交互的 Codex Skill 集合。仓库当前包含 10 个 Skill，覆盖沟通规范、需求澄清、改动范围、微服务接口、数据一致性、自动化测试、性能工程、生产可观测性、Freetalk 多仓库业务分析和 PHP 发布审查。

## 快速选择

| 你的任务 | 推荐 Skill | 主要产出 |
|---|---|---|
| 默认使用中文与用户沟通 | [`chinese-communication`](chinese-communication/SKILL.md) | 语言选择与交付物语言边界 |
| 在复杂或模糊问题前逐步澄清需求 | [`clarifying-before-solving`](clarifying-before-solving/SKILL.md) | 单问题访谈、需求确认与显式假设 |
| 生成必要且简洁的代码、减少多余判断、限制改动范围 | [`minimal-change-guard`](minimal-change-guard/SKILL.md) | 逐段必要性检查、重复校验与封装清理、必要调用链修正 |
| 定位 Freetalk PHP 项目、分析完整业务或跨仓库参数变化 | [`working-with-freetalk-php`](working-with-freetalk-php/SKILL.md) | 项目入口、依赖版本、业务链路、资源边界与交接文档 |
| 判断 PHP 当前改动能否上线、更新后重新检查 | [`reviewing-php-release-readiness`](reviewing-php-release-readiness/SKILL.md) | 基线、调用方、语法与 token 验证、发布门禁及部署条件 |
| 设计或评审 REST/gRPC 接口 | [`designing-microservice-interfaces`](designing-microservice-interfaces/SKILL.md) | 契约、兼容性、迁移与验证 |
| 设计跨存储或消息的数据一致性 | [`designing-data-consistency`](designing-data-consistency/SKILL.md) | 不变量、失败矩阵、恢复与对账 |
| 编写或评审 Go/PHP 后端测试 | [`testing-backend-services`](testing-backend-services/SKILL.md) | 测试矩阵、RED/GREEN 证据与风险 |
| 诊断性能或规划容量 | [`optimizing-backend-performance`](optimizing-backend-performance/SKILL.md) | 基线、假设、实验与回滚 |
| 设计观测或排查生产故障 | [`observing-production-services`](observing-production-services/SKILL.md) | SLI/SLO、遥测、时间线与恢复验证 |

`chinese-communication`、`clarifying-before-solving` 和 `minimal-change-guard` 分别负责沟通语言、需求澄清与改动范围。5 个通用后端领域 Skill 负责设计、测试、性能和观测；`working-with-freetalk-php` 提供项目定位与业务链路，`reviewing-php-release-readiness` 负责 PHP 发布结论。跨领域任务先确定主问题，再按职责边界组合。

使用时可直接在请求中指定 Skill，例如：

```text
使用 $designing-microservice-interfaces 评审这份 OpenAPI 契约的兼容性。
```

## 能力范围

### `chinese-communication`

- **用途**：控制面向用户的默认沟通语言，同时区分对话语言与交付物目标语言。
- **适用场景**：开始或继续任何用户交互；处理英文邮件、翻译、代码注释等具有单独语言要求的交付物。
- **默认输出**：对话默认使用简体中文；代码、命令、路径、标识符和原始报错保持原文；显式语言要求优先。
- **参考资料**：没有独立 reference，全部规则位于 [`SKILL.md`](chinese-communication/SKILL.md)。
- **OpenAI 配置**：当前没有 `agents/openai.yaml`。

### `clarifying-before-solving`

- **用途**：在形成方案前识别并补齐会改变决策方向的需求信息。
- **适用场景**：复杂、模糊、信息不足、高影响或存在多种合理解释的问题，以及用户明确要求先澄清需求的任务。
- **默认输出**：每轮一个最高价值问题；信息充分后先复述需求并等待确认；用户要求直接回答时列出假设后给出当前最佳方案。
- **参考资料**：没有独立 reference，全部规则位于 [`SKILL.md`](clarifying-before-solving/SKILL.md)。
- **OpenAI 配置**：[`agents/openai.yaml`](clarifying-before-solving/agents/openai.yaml)。

### `minimal-change-guard`

- **用途**：要求每段生成的代码都有需求、已确认契约或具体失败风险依据，非必要不生成，同时控制改动范围。
- **适用场景**：新增、修改、删除、废弃或替换代码；要求代码简洁、减少判断、限制文件范围，或只读审查过度设计与冗余。
- **默认输出**：必要实现及其依据、范围和冗余检查、实际验证结果及未覆盖边界；保留必要鉴权、防重与失败处理。
- **参考资料**：没有独立 reference，全部规则位于 [`SKILL.md`](minimal-change-guard/SKILL.md)。
- **OpenAI 配置**：[`agents/openai.yaml`](minimal-change-guard/agents/openai.yaml)。

### `working-with-freetalk-php`

- **用途**：在 Freetalk PHP 项目间定位入口、依赖和业务数据变化，形成有源码证据的调用链或交接文档。
- **适用场景**：`client_activity`、`backendV2`、`shop`、`family`、`prize_server` 与公共包的跨仓库分析、参数异常、发奖链路、功能废弃和 QConf/Redis 依赖追踪。
- **默认输出**：入口与字段变化、下游副作用、配置/key、失败语义、源码索引及运行验证边界。
- **参考资料**：[`项目地图`](working-with-freetalk-php/references/project-map.md)、[`业务链路`](working-with-freetalk-php/references/business-flows.md)、[`资源与运行边界`](working-with-freetalk-php/references/resources-and-runtime.md)。按当前问题加载。
- **OpenAI 配置**：[`agents/openai.yaml`](working-with-freetalk-php/agents/openai.yaml)。

### `reviewing-php-release-readiness`

- **用途**：对 PHP 当前工作树、指定提交或分支给出源码层发布门禁，区分本次新增问题与已有问题。
- **适用场景**：判断能否上线、更新后复审、删除闭环检查、注释/格式改动、旧 PHP 版本兼容和进程部署条件。
- **默认输出**：当前基线、按严重程度排列的发现、实际验证、发布条件与线上未验证项。审查默认只读。
- **参考资料**：[`验证方法与发布边界`](reviewing-php-release-readiness/references/verification.md)；[`非注释 token 比较脚本`](reviewing-php-release-readiness/scripts/compare_php_tokens.php) 只读取文件，退出码区分一致、不同和错误。
- **OpenAI 配置**：[`agents/openai.yaml`](reviewing-php-release-readiness/agents/openai.yaml)。

### `designing-microservice-interfaces`

- **用途**：以契约优先方式设计或评审 REST、gRPC 接口，并规划可兼容的演进与迁移。
- **适用场景**：新增 API/RPC、评审 OpenAPI/Protobuf、定义错误与幂等语义、分析破坏性变更、设计弃用和迁移过程。
- **默认输出**：服务边界、接口决策、OpenAPI 3.1 或 Protobuf 契约、横切约束、兼容性方案和验证结果。
- **参考资料**：[`REST 与 OpenAPI`](designing-microservice-interfaces/references/rest.md)、[`gRPC 与 Protobuf`](designing-microservice-interfaces/references/grpc.md)、[`接口兼容性与迁移`](designing-microservice-interfaces/references/compatibility.md)。
- **OpenAI 配置**：[`agents/openai.yaml`](designing-microservice-interfaces/agents/openai.yaml)。

### `designing-data-consistency`

- **用途**：从业务不变量和失败恢复出发，设计跨 MySQL、Redis、消息队列和服务边界的数据一致性。
- **适用场景**：事务边界、缓存失效、Outbox/Inbox、幂等消费、重试与死信、Saga、补偿和对账设计或评审。
- **默认输出**：一致性目标、所有权与事务边界、正常流程、失败矩阵、数据与消息契约、恢复与对账、观测和测试策略。
- **参考资料**：[`MySQL`](designing-data-consistency/references/mysql.md)、[`Redis`](designing-data-consistency/references/redis.md)、[`消息投递`](designing-data-consistency/references/messaging.md)、[`分布式一致性模式`](designing-data-consistency/references/patterns.md)。
- **OpenAI 配置**：[`agents/openai.yaml`](designing-data-consistency/agents/openai.yaml)。

### `testing-backend-services`

- **用途**：用最小且可信的自动化测试证明 Go/PHP 后端行为，或评审已有测试的检测能力与稳定性。
- **适用场景**：新行为与缺陷修复的 TDD、既有行为的特征测试、单元/集成/API/契约测试、测试隔离和质量评审。
- **默认输出**：测试目标、边界与用例矩阵、测试代码及环境、RED/GREEN 证据和剩余风险。
- **参考资料**：[`Go`](testing-backend-services/references/go.md)、[`PHP`](testing-backend-services/references/php.md)、[`Testcontainers 与集成测试`](testing-backend-services/references/integration.md)。PHP 参考包含旧版本与脚本式项目的验证边界。
- **OpenAI 配置**：[`agents/openai.yaml`](testing-backend-services/agents/openai.yaml)。

### `optimizing-backend-performance`

- **用途**：基于可复现证据诊断和改善 Go/PHP 后端的延迟、吞吐、资源使用、消息积压与容量。
- **适用场景**：性能诊断、单变量实验、优化方案评审、压测设计、MySQL/Redis 或 Kafka/RabbitMQ 瓶颈分析、容量规划。
- **默认输出**：目标差距、workload 与基线、假设表、实验设计、同条件结果、正确性与资源成本、回滚和重新校准条件。
- **参考资料**：[`性能方法论`](optimizing-backend-performance/references/methodology.md)、[`Go`](optimizing-backend-performance/references/go.md)、[`PHP`](optimizing-backend-performance/references/php.md)、[`MySQL 与 Redis`](optimizing-backend-performance/references/data-stores.md)、[`Kafka 与 RabbitMQ`](optimizing-backend-performance/references/messaging.md)、[`压测与容量验证`](optimizing-backend-performance/references/load-testing.md)。
- **OpenAI 配置**：[`agents/openai.yaml`](optimizing-backend-performance/agents/openai.yaml)。

### `observing-production-services`

- **用途**：设计厂商中立的生产可观测性，或基于影响、时间线和证据诊断线上故障并验证恢复。
- **适用场景**：SLI/SLO、日志/指标/Trace、Dashboard 与告警设计，Go/PHP 生产问题分析，依赖组件诊断，事故分诊与观测补强。
- **默认输出**：影响与基线、事实和未知项、假设与下一检查、止损及回滚条件、恢复信号，以及后续观测与负责人建议。
- **参考资料**：[`Telemetry`](observing-production-services/references/telemetry.md)、[`Go`](observing-production-services/references/go.md)、[`PHP`](observing-production-services/references/php.md)、[`依赖组件`](observing-production-services/references/dependencies.md)、[`事故分诊与恢复`](observing-production-services/references/incidents.md)。
- **OpenAI 配置**：[`agents/openai.yaml`](observing-production-services/agents/openai.yaml)。

## 组合使用

组合多个 Skill 时，先选择拥有主要问题和最终交付物的主 Skill，再按实际跨越的边界加载辅助 Skill。不要为了“覆盖全面”而无条件加载全部 Skill。

任务复杂、模糊或缺少关键约束时，先用 `clarifying-before-solving` 确认真实需求，再选择负责最终交付物的领域 Skill。简单明确的任务直接进入领域 Skill。

新增或修改代码时，使用 `minimal-change-guard` 逐段核对必要性与范围：同一边界内的输入校验一次，复用下游规则和 wrapper，查询与抽象必须有当前用途。具体规则以该技能为准，再按实际需要配合领域 Skill；只读审查不自动修改代码，也不要求额外审批已明确授权的工作。

### Freetalk 业务修改与发布

1. 用 `working-with-freetalk-php` 确认当前项目规则、入口、实际依赖版本、字段变换和下游副作用。
2. 已授权修改时，用 `minimal-change-guard` 控制写入范围；需要行为测试时选 `testing-backend-services`，先检查脚本副作用与运行版本。
3. 用户要求判断能否上线时，以 `reviewing-php-release-readiness` 复核当前完整补丁和调用闭环，给出源码发布门禁及部署条件。
4. 真实运行故障使用 `observing-production-services` 收集证据；源码结论不替代线上配置和到账验证。

### 新微服务能力

1. 用 `designing-microservice-interfaces` 明确服务边界和公共契约。
2. 涉及跨存储或异步消息时，用 `designing-data-consistency` 定义不变量、幂等和恢复。
3. 用 `testing-backend-services` 实现领域、基础设施和契约测试。
4. 有性能目标时，用 `optimizing-backend-performance` 建立基线并验证容量。
5. 上线前用 `observing-production-services` 设计 SLI/SLO、遥测、告警和 Runbook。

### 生产延迟问题

1. 以 `observing-production-services` 为主，界定影响、建立时间线并收集已有证据。
2. 证据指向可复现瓶颈后，用 `optimizing-backend-performance` 设计单变量实验和同条件复验。
3. 用 `testing-backend-services` 为修复补充行为与性能相关的回归保障。

### 重复消费问题

1. 用 `observing-production-services` 确认重复范围、时间线、消息与消费者信号。
2. 以 `designing-data-consistency` 设计 Inbox、唯一约束、幂等键、重试和对账恢复。
3. 用 `testing-backend-services` 覆盖重复、乱序、崩溃窗口和重试场景。
4. 消息契约需要变更时，再用 `designing-microservice-interfaces` 评估兼容性。

## 仓库结构

后端工程 Skill 通常采用以下结构；`references/` 和 `agents/` 可按实际需要省略：

```text
<skill-name>/
├── SKILL.md
├── agents/
│   └── openai.yaml
└── references/
    └── <topic>.md
```

需要可复用的确定性操作时可增加 `scripts/`，例如发布审查的 PHP token 比较器；脚本依赖与副作用应在技能中说明。

| 文件 | 职责 |
|---|---|
| `SKILL.md` | 定义 frontmatter、触发条件、核心工作流、职责边界、默认交付物与红线，是 Skill 的入口和事实来源 |
| `references/*.md` | 保存协议、语言、中间件或方法论的详细规则，由 `SKILL.md` 按当前任务渐进式加载 |
| `agents/openai.yaml` | 定义 OpenAI 界面中的展示名称、简短描述和默认调用提示词 |
| `scripts/` | 保存只在任务需要时运行的可复用工具，不代替业务验证 |

## 维护约定

1. **名称一致**：frontmatter 的 `name` 与目录名保持一致，使用小写 kebab-case。
2. **触发明确**：`description` 应说明何时使用，而不是只重复 Skill 名称或笼统描述能力。
3. **入口聚焦**：`SKILL.md` 保存决策流程、路由规则、职责边界和交付标准；大量领域细节拆入 reference。
4. **渐进加载**：每个 reference 应由 `SKILL.md` 直接引用，并说明加载条件；不要要求所有任务读取全部资料。
5. **边界唯一**：公共接口、一致性、测试、性能和生产运行分别由对应 Skill 所有，跨域任务通过组合解决。
6. **结果可验收**：写清默认交付物、验证命令、证据要求和无法验证时的报告方式。
7. **安全红线**：涉及生产操作、敏感数据、性能采集或破坏性变更时，明确授权、停止和回滚条件。
8. **界面同步**：新增 `agents/openai.yaml` 时，确保展示名称、短描述和默认提示词与 `SKILL.md` 的定位一致。
9. **链接验证**：新增、移动或删除 reference 后，检查 `SKILL.md` 和本 README 的全部相对链接。
10. **目录同步**：新增或移除 Skill 时，同步更新快速选择、能力范围、组合关系和完整性数量。

## 当前完整性说明

截至 2026-11-04，仓库盘点结果如下：

- 10 个 `SKILL.md`。
- 25 个实际存在的 reference 文件。
- 9 个 `agents/openai.yaml`；`chinese-communication` 当前没有 OpenAI 界面配置。
- 1 个只读 PHP token 比较脚本。
- 已补齐性能技能引用的 `references/load-testing.md`。
- 本轮项目覆盖、源码依据和技能更新范围见 [`项目与技能分析`](docs/analysis/2026-11-04-php-project-skill-analysis.md)。

本仓库是技能源文件集合。更新这里不会自动替换各机器已安装的技能；按现有安装方式安装或同步需要的技能目录后生效。
