# Clarifying Before Solving Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Create a discoverable Skill that asks one adaptive clarification question at a time for ambiguous requests, confirms the inferred need before solving, and respects a user's request to answer immediately.

**Architecture:** Keep the Skill self-contained in one `SKILL.md`, with `agents/openai.yaml` for interface metadata. Treat the interaction as a four-state flow: trigger decision, one-question interview, requirement confirmation, and final answer; use fresh-context behavioral scenarios plus static validation as the test boundary.

**Tech Stack:** Markdown, YAML, Codex Skill Creator scripts, Git, fresh-context subagent evaluations.

## Global Constraints

- Create the Skill at `clarifying-before-solving/` in this repository.
- Enter the interview only for complex, ambiguous, underspecified, high-impact, or explicitly clarification-first requests.
- Ask exactly one question per interview response and choose it from the user's latest answer.
- Treat 95% as decision sufficiency across goal, audience, context, scope, constraints, success criteria, and material preferences.
- Before the normal final answer, restate the inferred need in one concise sentence and obtain user confirmation.
- If the user asks to stop questions and answer directly, comply immediately and state material assumptions and uncertainties.
- Add no scripts, references, assets, or auxiliary README inside the Skill directory.
- Preserve all unrelated worktree changes.
- Commit steps are conditional on `.git` becoming writable; otherwise record the exact permission error and leave verified changes unstaged.

---

### Task 1: Establish RED Behavioral Evidence

**Files:**
- Read: `docs/superpowers/specs/2026-07-27-clarifying-before-solving-design.md`
- Create: none
- Modify: none

**Interfaces:**
- Consumes: the approved behavior in the design specification.
- Produces: observed no-guidance failures and rationalizations that Task 2 must address.

- [ ] **Step 1: Verify the Skill does not exist**

Run:

```bash
test ! -e clarifying-before-solving/SKILL.md
```

Expected: exit code 0 with no output.

- [ ] **Step 2: Run five no-guidance control samples for the ambiguous-request scenario**

Start each sample in a fresh context without loading the planned Skill. Use this exact user prompt:

```text
项目明天就要启动，请尽快帮我设计一个会员系统，但我现在还没想清楚具体范围。
```

For every response, manually record whether it:

```text
[ ] outputs a solution before clarifying the real goal
[ ] asks more than one question in the same message
[ ] lacks an explicit confirmation of the inferred requirement
```

Expected RED: at least one of the five controls exhibits one or more failures. If none fail, stop authoring behavior guidance because the control does not demonstrate the target problem.

- [ ] **Step 3: Run fresh-context controls for boundary behavior**

Use these exact prompts independently:

```text
把 2 + 2 的结果告诉我。
```

```text
我需要一个内部审批工具，具体怎么做还没想好。请先理解我的真实需求。
```

```text
不要再问了，请基于目前信息直接给方案。
```

Expected observations: document whether the first prompt receives unnecessary clarification, whether the second receives multiple questions or a premature plan, and whether the third is blocked by further questioning. Keep raw responses in the conversation/evaluation trace, not as repository files.

---

### Task 2: Create the Minimal Self-Contained Skill

**Files:**
- Create: `clarifying-before-solving/SKILL.md`
- Create: `clarifying-before-solving/agents/openai.yaml`

**Interfaces:**
- Consumes: the actual failures from Task 1 and the approved state transitions.
- Produces: `$clarifying-before-solving`, whose interview responses contain at most one question and whose exit behavior is deterministic.

- [ ] **Step 1: Initialize the Skill with official tooling**

Run:

```bash
python3 /Users/wuhui/.codex/skills/.system/skill-creator/scripts/init_skill.py clarifying-before-solving \
  --path /Users/wuhui/Documents/Date/gitee/skills \
  --interface 'display_name=Clarify Before Solving' \
  --interface 'short_description=Clarify ambiguous needs before proposing solutions' \
  --interface 'default_prompt=Use $clarifying-before-solving to clarify my real needs before proposing a solution.'
```

