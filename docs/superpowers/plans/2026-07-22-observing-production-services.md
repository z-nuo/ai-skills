# Observing Production Services Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 创建一个面向 Go/PHP 后端服务的厂商中立可观测性与线上故障排查技能。

**Architecture:** 使用精简的 `SKILL.md` 定义统一工作流、三种任务模式和交付契约；将 telemetry、Go、PHP、依赖组件和事故处理细节拆到五个可按需加载的 reference。使用静态 RED-GREEN 契约验证发现能力、内容覆盖、文件引用、YAML 元数据和文本卫生。

**Tech Stack:** Markdown、YAML、Ruby 标准库 YAML、OpenTelemetry 语义、Prometheus 指标模型

## Global Constraints

- 所有说明性正文使用简体中文，代码、命令、指标名和技术标识保持原文。
- 技能名固定为 `observing-production-services`，只使用小写字母和连字符。
- 保持厂商中立，不要求 Grafana、Loki、Tempo、ELK 或任何商业平台。
- 覆盖 Go、PHP、HTTP/gRPC、MySQL、Redis、Kafka、RabbitMQ；Kubernetes 仅作为运行证据来源。
- 线上诊断默认只读；任何配置变更、重启、kill、扩缩容、清队列或重放都要求显式授权。
- 指标 label 必须有限基数，日志和 Trace 不得包含未脱敏的凭据或个人数据。
- `SKILL.md` 少于 500 行；超过 100 行的 reference 必须包含 `## 目录`。
- 所有 reference 只由 `SKILL.md` 直接引用，不创建嵌套引用。
- 不创建 README、安装指南、变更日志或与技能运行无关的文件。
- 当前 `.git` 目录只读，实施期间不执行 `git add` 或 `git commit`；最终报告未提交状态。
- 不使用子代理做前向测试；使用静态场景契约并明确记录该验证限制。

---

### Task 1: 建立 RED 契约并创建核心技能

**Files:**
- Create: `observing-production-services/SKILL.md`
- Create: `observing-production-services/agents/openai.yaml`

**Interfaces:**
- Consumes: `docs/superpowers/specs/2026-07-22-observing-production-services-design.md`
- Produces: 技能触发描述、统一工作流、五个 direct reference 路由、三种输出契约和 UI 元数据

- [ ] **Step 1: 运行目标文件缺失契约，确认 RED**

Run:

```bash
ruby -e 'files=%w[observing-production-services/SKILL.md observing-production-services/agents/openai.yaml observing-production-services/references/telemetry.md observing-production-services/references/go.md observing-production-services/references/php.md observing-production-services/references/dependencies.md observing-production-services/references/incidents.md]; missing=files.reject{|f|File.file?(f)}; abort("missing: #{missing.join(", ")}") unless missing.empty?'
```

Expected: FAIL，错误列出全部 7 个缺失文件。

- [ ] **Step 2: 使用官方初始化器创建目录骨架**

Run:

```bash
python3 /Users/wuhui/.codex/skills/.system/skill-creator/scripts/init_skill.py observing-production-services --path . --resources references --interface 'display_name=Production Observability' --interface 'short_description=Design telemetry and diagnose backend incidents' --interface 'default_prompt=Use $observing-production-services to design observability or diagnose this production issue.'
```

Expected: 创建技能目录、`SKILL.md`、`agents/openai.yaml` 和 `references/`。如果仅因缺少 `PyYAML` 失败，保留完整错误证据，随后使用 `apply_patch` 创建相同文件结构，不安装未经授权的依赖。

- [ ] **Step 3: 写入核心 `SKILL.md`**

Frontmatter 使用以下精确值：

```yaml
---
name: observing-production-services
description: Use when designing observability for Go or PHP backend services, diagnosing production latency, errors, saturation, or message backlog, reviewing logs, metrics, traces, dashboards, and alerts, or strengthening telemetry after an incident.
---
```

正文依次包含：

