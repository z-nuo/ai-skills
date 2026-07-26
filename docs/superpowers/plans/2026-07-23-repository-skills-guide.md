# Repository Skills Guide Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 新增一份根目录 `README.md`，帮助使用者选择仓库中的 Skill，并帮助维护者理解目录、边界与维护约定。

**Architecture:** 使用单一 Markdown 文件作为仓库入口，按“快速选择、Skill 详解、组合使用、仓库维护”组织内容。信息只来源于现有 `SKILL.md`、`references/` 和 `agents/openai.yaml`，不复制 reference 的大段正文，也不修改现有 Skill。

**Tech Stack:** Markdown、POSIX shell、Git

## Global Constraints

- 只新增根目录 `README.md`，不修改、移动或删除现有 Skill 文件。
- 文档同时面向 Skill 使用者和维护者。
- 列出全部 6 个 Skill，并使用与目录一致的名称。
- 相对链接只指向实际存在的文件。
- `optimizing-backend-performance/references/load-testing.md` 必须标记为当前缺失，不生成死链接。
- `.superpowers/` 中的过程文件不进入 README 的仓库能力介绍。
- 不引入文档生成器、依赖或机器可读索引。

---

### Task 1: 创建并验证仓库 Skill 说明文档

**Files:**
- Create: `README.md`
- Reference: `docs/superpowers/specs/2026-07-23-repository-skills-guide-design.md`
- Reference: `*/SKILL.md`
- Reference: `*/references/*.md`
- Reference: `*/agents/openai.yaml`

**Interfaces:**
- Consumes: 6 个 Skill 的名称、description、职责边界、默认交付物、直接 reference 和 OpenAI 界面配置。
- Produces: 仓库级 Markdown 导航入口 `README.md`。

- [x] **Step 1: 写入 README 的使用导航**

创建 `README.md`，开头按以下顺序组织：

```markdown
# Skills

面向 Go/PHP 后端工程与中文交互的 Codex Skill 集合。

## 快速选择

| 你的任务 | 推荐 Skill | 主要产出 |
|---|---|---|
| 默认使用中文与用户沟通 | `chinese-communication` | 语言选择与交付物语言边界 |
| 设计或评审 REST/gRPC 接口 | `designing-microservice-interfaces` | 契约、兼容性、迁移与验证 |
| 设计跨存储或消息的数据一致性 | `designing-data-consistency` | 不变量、失败矩阵、恢复与对账 |
| 编写或评审 Go/PHP 后端测试 | `testing-backend-services` | 测试矩阵、RED/GREEN 证据与风险 |
| 诊断性能或规划容量 | `optimizing-backend-performance` | 基线、假设、实验与回滚 |
| 设计观测或排查生产故障 | `observing-production-services` | SLI/SLO、遥测、时间线与恢复验证 |
```

在表格后说明：`chinese-communication` 是全局交互规范；其余 5 个 Skill 分别负责接口、一致性、测试、性能和生产运行。

- [x] **Step 2: 补齐 6 个 Skill 的详细说明**

每个 Skill 使用同一字段顺序：

```markdown
### `<skill-name>`

- **用途**：一句话说明职责。
- **适用场景**：列出典型触发任务。
- **默认输出**：概括主要交付物。
- **参考资料**：使用相对链接列出实际存在的 reference；没有 reference 时明确说明。
- **OpenAI 配置**：链接存在的 `agents/openai.yaml`；没有配置时明确说明。
```

内容必须覆盖：

- `chinese-communication`：默认简体中文、显式语言要求和交付物语言边界。
- `designing-microservice-interfaces`：REST、gRPC、兼容性与迁移。
- `designing-data-consistency`：MySQL、Redis、消息投递和一致性模式。
- `testing-backend-services`：Go、PHP、Testcontainers 与契约测试。
- `optimizing-backend-performance`：方法论、Go、PHP、MySQL/Redis、Kafka/RabbitMQ；另以纯文本标记 `load-testing.md` 当前缺失。
- `observing-production-services`：Telemetry、Go、PHP、依赖组件和事故处理。

- [x] **Step 3: 添加组合流程与维护说明**

在 Skill 详解后新增：

```markdown
## 组合使用
## 仓库结构
## 维护约定
## 当前完整性说明
```

“组合使用”包含三条完整流程：新微服务能力、生产延迟问题、重复消费问题；强调先选一个主 Skill，再按职责边界加载辅助 Skill。

“仓库结构”展示标准目录：

```text
<skill-name>/
├── SKILL.md
├── agents/
│   └── openai.yaml
└── references/
    └── <topic>.md
```

“维护约定”至少包括 frontmatter、触发 description、渐进式加载 reference、职责边界、默认交付物、红线、安全约束和链接验证。

“当前完整性说明”记录 6 个 `SKILL.md`、20 个实际 reference、5 个 `agents/openai.yaml`，以及缺失的 `optimizing-backend-performance/references/load-testing.md`。

- [x] **Step 4: 验证 Skill 清单与关键内容**

运行：

```bash
rg -o '`(chinese-communication|designing-data-consistency|designing-microservice-interfaces|observing-production-services|optimizing-backend-performance|testing-backend-services)`' README.md | sort -u
```

预期：输出全部 6 个唯一 Skill 名称。

运行：

```bash
rg -n 'load-testing\.md.*当前缺失|当前缺失.*load-testing\.md' README.md
```

预期：至少匹配一行，且 `load-testing.md` 不是 Markdown 链接。

- [x] **Step 5: 验证 Markdown 相对链接**

提取 `README.md` 中所有本地 Markdown 链接，并逐一检查目标存在。预期输出为空且命令退出码为 0：

```bash
perl -ne 'while (/\[[^]]+\]\(([^)#]+)(?:#[^)]+)?\)/g) { print "$1\n" if $1 !~ m{^[a-z]+://} }' README.md |
while IFS= read -r path; do
  test -e "$path" || { printf 'missing: %s\n' "$path"; exit 1; }
done
```

运行 Markdown 空白检查：

```bash
git diff --check -- README.md
```

预期：无输出，退出码为 0。

- [x] **Step 6: 检查范围并创建文档提交**

运行：

```bash
git diff -- README.md
git status --short
```

确认 README 之外的既有未提交文件没有被修改或暂存。若环境允许写入 Git 索引，仅提交 README：

```bash
git add README.md
git diff --staged --check
git commit -m "docs: add repository skills guide"
```

若 Git 索引只读，保留未暂存的 `README.md`，并在交付说明中明确报告该限制。