Expected: `clarifying-before-solving/SKILL.md` and `clarifying-before-solving/agents/openai.yaml` are created, with no resource directories.

- [ ] **Step 2: Replace the generated Skill body with the minimal behavior contract**

Write this complete content to `clarifying-before-solving/SKILL.md` using `apply_patch`, adding counters only for failures actually observed in Task 1:

```markdown
---
name: clarifying-before-solving
description: Use when a request is ambiguous, complex, underspecified, high-impact, or supports materially different solutions, especially when the real goal, audience, scope, constraints, or success criteria are unclear.
---

# Clarify Before Solving

## Core Rule

Resolve decision-changing uncertainty before proposing a solution. Ask one question at a time, adapt the next question to the latest answer, and confirm the inferred need before the final solution.

## Flow

### 1. Decide Whether to Interview

Interview when at least one condition is true:

- Multiple reasonable interpretations would produce materially different solutions.
- Goal, audience, current state, scope, constraints, priorities, or success criteria are missing.
- The decision is high-impact or difficult to reverse.
- The user explicitly asks for clarification before an answer.

Answer directly when the request is simple, specific, low-risk, and already has enough information for one substantially correct result.

### 2. Ask the Highest-Value Question

Identify the unknown whose possible answers would most change the recommendation. Reply with only necessary one-sentence context and exactly one question. Prefer mutually exclusive choices with brief trade-offs; use an open question when choices would be artificial.

After each answer, reassess from the new information. Do not follow a fixed questionnaire, combine questions, or provide the full solution during the interview.

### 3. Check Decision Sufficiency

Treat 95% as a decision threshold, not a numeric probability. Continue interviewing while any unknown could materially change the direction, architecture, cost, risk, or deliverable.

Proceed only when these are known or have a safe default that will not change the main recommendation:

- real goal and desired outcome;
- audience or affected users;
- context, current state, and scope;
- hard constraints and exclusions;
- priorities and success criteria;
- preferences that materially change the result.

Implementation details that only affect minor tuning may remain as explicit assumptions.

### 4. Confirm Before Solving

When the threshold is met, state the inferred goal, key constraints, and success criteria in one concise sentence, then ask one confirmation question. Produce the final solution only after confirmation. If the user corrects the summary, return to the one-question loop.

### 5. Respect an Early Exit

If the user explicitly asks to stop questions or answer directly, stop interviewing immediately. State the material assumptions and remaining uncertainties, then provide the best solution supported by current information. Do not use the 95% threshold to override this instruction.

## Response Contract

| State | Response |
|---|---|
| Simple, sufficient request | Direct answer |
| Interview | Optional one-sentence context, then exactly one question |
| Requirement confirmation | One-sentence summary, then exactly one confirmation question |
| Confirmed | Final solution shaped to the original task |
| User exits early | Material assumptions and uncertainties, then current best solution |

## Common Mistakes

- Asking a list of questions in one message: select only the highest-value unknown.
- Treating 95% as confidence without checking decision-changing gaps: use the sufficiency list.
- Repeating a scripted questionnaire: derive each next question from the latest answer.
- Giving a partial architecture while interviewing: keep solution content for the confirmed or early-exit state.
- Interrogating a simple request: direct answers are part of this Skill's correct behavior.
```

- [ ] **Step 3: Verify the generated interface metadata**

Ensure `clarifying-before-solving/agents/openai.yaml` is exactly:

```yaml
interface:
  display_name: "Clarify Before Solving"
  short_description: "Clarify ambiguous needs before proposing solutions"
  default_prompt: "Use $clarifying-before-solving to clarify my real needs before proposing a solution."
```

- [ ] **Step 4: Run five fresh-context GREEN samples**

Repeat the five ambiguous-request samples from Task 1 while explicitly loading `$clarifying-before-solving`. Manually verify every response has no full solution and exactly one question. Continue at least one sample through user answers until it produces a one-sentence requirement summary and waits for confirmation.

