# README 仓库地址设计规格

## 目标

在 README 首屏明确展示本仓库的 Gitee 地址，方便读者快速访问仓库主页。

## 设计

在 `README.md` 的一级标题 `# Skills` 之后、项目简介之前新增一行：

```markdown
仓库地址：[https://gitee.com/z-nuo/skills](https://gitee.com/z-nuo/skills)
```

使用普通 Markdown 文本链接，不使用裸链接或依赖外部图片的徽章。保持现有简介、快速选择、能力范围和其他内容不变。

## 验证

- 链接文本和目标 URL 均为 `https://gitee.com/z-nuo/skills`。
- 链接位于标题之后、简介之前。
- README 的全部本地链接继续有效。
- `git diff --check` 无空白错误。