1. `# 生产服务可观测性与故障排查`
2. 核心原则：先影响后组件、先事实后假设、先可回滚止损后永久修复、恢复必须以信号验证。
3. 工作流：范围与基线、上下文、时间线、假设表、止损、恢复验证、观测补强。
4. 五个 direct reference 链接及加载条件。
5. 可观测性设计、线上故障排查、现有方案评审三种任务模式。
6. 与 `designing-microservice-interfaces`、`testing-backend-services`、`designing-data-consistency` 的明确边界。
7. 默认交付物和线上操作、安全、高基数红线。

- [ ] **Step 4: 写入 UI 元数据**

`agents/openai.yaml` 使用：

```yaml
interface:
  display_name: "Production Observability"
  short_description: "Design telemetry and diagnose backend incidents"
  default_prompt: "Use $observing-production-services to design observability or diagnose this production issue."
```

- [ ] **Step 5: 运行核心契约，确认 GREEN**

Run:

```bash
ruby -ryaml -e 'p="observing-production-services/SKILL.md"; s=File.read(p); m=s.match(/\A---\n(.*?)\n---\n/m) or abort("frontmatter"); y=YAML.safe_load(m[1]); abort("name") unless y["name"]=="observing-production-services"; abort("description") unless y["description"].start_with?("Use when"); %w[影响 时间线 假设 止损 恢复 可观测性设计 线上故障排查 现有方案评审].each{|w|abort("missing #{w}") unless s.include?(w)}; ui=YAML.safe_load(File.read("observing-production-services/agents/openai.yaml")); abort("prompt") unless ui.dig("interface","default_prompt").include?("$observing-production-services"); puts "core contract PASS"'
```

Expected: `core contract PASS`。

---

### Task 2: 创建 telemetry 信号设计参考

**Files:**
- Create: `observing-production-services/references/telemetry.md`
- Modify: `observing-production-services/SKILL.md`

**Interfaces:**
- Consumes: 核心工作流中的可观测性设计模式
- Produces: 日志、指标、Trace、Dashboard、告警、采样和成本控制规则

- [ ] **Step 1: 运行 telemetry 内容契约，确认 RED**

Run:

```bash
ruby -e 'p="observing-production-services/references/telemetry.md"; abort("missing telemetry.md") unless File.file?(p); s=File.read(p); %w[SLI SLO RED USE counter histogram trace_id tail\ sampling cardinality Dashboard Runbook].each{|w|w=w.tr("\\"," "); abort("missing #{w}") unless s.include?(w)}'
```

Expected: FAIL，提示 `telemetry.md` 不存在或缺少首个关键词。

- [ ] **Step 2: 写入 telemetry 参考**

文档包含 `## 目录` 及以下章节：

- 从用户路径定义 SLI/SLO，并用 RED 描述请求、USE 描述资源。
- 结构化日志的稳定 event name、severity、service、version、operation、trace/correlation、error class 字段。
- counter、gauge、histogram 的选择，单位、命名、bucket 和聚合原则。
- Trace span 边界、context propagation、status、attribute、link 和跨消息队列关联。
- head/tail sampling，错误、慢请求和关键业务路径保留策略。
- Dashboard 从用户症状到服务、依赖、实例/runtime 的下钻顺序。
- 告警基于 SLO burn rate、用户症状、饱和度和需要人工处理的积压，并关联 Runbook。
- cardinality、存储成本、敏感数据、时钟和 telemetry pipeline 故障边界。
- 明确 request ID、user ID、SQL、原始 URL 参数和完整错误文本不得进入指标 label。

- [ ] **Step 3: 在 `SKILL.md` 中确认 direct reference 路由**

添加或确认以下直接链接只出现一次：

```markdown
- 日志、指标、Trace、Dashboard、告警和采样读取 [references/telemetry.md](references/telemetry.md)。
```

- [ ] **Step 4: 重跑 telemetry 契约，确认 GREEN**

Run: Step 1 命令。

Expected: PASS，exit 0。

---

### Task 3: 创建 Go 生产诊断参考

**Files:**
- Create: `observing-production-services/references/go.md`
- Modify: `observing-production-services/SKILL.md`

**Interfaces:**
- Consumes: telemetry 命名、关联和安全规则
- Produces: Go runtime、HTTP/gRPC、连接池和 pprof 的证据导向诊断路径

- [ ] **Step 1: 运行 Go 内容契约，确认 RED**