Expected GREEN: all five samples follow the response contract; outputs converge on one high-value question rather than different response shapes.

- [ ] **Step 5: Verify boundary and override scenarios**

Repeat Task 1's three boundary prompts with the Skill loaded. For the early-exit scenario, first establish an active interview in the same context, then send `不要再问了，请基于目前信息直接给方案。`.

Expected: the arithmetic request is answered directly; the ambiguous request receives exactly one question; the early-exit request receives assumptions, uncertainties, and a solution without another clarification question.

- [ ] **Step 6: Close only observed loopholes and re-run affected samples**

If a GREEN sample fails, add the smallest explicit counter to `SKILL.md`, then re-run five fresh samples for that response state. Do not add hypothetical rules that were not exercised.

- [ ] **Step 7: Run official static validation**

Run:

```bash
python3 /Users/wuhui/.codex/skills/.system/skill-creator/scripts/quick_validate.py clarifying-before-solving
wc -w clarifying-before-solving/SKILL.md
```

Expected: validator prints `Skill is valid!`; word count remains below 500 words unless observed behavioral failures require explicit counters.

- [ ] **Step 8: Create an atomic Skill commit when Git is writable**

```bash
git add clarifying-before-solving/SKILL.md clarifying-before-solving/agents/openai.yaml
git diff --cached --check
git commit -m "feat: add clarification-first problem solving skill"
```

Expected: one commit containing only the Skill and its interface metadata. If `.git/index.lock` cannot be created, record the error and continue without staging.

---

### Task 3: Integrate the Skill into Repository Documentation

**Files:**
- Modify: `README.md`
- Read: `clarifying-before-solving/SKILL.md`
- Read: `clarifying-before-solving/agents/openai.yaml`

**Interfaces:**
- Consumes: the validated Skill name, trigger boundary, outputs, and metadata from Task 2.
- Produces: repository discovery links and accurate integrity counts.

- [ ] **Step 1: Update repository summary and quick selection**

Change the README introduction from 6 to 7 Skills and add problem clarification to the coverage summary. Add this row after `chinese-communication`:

```markdown
| 在复杂或模糊问题前逐步澄清需求 | [`clarifying-before-solving`](clarifying-before-solving/SKILL.md) | 单问题访谈、需求确认与显式假设 |
```

Change “其余 5 个 Skill” to wording that distinguishes the two interaction Skills from the five backend domain Skills.

- [ ] **Step 2: Add the capability entry**

Add this section after `chinese-communication`:

```markdown
### `clarifying-before-solving`

- **用途**：在形成方案前识别并补齐会改变决策方向的需求信息。
- **适用场景**：复杂、模糊、信息不足、高影响或存在多种合理解释的问题，以及用户明确要求先澄清需求的任务。
- **默认输出**：每轮一个最高价值问题；信息充分后先复述需求并等待确认；用户要求直接回答时列出假设后给出当前最佳方案。
- **参考资料**：没有独立 reference，全部规则位于 [`SKILL.md`](clarifying-before-solving/SKILL.md)。
- **OpenAI 配置**：[`agents/openai.yaml`](clarifying-before-solving/agents/openai.yaml)。
```

- [ ] **Step 3: Update integrity metadata**

Set the inventory date to `2026-07-27`, the `SKILL.md` count to 7, and the `agents/openai.yaml` count to 6. Keep the existing note about the missing `optimizing-backend-performance/references/load-testing.md` unchanged.

- [ ] **Step 4: Run repository-wide static checks**

Run:

```bash
git diff --check
test "$(find . -name SKILL.md -not -path './.git/*' | wc -l | tr -d ' ')" = "7"
test "$(find . -path '*/agents/openai.yaml' | wc -l | tr -d ' ')" = "6"
rg -n 'TB''D|TO''DO|implement la''ter|fill in de''tails' clarifying-before-solving README.md
python3 /Users/wuhui/.codex/skills/.system/skill-creator/scripts/quick_validate.py clarifying-before-solving
```

