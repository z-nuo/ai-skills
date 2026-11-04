# PHP 项目与技能分析

盘点日期：2026-11-04。分析对象为用户指定的 `service-php-gitlab/` 和技能仓库 `ai-skills/`。

## 范围与证据等级

- 对根目录下 58 个 Git 项目检查目录、branch、HEAD、既有工作树状态、根级 Composer/npm manifest 和入口线索。
- 其中 39 个项目有根级 `composer.json`，15 个有根级 `package.json`；有 package.json 不等于 Node 后端，部分仅用于前端构建或旧构建工具。
- 读取现有 8 个技能的入口和相关 PHP 参考，检查职责、链接与版本适用性；对关键业务和工程规则做源码抽样。
- 全量项目表表示目录与 manifest 盘点覆盖，业务链路仅做有针对性的抽样，不表示完成 58 个项目的逐行审计或安全审计。
- 本次只写技能仓库。源码项目不执行修改、依赖更新、业务脚本、上传构建、RPC、Redis 或部署操作。

## 当前工程事实

1. PHP 版本并不统一。多数现代 Freetalk 服务声明 `^7.3.33`，`paipai-zone-show-builder/composer.json` 为 `5.6.*`，`user-album-upload/composer.json` 为 `5.6`；目录内另有 Vue 与 Go 项目。
2. `client_activity/Public/index.php` 加载 `Composer/autoload.php`，其 Composer 配置指定 `Composer/`；`Vendor/` 仍有业务实现。`family.ifreetalk.com/Vendor/RecommendChatList/` 也是自有业务。其他项目存在小写 `vendor/` 和 `composer/`，必须逐项目确认。
3. 五个核心包通过 Composer、autoload 与资源 wrapper 连接业务服务；兄弟包当前 HEAD 和服务安装版本是不同证据。`framework` 也不等同于公共包 `freetalk_framework`。
4. `_master` 后缀并不表示 master 分支。盘点时 `backendV2.ifreetalk.com_master` 为 `online`，`view-backend.ifreetalk.com_master` 为 `release`，后续使用仍须重查。
5. 项目规则主要来自 `backendV2.ifreetalk.com/AGENTS.md`、其 `_master` 副本、`client_activity/AGENTS.md`、`cli-share.ifreetalk.com/AGENTS.md`、`freetalk_common/AGENTS.md`。公共规则与机制还位于 `freetalk_common/docs/`；新技能仅提供导航，不复制或取代项目约束。
6. `freetalk_common/docs/runnable-tests.md` 将发奖、地图与联盟脚本列为集成测试。测试目录存在不代表可离线执行。
7. `view-backend.ifreetalk.com/config/webpack.prod.js` 使用资源上传插件并写环境文件；`package.json` 中的 `test` 是失败占位脚本。不能将生产构建或 npm test 当作无副作用的验证方法。

## 关键业务抽样

| 场景 | 当前源码起点 | 沉淀到技能的判断方法 |
|---|---|---|
| 发奖参数变化 | `backendV2.ifreetalk.com/application/module/send/sailingProp/sendNavigationProps.php` → `prize_server/application/module/goodsModule/planConfigFormat.php::filterGoods()` | 逐段记录配置来源、goods_type/goods_id、字段过滤及 item_id 重算；实际事故仍需请求与配置版本 |
| 推荐与缓存 | `family.ifreetalk.com/Application/controllers/RecommendChatList.php`、`Vendor/RecommendChatList/RecommendChatRedis.php` | 保留业务 Vendor，追踪来源、缓存、筛选排序、pipeline 与失败值 |
| 商城资格与发奖 | `shop.ifreetalk.com/application/app/Module/Shop/FIPowerGiftPkg.php`、`client_activity/Vendor/Shop/ShopPackageBuy.php` | 分开分析资格、订单、支付、队列、奖励与锁；归属维度逐调用判断 |
| 资源路由 | `freetalk_framework/src/store/redis.php`、`src/store/driver/redisClient.php`、`freetalk_qconf/src/qconf.php` | 分开记录配置入口、逻辑 key、CGK 维度与物理路由的证据等级 |

## 技能缺口与本轮更新

| 原有缺口 | 更新 | 边界 |
|---|---|---|
| 通用领域技能缺少本地项目、入口与公共包导航 | 新增 `working-with-freetalk-php`，按需加载项目地图、业务链路、资源运行参考 | 提供可复用分析方法，不固化历史业务结果和依赖版本 |
| 缺少独立 PHP 发布门禁与复审流程 | 新增 `reviewing-php-release-readiness` 与只读 token 比较脚本 | 审查默认只读，源码结论与生产验收分开 |
| PHP 测试主示例使用 attributes，旧脚本项目的验证路径不具体 | 更新 `testing-backend-services/references/php.md` | 遵守各项目 PHP 版本、现有设施和脚本副作用 |
| 性能技能引用的 load-testing.md 缺失 | 补齐负载模型、协调遗漏、停止条件、异步完成与容量参考 | 不新增压测依赖，不实际制造负载 |
| README 数量、目录和引用不完整 | 同步为 10 个技能、25 个 reference、9 个界面配置和 1 个脚本 | 保持已有技能调用策略，不自动替换本机安装副本 |

