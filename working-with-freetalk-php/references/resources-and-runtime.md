# 资源与运行边界

## QConf / Redis

沿“业务 → helper/wrapper → QConf01 或 QConf02/CGK → key 模板/参数 → Redis 命令”追读写、删除、TTL、锁与 pipeline，记录 cluster/group/id。`PPQConf::getRedisConf()`、`redis::getInstance()`、`unCgkRedisType` 是搜索线索；`getKey()` 查实际安装的 `freetalk/framework/src/store/driver/redisClient.php`，默认 key 可能来自运行配置。

配置入口、逻辑 key 和物理实例是不同证据；配置名不同不证明实例不同，静态同 CGK 也不证明线上路由/DB 相同。

共享存储静态扫描按约定统计：同 QConf01 入口或同 QConf02/CGK 下多个不同规范化逻辑 key；同 key 多调用合并。`%s/%d/%u/%llu` 等归为 `{placeholder}`，保留固定文本及占位数量/顺序。不是物理冲突证明。

提供 `新老集群key合并.xlsx` 时，`xcache-集群汇总` 的 `database_type` 对应 CGK、`db_user` 对应 key；只计同 CGK 多个不同 key，跨 CGK 复用允许。未提供文件不沿用旧统计。只排除确认的依赖/生成代码，无入口公共包保留为依赖线索，独立脚本仍是入口。

## RPC 与不确定结果

核对服务枚举、包号、生成消息字段和公共返回解析，优先复用 `thriftHelper.php::RsParseInnerCallBack()` 等现有处理。

写操作超时、断连或解析失败可能已提交，保留关联标识并先核对下游状态，不能把异常当未执行自动重试。业务成功而操作日志失败时分别报告，避免再次发放。

密码、token、解密连接与鉴权信息只写脱敏日志副本，分析/返回仍用必要的原对象；`DEBUG` 名称不代表自动脱敏。

## 缓存与验证

区分权威、FrontCache/FrontData 与本地缓存。Yac 核对扩展存在/缺失、命中/未命中、空值、异常与 TTL，沿用现有 `class_exists('\Yac')` 降级契约。

先读测试矩阵、bootstrap 和资源连接。静态检查使用正确版本；隔离逻辑不加载真实资源；集成运行须明确环境、数据、清理及授权。`tests/` 可能实际发奖或写 Redis，`freetalk_common/docs/runnable-tests.md` 可作分类线索。目标 PHP 不可用时如实说明，新版 lint 不证明旧版兼容。

worker、定时进程和公共类按实际加载/缓存更新机制判断生效条件，发布时列重启/回收顺序，不将待操作写成已执行。