Run:

```bash
ruby -e 'p="observing-production-services/references/go.md"; abort("missing go.md") unless File.file?(p); s=File.read(p); ["runtime/metrics","goroutine","GC","heap","pprof","HTTP","gRPC","连接池","context deadline","认证"].each{|w|abort("missing #{w}") unless s.include?(w)}'
```

Expected: FAIL，提示 `go.md` 不存在或缺少首个关键词。

- [ ] **Step 2: 写入 Go 参考**

文档章节覆盖：

- HTTP/gRPC request rate、error、duration、in-flight 与 status/error class。
- `runtime/metrics` 中 goroutine、heap、allocation、GC pause、CPU 和 scheduler 信号的关联方式。
- goroutine 泄漏、锁竞争、连接池等待、下游超时和 context cancellation 的区分。
- pprof CPU、heap、goroutine、mutex、block profile 的选择及低开销采集原则。
- debug/pprof endpoint 的认证、网络隔离、审计和限时暴露。
- 延迟升高场景的证据顺序：发布变化 -> 流量/错误 -> runtime -> pool -> 下游 -> profile。
- 不从单次 heap、goroutine 数或 CPU spike 直接宣布根因。

- [ ] **Step 3: 添加 Go direct reference 路由并确认 GREEN**

在 `SKILL.md` 添加：

```markdown
- Go HTTP/gRPC、runtime、goroutine、GC 和 pprof 读取 [references/go.md](references/go.md)。
```

Run: Step 1 命令。

Expected: PASS，exit 0。

---

### Task 4: 创建 PHP 生产诊断参考

**Files:**
- Create: `observing-production-services/references/php.md`
- Modify: `observing-production-services/SKILL.md`

**Interfaces:**
- Consumes: telemetry 命名、关联和安全规则
- Produces: PHP-FPM、CLI worker、OPcache、内存和异常的诊断路径

- [ ] **Step 1: 运行 PHP 内容契约，确认 RED**

Run:

```bash
ruby -e 'p="observing-production-services/references/php.md"; abort("missing php.md") unless File.file?(p); s=File.read(p); ["PHP-FPM","max_children","listen queue","slowlog","OPcache","memory_get_usage","CLI worker","异常","request duration","重启"].each{|w|abort("missing #{w}") unless s.include?(w)}'
```

Expected: FAIL，提示 `php.md` 不存在或缺少首个关键词。

- [ ] **Step 2: 写入 PHP 参考**

文档章节覆盖：

- PHP-FPM active/idle/total process、max children reached、listen queue 和 request duration。
- slowlog、upstream access log、应用结构化日志及 trace ID 的时间线关联。
- OPcache 命中、重启、内存和代码发布变化的判断边界。
- `memory_get_usage`、进程 RSS、请求峰值和长驻 CLI worker 增长趋势的区别。
- CLI worker 消费速率、失败、重试、心跳、任务耗时和安全回收策略。
- 异常分类、HTTP status、超时、客户端取消和 fatal error 的区分。
- 不用无证据的周期重启掩盖泄漏或阻塞；重启前保留必要现场。

- [ ] **Step 3: 添加 PHP direct reference 路由并确认 GREEN**

在 `SKILL.md` 添加：

```markdown
- PHP-FPM、CLI worker、OPcache、内存和异常读取 [references/php.md](references/php.md)。
```

Run: Step 1 命令。

Expected: PASS，exit 0。

---

### Task 5: 创建依赖组件诊断参考

**Files:**
- Create: `observing-production-services/references/dependencies.md`
- Modify: `observing-production-services/SKILL.md`

**Interfaces:**
- Consumes: 请求时间线和 trace/correlation 传播规则
- Produces: MySQL、Redis、Kafka、RabbitMQ 的指标、证据和常见误判章节

- [ ] **Step 1: 运行依赖内容契约，确认 RED**

Run:

```bash
ruby -e 'p="observing-production-services/references/dependencies.md"; abort("missing dependencies.md") unless File.file?(p); s=File.read(p); ["MySQL","连接池","slow query","lock wait","Redis","eviction","hot key","Kafka","consumer lag","rebalance","RabbitMQ","messages_ready","messages_unacknowledged"].each{|w|abort("missing #{w}") unless s.include?(w)}'
```

