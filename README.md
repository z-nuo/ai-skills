# 个人 Skills

用于日常 Go/PHP 开发、Freetalk 业务分析与发布检查。核心要求是代码必要、改动聚焦、验证如实。

## 选择技能

| 任务 | Skill |
|---|---|
| 中文沟通、提交信息 | [chinese-communication](chinese-communication/SKILL.md) |
| 澄清决定性需求 | [clarifying-before-solving](clarifying-before-solving/SKILL.md) |
| 代码简洁、减少多余判断与封装 | [minimal-change-guard](minimal-change-guard/SKILL.md) |
| Freetalk 项目入口与业务链路 | [working-with-freetalk-php](working-with-freetalk-php/SKILL.md) |
| PHP 能否上线、更新后复审 | [reviewing-php-release-readiness](reviewing-php-release-readiness/SKILL.md) |
| REST/gRPC 契约与兼容性 | [designing-microservice-interfaces](designing-microservice-interfaces/SKILL.md) |
| 事务、缓存、消息与恢复 | [designing-data-consistency](designing-data-consistency/SKILL.md) |
| Go/PHP 测试 | [testing-backend-services](testing-backend-services/SKILL.md) |
| 性能、压测与容量 | [optimizing-backend-performance](optimizing-backend-performance/SKILL.md) |
| 观测、线上排障与恢复 | [observing-production-services](observing-production-services/SKILL.md) |

## 使用

将需要的技能目录放到 `~/.codex/skills/`，也可链接到本仓库。保持目录名与 `SKILL.md` 中的 `name` 一致。

在请求中使用 `$技能名`，例如：

```text
使用 $minimal-change-guard 精简当前补丁，保留需求所需的完整行为。
```

先选解决主要问题的技能，跨领域再按需组合；参考资料由各入口按场景加载。新增规则优先修改其所属技能，避免重复堆叠。

[GitHub](https://github.com/z-nuo/ai-skills) · [Gitee 镜像](https://gitee.com/z-nuo/skills)
