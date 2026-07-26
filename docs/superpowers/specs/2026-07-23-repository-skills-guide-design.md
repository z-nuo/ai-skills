# 仓库 Skill 说明文档设计

## 状态

已确认，待实现。

## 日期

2026-07-23

## 背景

当前仓库包含 6 个 Skill，但根目录没有统一入口文档。使用者需要逐个打开 `SKILL.md` 才能判断应该选择哪个 Skill，维护者也缺少一份说明目录约定、职责边界和组合关系的仓库级文档。

本次工作将新增根目录 `README.md`，同时服务 Skill 使用者和维护者。文档基于仓库当前文件，不改变现有 Skill、reference 或 agent 配置。

## 目标

- 让使用者从任务场景快速定位正确的 Skill。
- 说明每个 Skill 的职责、典型触发、默认交付物和直接引用资料。
- 解释后端工程 Skill 之间的边界与组合顺序。
- 说明标准目录结构和新增、维护 Skill 时应遵守的约定。
- 如实记录盘点发现的仓库完整性问题。

## 非目标

- 不重写或重新分类现有 Skill 内容。
- 不移动、重命名或删除现有文件。
- 不补写缺失的 reference。
- 不将 `.superpowers/` 内的过程记录作为用户文档的一部分。
- 不引入文档生成器或机器可读索引。

## 目标读者

### Skill 使用者

希望根据当前任务快速选择 Skill，并了解 Skill 能提供什么、何时需要组合其他 Skill。

### Skill 维护者

希望理解仓库结构、文件职责、引用规则和维护检查项，能够以一致方式增加或更新 Skill。

## 方案选择

采用单一根目录 `README.md`。

相较于只放在 `docs/SKILLS.md`，根目录 README 更容易发现；相较于拆分为多份文档，单文件更符合当前 6 个 Skill 的规模和“一份说明文档”的要求。暂不引入自动生成机制，因为仓库规模较小，生成链路会增加维护成本。

## 信息架构

README 按“先选择、后深入、再维护”的顺序组织：

1. **仓库简介**：说明仓库用途、主要技术范围和当前 Skill 数量。
2. **快速选择**：用表格列出任务场景、推荐 Skill 和主要产出。
3. **能力关系**：说明 `chinese-communication` 是全局交互规范，其余 5 个 Skill 覆盖接口、一致性、测试、性能和生产观测。
4. **Skill 详解**：逐项说明用途、适用场景、默认输出及 references。
5. **组合使用**：提供典型跨领域流程，明确主 Skill 与辅助 Skill 的选择原则。
6. **仓库结构**：展示标准 Skill 目录树，并解释 `SKILL.md`、`references/` 和 `agents/openai.yaml` 的职责。
7. **维护约定**：说明元数据、渐进式引用、职责边界、交付物、红线和验证要求。
8. **完整性说明**：记录当前已知的缺失引用，避免用户误以为相应资料存在。

## Skill 分类

| 分类 | Skill | 定位 |
|---|---|---|
| 交互规范 | `chinese-communication` | 控制面向用户的默认沟通语言及例外规则 |
| 接口设计 | `designing-microservice-interfaces` | 设计和评审 REST、gRPC 契约及兼容性迁移 |
| 数据可靠性 | `designing-data-consistency` | 设计 MySQL、Redis、消息系统和跨服务一致性 |
| 测试保障 | `testing-backend-services` | 为 Go/PHP 后端编写和评审单元、集成与契约测试 |
| 性能工程 | `optimizing-backend-performance` | 诊断和验证 Go/PHP 后端性能及容量 |
| 生产运行 | `observing-production-services` | 设计可观测性、诊断生产故障并验证恢复 |

## 组合关系

README 将使用以下原则描述 Skill 组合：

- 先确定用户请求的主问题，只选择一个主 Skill。
- 当任务跨越公共契约、一致性、测试、性能或生产运行边界时，再加载对应辅助 Skill。
- 接口契约由 `designing-microservice-interfaces` 所有。
- 跨存储和消息的数据正确性由 `designing-data-consistency` 所有。
- 测试实现与测试质量由 `testing-backend-services` 所有。
- 性能证据、实验和容量由 `optimizing-backend-performance` 所有。
- 生产影响、观测信号和事故恢复由 `observing-production-services` 所有。

典型流程示例包括：

- 新微服务能力：接口设计 -> 一致性设计 -> 测试 -> 性能验证 -> 生产观测。
- 生产延迟问题：生产观测定位影响 -> 性能 Skill 验证瓶颈 -> 测试 Skill 补回归保障。
- 重复消费问题：生产观测收集证据 -> 一致性 Skill 设计幂等与恢复 -> 测试 Skill 覆盖重复和乱序。

## 内容来源与准确性

- Skill 名称、description、职责、工作流和边界以各目录的 `SKILL.md` 为事实来源。
- reference 列表以实际存在文件为准，并对 `SKILL.md` 中引用但不存在的文件单独标记。
- OpenAI 展示名称和默认提示词以 `agents/openai.yaml` 为事实来源。
- README 使用相对链接，确保在仓库浏览器和本地 Markdown 查看器中可导航。
- 不复制 reference 的大段细节，只概括其主题，减少重复与内容漂移。

## 当前完整性发现

盘点时确认：

- 仓库包含 6 个 `SKILL.md`。
- 5 个后端工程 Skill 包含 `agents/openai.yaml`。
- 实际存在 20 个 reference 文件。
- `optimizing-backend-performance/SKILL.md` 引用了 `references/load-testing.md`，但该文件当前不存在。
- `chinese-communication` 是轻量全局规范，没有 `references/` 或 `agents/openai.yaml`。

README 会如实标记缺失的 `load-testing.md`，但本次不擅自补写该文件。

## 验证标准

- README 中列出全部 6 个 Skill，名称与目录一致。
- 所有已提供的相对链接均指向存在的文件。
- 缺失的 `load-testing.md` 不生成死链接，而是标记为“当前缺失”。
- 快速选择表覆盖接口、一致性、测试、性能、可观测性和语言规范六类场景。
- 每个 Skill 至少说明用途、典型场景、默认输出和 reference 范围。
- 文档同时包含使用导航、组合关系、目录结构和维护约定。
- 文档不包含占位符、未决设计或与当前仓库不一致的数量。

