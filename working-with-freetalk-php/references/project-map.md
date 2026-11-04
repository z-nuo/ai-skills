# 项目地图与事实源

本地图来自 2026-11-04 的源码盘点，路径相对用户指定的项目根目录。它提供搜索起点；分支、依赖和具体业务行为每次读取当前文件确认。

## 入口与目录差异

| 项目或项目组 | 优先检查的位置 | 定位要点 |
|---|---|---|
| `client_activity` | `Public/index.php`、`Application/controllers/`、`Vendor/`、`Application/config/` | 活动 Web 服务；`composer.json` 指定依赖目录 `Composer/`，业务类仍大量位于 `Vendor/` |
| `family.ifreetalk.com` | `Public/index.php`、`Application/controllers/`、`Application/Model/`、`Vendor/` | 房间/家族与推荐业务；业务 `Vendor/RecommendChatList/` 不能漏查 |
| `backendV2.ifreetalk.com`、`backendV2.ifreetalk.com_master` | `public/index.php`、`cmd/app.php`、`application/controllers/`、`application/module/`、`application/model/`、`application/config/`、`application/hooks/` | 运营后台；先读自身规则与 `docs/requirements/README.md`，GM 场景再读 `docs/gm-tool-development-process.md` |
| `shop.ifreetalk.com` | `public/index.php`、`application/controllers/`、`application/module/`、`application/model/`、`application/app/Module/`、`application/app/Model/` | 新旧目录并存；商城、资格、订单、支付与奖励需逐入口定位 |
| `prize_server` | `application/controllers/callBackGoods.php`、`application/module/callBackGoods/`、`application/module/goodsModule/`、`application/model/goodsSend/`、`application/model/goodsDeduct/` | 奖励中心接收、参数过滤/转换、发放与扣除；发送成功不等于最终到账 |
| `backend_script`、`cash`、`section.ifreetalk.com`、`qr.ifreetalk.com`、`statistics_service`、`paipai-zone-make-server` | `Public/index.php`、`Application/`，以及存在的 `Config/` | 大写目录服务；不可套用后台的小写路径 |
| `anchor.ifreetalk.com`、`backend_rpc`、`cli-share.ifreetalk.com`、`clientreport.ifreetalk.com`、`cooperate-ifreetalk-com`、`factory-service`、`family-om-service`、`fcm.ifreetalk.com`、`moment_service`、`ranch-version.ifreetalk.com`、`ranch-web-service`、`rank.ifreetalk.com`、`salary-service`、`user-console-server`、`webtools.ifreetalk.com` | 当前 `composer.json`、`public/index.php`、`application/`、存在的 `cmd/` | 多数为小写目录的 Freetalk 服务；版本、入口和框架使用各自文件确认 |
| `backend`、`team.ithinktop.com` | `index.php`、`application/`、`system/` | 旧管理后台结构，不能按 `backendV2` 推断路由 |
| `17paipai.cn`、`item.ifreetalk.com`、`www.17paipai.cn`、`private_chat_upload`、`user-album-upload`、`paipai-zone-show-builder` | 根目录 PHP、上传/下载回调及其 include 链 | 独立脚本也是应用入口；没有 `public/index.php` 不代表没有业务 |
| `activity_export_config` | 业务子目录的 `csv2array.php`、`build.php` 和 Composer 配置 | 配置导出工具；执行前检查生成路径与覆盖行为 |
| `config-cache-php-template-def`、`list.ifreetalk.com` | `config_template/`、`config/` | 模板或配置仓库；没有 HTTP 入口仍可能是配置消费者的上游 |
| `view-backend.ifreetalk.com`、`view-backend.ifreetalk.com_master` | `package.json`、`src/`、`config/webpack*.js` | Vue 管理前端，需要与实际 PHP 后台配对；`_master` 不表示当前分支 |
| `ranch-info-service` | `go.mod`、`main.go`、`module/` | Go 项目；项目根目录的名字不能决定语言与构建方法 |

未在表中逐项解释的项目同样先看真实入口和源码，不从域名推断完整业务职责。本表提供搜索起点，不要求每次任务遍历所有项目。

## 五个核心公共包

| 源码仓库 | Composer 包 | 主要检查位置 |
|---|---|---|
| `freetalk_common` | `freetalk/common` | `src/helper/`、`user/`、`map/`、`alliance/`、`prize/`、`prize2/`、`queue/`；工程规则在 `docs/` |
| `freetalk_framework` | `freetalk/framework` | `src/FTalk/` 的应用与分派，`src/store/` 的 IO wrapper |
| `freetalk_qconf` | `freetalk/qconf` | `src/qconf.php` 与 `src/unCgk*Type.php` 配置/资源枚举 |
| `freetalk_lcache` | `freetalk/lcache` | `src/FrontCache/`、缓存和配置模型；区分缓存值与权威数据 |
| `freetalk_protobuf` | `freetalk/protobuf` | `src/` 中生成消息类；字段、setter/getter 与实际安装版本逐项核对 |

`framework` 是另一个含 `public/`、`application/` 的项目，不等同于 `freetalk_framework`。公共库没有 Controller 是正常边界，不为保持目录一致而添加 Web 层。

依赖关系按三层核对：`composer.json` 的声明 → 存在的 `composer.lock` 中锁定版本/reference → 实际依赖目录的 installed 元数据、autoload 与实现。没有 lock 或依赖未安装时如实标注；兄弟包源码用于追踪开发，不证明调用服务已经使用该版本。

## 前后端联调

查 Vue 页面的请求路径、Mock、路由与菜单权限，再对到 PHP controller/module、参数和返回。加载问题同时检查请求成功/失败后的 loading 清理、`keep-alive`、`CustomRouterView` 及缓存恢复路径；Mock 响应不能证明真实权限或 RPC 可用。

运行构建前读取脚本与 webpack 插件。盘点时 `view-backend.ifreetalk.com/config/webpack.prod.js` 使用 `http-push-webpack-plugin-v5`，还会写 `config/env.js`；生产构建因此不是纯语法检查。优先采用已有本地 lint 或隔离构建方式，报告验证边界，不复制配置中的上传地址或凭据。