Expected: `git diff --check` and both count checks exit 0; placeholder search has no matches; validator prints `Skill is valid!`.

- [ ] **Step 5: Review the complete diff and status**

Run:

```bash
git status --short
git diff -- README.md clarifying-before-solving/SKILL.md clarifying-before-solving/agents/openai.yaml docs/superpowers/specs/2026-07-27-clarifying-before-solving-design.md docs/superpowers/plans/2026-07-27-clarifying-before-solving.md
```

Expected: only the approved design, implementation plan, new Skill, metadata, and README integration are changed. No unrelated file is modified.

- [ ] **Step 6: Commit documentation when Git is writable**

```bash
git add README.md docs/superpowers/specs/2026-07-27-clarifying-before-solving-design.md docs/superpowers/plans/2026-07-27-clarifying-before-solving.md
git diff --cached --check
git commit -m "docs: document clarification-first skill"
```

Expected: one documentation-only commit. If `.git/index.lock` cannot be created, leave all verified files unstaged and report the limitation.

---

### Task 4: Localize the Skill into Simplified Chinese

**Files:**
- Modify: `clarifying-before-solving/SKILL.md`
- Modify: `clarifying-before-solving/agents/openai.yaml`
- Modify: `docs/superpowers/specs/2026-07-27-clarifying-before-solving-design.md`

**Interfaces:**
- Consumes: the five-state behavior contract from Task 2 and the approved localization scope in the design specification.
- Produces: the same `$clarifying-before-solving` invocation and behavior with all agent-facing and UI-facing prose in Simplified Chinese.

- [ ] **Step 1: Run the localization RED check**

Run:

```bash
if rg -n 'Use when|Clarify Before Solving|Core Rule|Decide Whether to Interview|Common Mistakes|Clarify ambiguous needs|Use \$clarifying-before-solving' \
  clarifying-before-solving/SKILL.md \
  clarifying-before-solving/agents/openai.yaml; then
  echo 'RED: English-facing copy remains'
  exit 1
fi
```

Expected: exit code 1 with matches from both files and `RED: English-facing copy remains`.

- [ ] **Step 2: Replace `SKILL.md` with the complete Chinese version**

Use `apply_patch` to set `clarifying-before-solving/SKILL.md` to exactly:

```markdown
---
name: clarifying-before-solving
description: 当请求复杂、模糊、信息不足、影响重大或存在会产生实质不同方案的多种解释，尤其是真实目标、受众、范围、约束或成功标准不明确时使用。
---

# 先澄清，再解决

## 核心规则

在提出方案前消除会改变决策的不确定性。每次只提出一个自适应问题，并在解决问题前确认推断出的真实需求。

## 工作流程

### 1. 判断是否需要访谈

满足以下任一条件时进入访谈：

- 存在多种合理解释，且不同解释会产生实质不同的方案。
- 目标、受众、范围、约束、优先级或成功标准缺失。
- 决策影响重大或难以撤销。
- 用户明确要求先澄清再回答。

当请求简单、具体、风险低，并且现有信息足以得出基本唯一的正确结果时，直接回答。

### 2. 提出最高价值的问题

找出不同答案最可能改变建议方向的未知项。回复最多包含一句必要的上下文和一个问题。优先提供互斥选项并简要说明取舍；无法自然列举选项时再使用开放式问题。

收到每次回答后重新评估。不要执行固定问卷，不要合并多个问题，也不要在访谈阶段提供方案。

### 3. 检查决策信息是否充分

将 95% 视为决策充分度门槛，而不是数学概率。只要仍有未知项可能实质改变方向、成本、风险或交付物，就继续追问。

只有以下信息已经明确，或具有不会改变主要方向的安全默认值时，才进入下一阶段：

- 真实目标和期望结果；
- 受众或受影响的用户；
- 背景、现状和范围；
- 硬性约束和明确排除项；
- 优先级和成功标准；
- 会实质改变结果的偏好。

只影响细节微调的实现信息可以保留为显式假设。

### 4. 先确认，再解决

达到门槛后，用一句简洁陈述概括推断出的目标、关键约束和成功标准，然后只提出一个确认问题。仅在用户确认后给出最终方案。用户纠正概括内容时，返回一次一个问题的访谈循环。

### 5. 尊重用户提前退出

用户明确要求停止提问或直接回答时，立即结束访谈。先说明会影响结论的假设和剩余不确定性，再基于现有信息给出最佳方案。不得以未达到 95% 门槛为由拒绝该指令。

## 回复契约

| 状态 | 回复方式 |
|---|---|
| 请求简单且信息充分 | 直接回答 |
| 访谈中 | 可选的一句上下文，然后只提出一个问题 |
| 确认需求 | 用一句话概括需求，然后只提出一个确认问题 |
| 用户已确认 | 按原始任务要求给出最终方案 |
| 用户提前退出 | 说明重要假设和不确定性，然后给出当前最佳方案 |

## 常见错误

- 一次列出多个问题：只选择最高价值的未知项。
- 未检查决策缺口就声称达到 95%：逐项使用信息充分度清单。
- 重复预设问卷：根据用户最新回答生成下一个问题。
- 对简单请求进行机械追问：直接回答也是该技能的正确行为。
```

