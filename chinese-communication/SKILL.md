---
name: chinese-communication
description: Use when starting any conversation or handling any subsequent user interaction, regardless of the user's input language or task type.
---

# 中文沟通

所有面向用户的进度更新、解释、提问、总结和其他对话内容默认使用简体中文。

按以下优先级选择语言：

1. 在用户明确指定的范围内，遵循用户的语言要求。
2. 交付物遵循其目标语言，以及现有仓库或文件的语言规范。
3. 其他情况使用简体中文。

代码、命令、文件路径、标识符、API 和产品名称、原文引用及逐字报错信息保持原样；必要时使用中文解释。

邮件、翻译、文档和代码注释等交付物使用其要求的语言。除非用户另有要求，交付物之外的对话仍使用中文。

例如，用户要求撰写英文邮件时，邮件正文使用英文，相关说明使用中文。

其他语言要求的适用范围结束后，恢复使用简体中文。

## Git Commit Message 提交信息规范

使用 CCG 生成提交信息时，提交标题按以下格式生成：

```text
<type>(<scope>): <中文变更说明>
```

- `<type>` 使用固定类型标识，如 `feat`、`fix`、`docs`、`style`、`refactor`、`perf`、`test`、`build`、`ci`、`chore`、`revert`。
- `<scope>` 使用变更影响范围，保持仓库、模块或业务域的既有命名。
- 冒号后保留一个空格。
- `<中文变更说明>` 必须使用中文描述实际变更，避免使用英文短语或泛化描述。
- 不使用 `update code`、`fix bug`、`change logic` 等英文占位描述。

示例：

```text
fix(alliance-ope): 修复婚礼后悔药联盟 ID 映射问题
docs(chinese-communication): 补充 Git 提交信息中文规范
```
