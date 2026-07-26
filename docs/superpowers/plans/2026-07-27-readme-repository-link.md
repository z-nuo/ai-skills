# README Repository Link Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add the canonical Gitee repository URL directly below the README title.

**Architecture:** Make one localized Markdown edit to `README.md`. Verify exact link text, exact target, document order, existing local links, and whitespace without adding dependencies.

**Tech Stack:** Markdown, Ruby standard library, Git.

## Global Constraints

- Use `https://gitee.com/z-nuo/skills` as both visible link text and target.
- Place the link after `# Skills` and before the existing project introduction.
- Do not change any other README content.
- Preserve unrelated worktree changes.
- Leave changes unstaged when `.git` is read-only.

---

### Task 1: Add the Repository Link

**Files:**
- Modify: `README.md`
- Read: `docs/superpowers/specs/2026-07-27-readme-repository-link-design.md`

**Interfaces:**
- Consumes: the exact Gitee URL and approved placement.
- Produces: a first-viewport Markdown link to the repository homepage.

- [ ] **Step 1: Run the failing placement check**

Run:

```bash
ruby -e 'lines=File.readlines("README.md", chomp: true); expected=["# Skills", "", "仓库地址：[https://gitee.com/z-nuo/skills](https://gitee.com/z-nuo/skills)"]; abort("仓库地址尚未位于标题下方") unless lines.first(3)==expected'
```

Expected: exit code 1 with `仓库地址尚未位于标题下方`.

- [ ] **Step 2: Add the exact Markdown link**

Use `apply_patch` to change the beginning of `README.md` to:

```markdown
# Skills

仓库地址：[https://gitee.com/z-nuo/skills](https://gitee.com/z-nuo/skills)

面向 Go/PHP 后端工程与高质量交互的 Codex Skill 集合。仓库当前包含 7 个 Skill，覆盖沟通规范、需求澄清、微服务接口、数据一致性、自动化测试、性能工程和生产可观测性。
```

- [ ] **Step 3: Run placement and link validation**

Run:

```bash
ruby -e 'lines=File.readlines("README.md", chomp: true); expected=["# Skills", "", "仓库地址：[https://gitee.com/z-nuo/skills](https://gitee.com/z-nuo/skills)", ""]; abort("位置错误") unless lines.first(4)==expected; abort("链接数量错误") unless File.read("README.md").scan("https://gitee.com/z-nuo/skills").length==2'

ruby -e 'text=File.read("README.md"); links=text.scan(/\]\(([^)]+)\)/).flatten.reject{|p| p.start_with?("http") || p.start_with?("#")}; missing=links.reject{|p| File.exist?(p)}; abort("失效链接: #{missing.join(", ")}") unless missing.empty?'

git diff --check
```

Expected: all commands exit 0.

- [ ] **Step 4: Review scope**

Run:

```bash
git diff -- README.md
git status --short
```

Expected: the README diff contains one added repository-address line and surrounding blank line only; the existing design and plan documents remain the only other task files.