## 全量项目清单

以下为本次开始时的检出快照；“定位类别”来自结构与 manifest，不能据此推断完整线上职责。HEAD 仅用于追溯本轮证据，不作为未来使用前提。

| 项目 | 定位类别 | branch | HEAD | 入口或证据位置 | 原有改动条目 |
|---|---|---|---|---|---|
| `17paipai.cn` | 脚本/站点/导出工具 | `master` | `869176a6349c` | `index.php` | 0 |
| `activity_export_config` | 脚本/站点/导出工具 | `master` | `52a9b4d43beb` | `composer.json、业务导表目录` | 0 |
| `anchor.ifreetalk.com` | PHP 应用/服务 | `online` | `4e946bd136e6` | `public/index.php` | 0 |
| `auth_server` | PHP 应用/服务 | `master` | `2484bcda7106` | `public/index.php` | 0 |
| `authentication_service` | PHP 应用/服务 | `master` | `f4928b411fba` | `application/server/checkUserBanned.php` | 0 |
| `backend` | 旧后台结构 | `master` | `d7addb8b9b15` | `index.php` | 0 |
| `backendV2.ifreetalk.com` | PHP 应用/服务 | `90032` | `948ff4157207` | `public/index.php` | 0 |
| `backendV2.ifreetalk.com_master` | PHP 应用/服务 | `online` | `ecc5ddb2521e` | `public/index.php` | 1 |
| `backend_rpc` | PHP 应用/服务 | `master` | `f4dc33a9ea6a` | `public/index.php` | 0 |
| `backend_script` | PHP 应用/服务 | `master` | `9f57a099661e` | `Public/index.php` | 0 |
| `cash` | PHP 应用/服务 | `online` | `79b3d6a332db` | `Public/index.php` | 1 |
| `cli-share.ifreetalk.com` | PHP 应用/服务 | `online` | `544068c805c2` | `public/index.php` | 1 |
| `client-version-service` | PHP 应用/服务 | `master` | `a556032917e9` | `public/index.php` | 0 |
| `client_activity` | PHP 应用/服务 | `master` | `dd7fd564e524` | `Public/index.php` | 0 |
| `clientreport.ifreetalk.com` | PHP 应用/服务 | `master` | `1edb41be184d` | `public/index.php` | 0 |
| `clientstat.ifreetalk.com` | PHP 应用/服务 | `master` | `428e23eb5872` | `public/index.php` | 0 |
| `config-cache-php-sdk` | PHP 应用/服务 | `master` | `fb646401eb13` | `public/index.php` | 0 |
| `config-cache-php-template-def` | 配置/模板 | `release_yunying_0705` | `eab079da2d34` | `config_template/` | 0 |
| `cooperate-ifreetalk-com` | PHP 应用/服务 | `master_yingyongbao` | `78fea896b8a5` | `public/index.php` | 0 |
| `d.17paipai.cn` | PHP 应用/服务 | `master` | `878bf927b825` | `Public/index.php` | 0 |
| `factory-service` | PHP 应用/服务 | `online` | `b2d979c8c056` | `public/index.php` | 0 |
| `family-om-service` | PHP 应用/服务 | `master` | `807a2e3de90d` | `public/index.php` | 1 |
| `family.ifreetalk.com` | PHP 应用/服务 | `online` | `a2fb5a7c052e` | `Public/index.php` | 0 |
| `fcm.ifreetalk.com` | PHP 应用/服务 | `master` | `6c3bc97c3bfb` | `public/index.php` | 0 |
| `fission.ifreetalk.com` | PHP 应用/服务 | `master` | `4576b856fe8f` | `public/index.php` | 0 |
| `framework` | PHP 应用/服务 | `master_backend` | `1f66aaec85cf` | `public/index.php` | 0 |
| `freetalk_common` | 公共包/协议 | `master` | `100a03dd9d21` | `composer.json、src/` | 0 |
| `freetalk_framework` | 公共包/协议 | `master` | `6f222d116acf` | `composer.json、src/` | 0 |
| `freetalk_lcache` | 公共包/协议 | `dev_0721_songxiu` | `29642bfd60d3` | `composer.json、src/` | 0 |
| `freetalk_protobuf` | 公共包/协议 | `dev_0721_songxiu` | `2c9806e4afb7` | `composer.json、src/` | 0 |
| `freetalk_qconf` | 公共包/协议 | `master` | `0aaa06f7300b` | `composer.json、src/` | 0 |
| `item.ifreetalk.com` | 脚本/站点/导出工具 | `online` | `01d06926af61` | `index.php` | 0 |
| `kf.ifreetalk.com` | PHP 应用/服务 | `master` | `c7f43e74e51c` | `public/index.php` | 0 |
| `list.ifreetalk.com` | 配置/模板 | `master` | `eef30976223d` | `config/` | 2 |
| `moment_service` | PHP 应用/服务 | `online` | `c01061ddb2ef` | `public/index.php` | 0 |
| `paipai-zone-make-server` | PHP 应用/服务 | `online` | `0762b30bd01d` | `Public/index.php` | 0 |
| `paipai-zone-show-builder` | 脚本/站点/导出工具 | `online` | `bc6096aaf90a` | `show_get.php、show_handle.php` | 0 |
| `private_chat_upload` | 脚本/站点/导出工具 | `online` | `9b8f11d1ff23` | `uploadpic_2.php、uploadvoice.php` | 0 |
| `prize_server` | PHP 应用/服务 | `90032` | `3e97163605f4` | `public/index.php` | 0 |
| `qr.ifreetalk.com` | PHP 应用/服务 | `online` | `20abbe893565` | `Public/index.php` | 0 |
| `qunicon.ifreetalk.com` | PHP 应用/服务 | `online` | `8d71fd0c1056` | `Public/index.php` | 0 |
| `ranch-info-service` | Go 服务 | `online` | `3505ae1545f8` | `go.mod、main.go` | 0 |
| `ranch-version.ifreetalk.com` | PHP 应用/服务 | `master` | `b2eb732888c6` | `public/index.php` | 0 |
| `ranch-web-service` | PHP 应用/服务 | `master` | `36a3cef2959d` | `public/index.php` | 1 |
| `ranch-web-shop` | PHP 应用/服务 | `online-back` | `70a85ad445e9` | `public/index.php` | 0 |
| `rank.ifreetalk.com` | PHP 应用/服务 | `master` | `da75b7971fae` | `public/index.php` | 0 |
| `salary-service` | PHP 应用/服务 | `master` | `397167293759` | `public/index.php` | 0 |
| `section.ifreetalk.com` | PHP 应用/服务 | `master` | `7a1706c68e30` | `Public/index.php` | 2 |
| `shop.ifreetalk.com` | PHP 应用/服务 | `dev_260508SeaShip` | `24cc206aaf92` | `public/index.php` | 0 |
| `statistics_service` | PHP 应用/服务 | `master` | `646d92c0f11a` | `Public/index.php` | 0 |
| `team.ithinktop.com` | 旧后台结构 | `master` | `123c201f06cf` | `index.php` | 0 |
| `user-album-upload` | 脚本/站点/导出工具 | `online` | `1b0ff35b803e` | `composer.json、finish_upload.php` | 0 |
| `user-console-server` | PHP 应用/服务 | `online` | `ac7363ab1010` | `public/index.php` | 0 |
| `view-backend.ifreetalk.com` | Vue 前端 | `90036` | `75db9fea8866` | `package.json、src/、config/` | 0 |
| `view-backend.ifreetalk.com_master` | Vue 前端 | `release` | `753f8253113e` | `package.json、src/、config/` | 0 |
| `web-img-service` | PHP 应用/服务 | `master` | `665854d55416` | `public/index.php` | 0 |
| `webtools.ifreetalk.com` | PHP 应用/服务 | `master` | `f18d8668dc6e` | `public/index.php` | 0 |
| `www.17paipai.cn` | 脚本/站点/导出工具 | `master` | `aeb69a41b13b` | `index.php` | 0 |