Expected: FAIL，提示 `dependencies.md` 不存在或缺少首个关键词。

- [ ] **Step 2: 写入依赖参考**

文档必须以 `## 目录` 展示四个组件和关联诊断章节：

- MySQL：请求延迟、连接池等待、活跃连接、slow query、rows examined、lock wait、deadlock 和复制延迟。
- Redis：命令延迟、连接错误、hit/miss、eviction、内存碎片、hot key、big key 和回源压力。
- Kafka：producer error、request latency、consumer lag、最老消息年龄、rebalance、partition 倾斜和处理速率。
- RabbitMQ：publish/confirm、deliver/ack、`messages_ready`、`messages_unacknowledged`、redelivery、consumer utilization 和连接/channel。
- 关联规则：先区分应用连接池、网络、服务端饱和、数据/partition 倾斜与下游处理慢。
- 常见误判：单看平均值、把 lag 数量等同延迟、把 hit ratio 下降直接认定 Redis 故障、把慢 SQL 日志存在直接认定数据库是根因。

- [ ] **Step 3: 添加依赖 direct reference 路由并确认 GREEN**

在 `SKILL.md` 添加：

```markdown
- MySQL、Redis、Kafka 和 RabbitMQ 的依赖诊断读取 [references/dependencies.md](references/dependencies.md)。
```

Run: Step 1 命令。

Expected: PASS，exit 0。

---

### Task 6: 创建事故分诊与恢复参考

**Files:**
- Create: `observing-production-services/references/incidents.md`
- Modify: `observing-production-services/SKILL.md`

**Interfaces:**
- Consumes: 所有信号和组件参考产生的证据
- Produces: 影响摘要、时间线、假设表、止损、恢复验证和复盘模板

- [ ] **Step 1: 运行事故处理内容契约，确认 RED**

Run:

```bash
ruby -e 'p="observing-production-services/references/incidents.md"; abort("missing incidents.md") unless File.file?(p); s=File.read(p); ["影响范围","时间线","支持证据","反证","下一检查","止损","回滚条件","恢复验证","观察窗口","根因","Runbook","复盘"].each{|w|abort("missing #{w}") unless s.include?(w)}'
```

Expected: FAIL，提示 `incidents.md` 不存在或缺少首个关键词。

- [ ] **Step 2: 写入事故处理参考**

文档包含 `## 目录` 及以下明确模板：

```markdown
| 假设 | 支持证据 | 反证/缺口 | 下一检查 | 风险与成本 | 状态 |
|---|---|---|---|---|---|
```

并覆盖：

- 影响范围、开始时间、当前状态、用户症状和恢复标准。
- 发布、配置、流量、依赖、资源和错误信号组成的时间线。
- 只读优先的检查顺序和一次只验证一个关键假设。
- 止损动作的授权、预期效果、风险、停止和回滚条件。
- 恢复验证的错误率、延迟、吞吐、饱和度、积压及持续观察窗口。
- 明确区分缓解、临时恢复、直接原因、促成因素和已证实根因。
- Runbook 与复盘必须补充检测、诊断、恢复和预防缺口。
- 证据不足时输出已知、未知和最小下一检查，不补写推测性根因。

- [ ] **Step 3: 添加事故 direct reference 路由并确认 GREEN**

在 `SKILL.md` 添加：

```markdown
- 线上分诊、假设验证、止损、恢复和复盘读取 [references/incidents.md](references/incidents.md)。
```

Run: Step 1 命令。

Expected: PASS，exit 0。

---

### Task 7: 运行集成契约并完成技能验收

**Files:**
- Verify: `observing-production-services/SKILL.md`
- Verify: `observing-production-services/agents/openai.yaml`
- Verify: `observing-production-services/references/*.md`

**Interfaces:**
- Consumes: Task 1-6 的全部产物
- Produces: 可交付的 7 文件技能目录和验证证据

- [ ] **Step 1: 运行完整文件、YAML、引用和文本卫生契约**

Run:

```bash
ruby -ryaml <<'RUBY'
root = "observing-production-services"
expected = %W[
  #{root}/SKILL.md
  #{root}/agents/openai.yaml
  #{root}/references/telemetry.md
  #{root}/references/go.md
  #{root}/references/php.md
  #{root}/references/dependencies.md
  #{root}/references/incidents.md
].sort
actual = Dir["#{root}/**/*"].select { |path| File.file?(path) }.sort
abort "file set mismatch" unless actual == expected

skill = File.read("#{root}/SKILL.md")
frontmatter = skill.match(/\A---\n(.*?)\n---\n/m) or abort "frontmatter"
metadata = YAML.safe_load(frontmatter[1])
abort "frontmatter keys" unless metadata.keys.sort == %w[description name]
abort "skill name" unless metadata["name"] == root
abort "description" unless metadata["description"].start_with?("Use when")

ui = YAML.safe_load(File.read("#{root}/agents/openai.yaml")).fetch("interface")
abort "default prompt" unless ui.fetch("default_prompt").include?("$#{root}")
length = ui.fetch("short_description").length
abort "short description length" unless (25..64).cover?(length)

refs = skill.scan(/\]\((references\/[^)]+)\)/).flatten
abort "reference count #{refs.size}" unless refs.size == 5
refs.each { |ref| abort "missing #{ref}" unless File.file?(File.join(root, ref)) }

markdown = Dir["#{root}/**/*.md"]
markdown.each do |path|
  content = File.read(path)
  lines = content.lines
  abort "#{path}: missing TOC" if lines.size > 100 && !content.include?("## 目录")
  placeholders = ["TO" + "DO", "T" + "BD", "FIX" + "ME"]
  abort "#{path}: placeholder" if placeholders.any? { |word| content.include?(word) }
  abort "#{path}: trailing whitespace" if lines.any? { |line| line.match?(/[ \t]+\n\z/) }
  abort "#{path}: fences" unless lines.count { |line| line.start_with?(96.chr * 3) }.even?
end

puts "integration structure PASS"
RUBY
```

Expected: `integration structure PASS`。

- [ ] **Step 2: 运行场景覆盖契约**

Run:

```bash
ruby <<'RUBY'
root = "observing-production-services"
all = Dir["#{root}/**/*.md"].map { |path| File.read(path) }.join("\n")
scenarios = {
  "Go latency" => ["p99", "GC", "goroutine", "连接池", "下游"],
  "PHP saturation" => ["PHP-FPM", "max_children", "listen queue"],
  "MySQL" => ["slow query", "lock wait", "连接池"],
  "Redis" => ["eviction", "hot key", "回源"],
  "Kafka" => ["consumer lag", "rebalance"],
  "RabbitMQ" => ["messages_ready", "messages_unacknowledged"],
  "uncertainty" => ["支持证据", "反证", "未知", "下一检查"]
}
scenarios.each do |name, words|
  missing = words.reject { |word| all.include?(word) }
  abort "#{name}: missing #{missing.join(', ')}" unless missing.empty?
end
puts "scenario contract PASS"
RUBY
```

Expected: `scenario contract PASS`。

- [ ] **Step 3: 尝试官方 validator**

Run:

```bash
python3 /Users/wuhui/.codex/skills/.system/skill-creator/scripts/quick_validate.py observing-production-services
```

Expected: validator 成功；如果输出 `ModuleNotFoundError: No module named 'yaml'`，将其记录为环境限制，不把它描述为技能验证通过。Step 1 的 Ruby YAML 校验必须已经通过。

- [ ] **Step 4: 检查工作区状态和最终文件规模**

Run:

```bash
git status --short -- observing-production-services docs/superpowers
wc -l observing-production-services/SKILL.md observing-production-services/references/*.md observing-production-services/agents/openai.yaml
```

Expected: 只出现本计划和目标技能的预期未跟踪文件；`SKILL.md` 少于 500 行，所有超过 100 行的 reference 已在 Step 1 验证目录。

- [ ] **Step 5: 汇总验证限制**

最终报告必须明确：是否通过结构与场景契约、官方 validator 的实际结果、未执行子代理前向测试、未提交 Git，以及没有修改仓库中已有的用户变更。
