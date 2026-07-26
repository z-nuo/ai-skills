# Ignore Superpowers Artifacts Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 让 Git 忽略仓库根目录的 `.superpowers/` 本地执行产物，同时继续跟踪 `docs/superpowers/` 工程文档。

**Architecture:** 只在根目录 `.gitignore` 末尾增加一个说明注释和一条目录规则。使用 Git 原生命令分别验证应命中路径与不应命中路径，不修改或删除任何现有产物。

**Tech Stack:** Git ignore 规则、Git CLI

## Global Constraints

- 只修改根目录 `.gitignore`。
- 只忽略仓库根目录 `.superpowers/`。
- 不忽略 `docs/superpowers/`。
- 不新增其他通用忽略规则。
- 不修改、移动或删除 `.superpowers/` 中的文件。

---

### Task 1: 忽略根目录 Superpowers 本地产物

**Files:**
- Modify: `.gitignore`
- Reference: `docs/superpowers/specs/2026-07-23-ignore-superpowers-artifacts-design.md`

**Interfaces:**
- Consumes: Git ignore 的仓库根目录匹配语义。
- Produces: `.superpowers/` 的稳定忽略规则，并保留 `docs/superpowers/` 为可跟踪内容。

- [x] **Step 1: 验证修改前状态**

运行：

```bash
git check-ignore .superpowers/sdd/progress.md
```

预期：退出码为 1，表明该路径当前未被忽略。

- [x] **Step 2: 追加最小忽略规则**

在 `.gitignore` 末尾追加：

```gitignore

# Agent workflow artifacts
.superpowers/
```

- [x] **Step 3: 验证应命中的路径**

运行：

```bash
git check-ignore -v .superpowers/sdd/progress.md
```

预期：输出指向 `.gitignore` 中的 `.superpowers/` 规则，退出码为 0。

- [x] **Step 4: 验证不应命中的路径**

运行：

```bash
git check-ignore docs/superpowers/specs/2026-07-23-ignore-superpowers-artifacts-design.md
```

预期：无输出，退出码为 1，表明 `docs/superpowers/` 未被忽略。

- [x] **Step 5: 验证格式和工作区状态**

运行：

```bash
git diff --check -- .gitignore
git status --short
```

预期：空白检查无输出；状态不再包含 `?? .superpowers/`，仍包含 `?? docs/`。

- [x] **Step 6: 尝试创建原子提交**

若环境允许写入 Git 索引，运行：

```bash
git add .gitignore
git diff --staged --check
git commit -m "chore: ignore local superpowers artifacts"
```

若 Git 索引只读，保留未暂存改动并在交付说明中报告限制。
