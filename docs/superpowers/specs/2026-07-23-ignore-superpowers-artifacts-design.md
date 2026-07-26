# 忽略本地 Superpowers 产物设计

## 状态

已确认，待实现。

## 日期

2026-07-23

## 背景

仓库根目录的 `.superpowers/` 包含本地执行过程产生的 brief、report、review 和 diff 文件。它当前未被 Git 忽略，因此会作为未跟踪目录出现在工作区状态中。

`docs/superpowers/` 保存设计规格和实施计划，属于可进入版本控制的工程文档，与根目录 `.superpowers/` 的本地产物用途不同。

## 决策

在根目录 `.gitignore` 末尾增加：

```gitignore
# Agent workflow artifacts
.superpowers/
```

该规则只忽略仓库根目录 `.superpowers/` 目录，不忽略 `docs/superpowers/`。

## 非目标

- 不忽略 `docs/superpowers/`。
- 不新增 `.env*`、证书、密钥或其他通用规则。
- 不修改、移动或删除 `.superpowers/` 中的现有文件。
- 不修改已有 `.gitignore` 规则。

## 验证标准

- `git check-ignore -v .superpowers/sdd/progress.md` 命中新增规则。
- `git check-ignore docs/superpowers/specs/2026-07-23-ignore-superpowers-artifacts-design.md` 不命中任何规则。
- `git status --short` 不再显示未跟踪的 `.superpowers/`，仍显示未跟踪的 `docs/`。
- `git diff --check -- .gitignore` 无空白错误。

