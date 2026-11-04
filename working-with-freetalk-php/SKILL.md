---
name: working-with-freetalk-php
description: 分析或修改 Freetalk PHP 多仓库业务时使用，涵盖 client_activity、backendV2、shop、family、prize_server 和 freetalk 公共包的入口定位、调用链、QConf/Redis 与交接文档；独立的发布审查使用 reviewing-php-release-readiness。
---

# Freetalk PHP 项目与业务链路

从当前 checkout 找到真实入口、数据来源和副作用边界，再回答业务问题或实施已授权的修改。用户提供的项目路径是起点；兄弟仓库和安装依赖用于补齐实际跨越的调用链。

## 先确认项目事实

读取目标项目 `AGENTS.md`、README、`composer.json`、实际入口和同类实现。记录仓库路径、branch、HEAD 及已有改动；`_master` 是目录名，分支需要另查。目录盘点或只读分析不意味着要修改业务源码。

- 定位服务、入口或公共包时，读取 [项目地图](references/project-map.md)。
- 分析字段变化、支付发奖、推荐链路、功能废弃或编写交接文档时，读取 [业务链路](references/business-flows.md)。
- 涉及 QConf、Redis、Thrift、Yac、队列或脚本验证时，读取 [资源与运行边界](references/resources-and-runtime.md)。

安装依赖的位置从 `config.vendor-dir` 和入口的 autoload 路径确认。`Vendor/` 可能包含业务类，不能统一排除；`vendor/`、`Composer/`、`composer/` 也不能互换。需要理解框架时只读实际安装包；需要修改公共包时回到对应源码仓库。

涉及 `freetalk/*` 核心包时，按目标项目规则查找安装的 `freetalk/common/docs/agents-reference.md`、`team-ai-coding-rules.md` 和 `engineering-mechanism.md`，再按问题读取 `package-usage-map.md`、`good-examples.md`、`runnable-tests.md`。缺失时可参考兄弟 `freetalk_common/docs/`，但注明版本差异，不能把兄弟仓库 HEAD 当作已安装实现。

## 形成可验证的链路

1. 用 `rg -n` 找用户给出的入口、类、方法、字段或业务词，配合 `rg --files` 查路径。按当前项目目录和 autoload 搜索，保留业务 `Vendor/`。
2. 从入口逐段记录调用方、参数来源、转换位置、下游读写和返回。跨服务时继续查接收端、消费者或生成协议；只有请求发送代码时，结论停在发送边界。
3. 对关键字段给出“来源 → 入口值 → 转换 → 下游值 → 使用位置”。对失败路径说明是否已扣款、写锁、消费、入队或发奖，以及重试后果。
4. 改动时沿用当前项目已有 wrapper、业务类、日志和返回结构，保持其 PHP 版本与目录约定。生成代码或检查冗余时使用 `minimal-change-guard` 的逐段必要性规则，核对每个判断、查询、参数和抽象的依据，按目标项目规则检查新增类数与调用层数。
5. 用源码位置支持结论，分别列出本地验证与仍需线上配置、日志或真实服务确认的部分。

搜索未发现调用不能独立证明方法可删除：继续核对路由、字符串分派、CLI/定时任务、配置、模板和跨仓库消费者。用户已确认的业务规则属于验收条件，不将单次历史处理结论泛化到其他业务。

## 交付

问答先给结论，再给关键调用链和证据。用户要求完整 Markdown 交接时，保存业务流程、输入输出示例、数据结构与排序、配置/key、失败与恢复、相关入口及源码索引；复杂流程可加 Mermaid。

变更交付说明实际文件、必要连带变更、运行的验证及缺口。发布门禁由 `reviewing-php-release-readiness` 处理；一致性方案、接口设计或测试实现按实际问题选择相应领域技能，不为读懂既有调用链重设计系统。