这里的改动条目数按 `git status --porcelain=v1` 行数统计，目录级未跟踪条目可能包含多个文件，不能视作精确文件数。已有改动保留原状。

收尾对照发现两处分析期间的检出变化：`backendV2.ifreetalk.com` 的 HEAD 从 `948ff4157207` 变为 `28135f914107`，分支仍为 `90032`；`backendV2.ifreetalk.com_master` 从 `online/ecc5ddb2521e` 变为 `master/31d2b8cabe34`。本任务未向这两处写入。比较前后版本，变化集中在后台审核与充值统计文件，新技能引用的发奖入口、需求入口和 GM 流程文档未变。上表保留开始时快照，不将期间变化混作本轮技能修改。

## 验证与使用

- 10 个技能均通过 `skill-creator/scripts/quick_validate.py`；9 个 UI YAML 配置完成结构及默认提示词校验。
- 37 个维护中的 Markdown 文件检查了 77 个本地文件链接，未发现缺失目标；17 个关键源码锚点均存在。链接统计不包含 GitHub/Gitee 外链或标题锚点。
- token 工具在 PHP 7.3.33 下通过 lint 和 14 个临时样例，覆盖注释/空白一致、业务值、字符串、HTML、heredoc、数组语法、尾逗号、错误参数/路径和不执行目标源码。未实跑 PHP 5.6，语法采用其支持的形式。
- 场景复核覆盖跨仓库参数、混合暂存状态和旧 PHP 脚本验证，未发现越过只读或外部副作用边界的指导。行为指导的检查不等同于执行生产业务。
- 原有与新增文件完成空白差异检查；58 个源码项目的暂存/未暂存/未跟踪状态与开始时一致，56 个项目的 branch/HEAD 同样一致，另外两处检出变化见上文。
- 上述结果只覆盖技能结构、工具行为和分析方法，不能据此推导所有业务已通过测试。
- 技能安装或同步由现有技能管理方式处理，本轮只更新用户指定的源仓库，未提交、推送或部署。
