---
name: working-with-freetalk-php
description: 定位 Freetalk PHP 多仓库入口、依赖和资源，追踪业务参数、支付发奖与废弃链路，编写交接文档。
---

# Freetalk PHP 项目与业务链路

以用户指定 checkout 为起点，读取项目规则、Composer、入口和同类实现，确认路径、branch、HEAD 与工作树。`_master` 是目录名，不能代替分支证据。

1. 用 `rg` 定位入口，沿调用方、参数来源、转换、下游读写和返回追踪；跨服务继续查接收端及消费者。
2. 关键字段按“来源 → 入口值 → 转换 → 下游用途”说明；失败时核对已扣款、写锁、入队或发奖的状态。
3. 实现沿用现有 wrapper、返回结构和目标 PHP 版本；代码必要性与范围按 `minimal-change-guard` 核对。
4. 用源码位置支持结论，分清本地验证与线上配置、日志、真实服务的未知项。

## 按需参考

- 服务入口、依赖目录和版本：[项目地图](references/project-map.md)。
- 字段变化、支付发奖、推荐、废弃与交接：[业务链路](references/business-flows.md)。
- QConf、Redis、RPC、缓存和验证副作用：[资源与运行](references/resources-and-runtime.md)。

`Vendor/` 可能是自有业务；安装依赖目录按 `config.vendor-dir` 与实际 autoload 确认。公共包声明、lock、安装实现和兄弟源码版本分别核对。

涉及 `freetalk/*` 时，按项目规则查安装包 `freetalk/common/docs/` 的 `agents-reference.md`、`team-ai-coding-rules.md`、`engineering-mechanism.md`；用法、示例与测试再按需查 `package-usage-map.md`、`good-examples.md`、`runnable-tests.md`。缺失时可参考兄弟 `freetalk_common/docs/`，注明版本差异。

问答先给结论和关键链路；要求交接文档时保存业务流程、数据示例、key/排序、失败恢复和源码索引。发布结论使用 `reviewing-php-release-readiness`。