- [ ] **Step 3: Replace UI metadata with the complete Chinese version**

Use `apply_patch` to set `clarifying-before-solving/agents/openai.yaml` to exactly:

```yaml
interface:
  display_name: "先澄清，再解决"
  short_description: "在提出最终方案前逐步澄清复杂、模糊或信息不足的真实需求"
  default_prompt: "使用 $clarifying-before-solving 在提出方案前逐步澄清我的真实需求。"
```

- [ ] **Step 4: Synchronize the design specification**

Replace the three English values in the `agents/openai.yaml` list under `## 发现与界面元数据` with:

```markdown
- `display_name`: `先澄清，再解决`
- `short_description`: `在提出最终方案前逐步澄清复杂、模糊或信息不足的真实需求`
- `default_prompt`: `使用 $clarifying-before-solving 在提出方案前逐步澄清我的真实需求。`
```

- [ ] **Step 5: Run the localization GREEN check and structural validation**

Run:

```bash
if rg -n 'Use when|Clarify Before Solving|Core Rule|Decide Whether to Interview|Common Mistakes|Clarify ambiguous needs|Use \$clarifying-before-solving' \
  clarifying-before-solving/SKILL.md \
  clarifying-before-solving/agents/openai.yaml; then
  exit 1
fi

git diff --check

ruby -e 'require "yaml"; text=File.read("clarifying-before-solving/SKILL.md"); fm=text.match(/\A---\n(.*?)\n---\n/m) or abort("missing frontmatter"); data=YAML.safe_load(fm[1]); abort("bad name") unless data["name"]=="clarifying-before-solving"; abort("bad description") unless data["description"].is_a?(String) && !data["description"].empty? && data["description"].length<=1024; ui=YAML.safe_load(File.read("clarifying-before-solving/agents/openai.yaml")); abort("bad prompt") unless ui.dig("interface","default_prompt")&.include?("$clarifying-before-solving")'

rg -q '^# 先澄清，再解决$' clarifying-before-solving/SKILL.md
rg -q '^## 回复契约$' clarifying-before-solving/SKILL.md
rg -q '用户明确要求停止提问或直接回答时，立即结束访谈' clarifying-before-solving/SKILL.md
```

Expected: all commands exit 0 with no English-copy matches, valid YAML, the unchanged Skill identifier, and the Chinese response contract present.

- [ ] **Step 6: Review the final localization diff**

Run:

```bash
git status --short
git diff --check
git diff -- README.md
git diff --no-index /dev/null clarifying-before-solving/SKILL.md
git diff --no-index /dev/null clarifying-before-solving/agents/openai.yaml
```

Expected: the Skill and UI metadata are fully Chinese except for required technical identifiers and YAML keys; README remains consistent and no unrelated files are changed.
