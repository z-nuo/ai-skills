# 资源与运行边界

## QConf 与 Redis

沿“业务方法 → 资源 helper/wrapper → QConf01 名称或 QConf02/CGK → key 模板与参数 → Redis 命令”追踪，记录 cluster/group/id 等实际传入维度。配置入口、逻辑 key、物理实例是不同证据层级。

- 老式入口可从业务 `PPQConf::getRedisConf()` 等封装查配置名称；新式入口可从 `freetalk/framework` 的 `redis::getInstance()` 查 `unCgkRedisType`。
- `getKey()` 的实现查当前安装的 `freetalk/framework/src/store/driver/redisClient.php`；默认 key 还可能来自运行配置。枚举注释只能提供静态模板线索。
- 追踪读、写、删除、TTL、锁与 pipeline，确认 key 的维度是否与业务隔离一致。
- 从配置名不同不能推断物理 Redis 不同；静态 CGK 相同也不能证明当前线上路由、DB 和集群相同。运行配置未读取时说明边界。

用户要求既定的“共享存储静态扫描”口径时：同一 QConf01 入口或同一 QConf02/CGK 下存在多个不同规范化逻辑 key 才算代码级共享入口，单个 key 的多处调用合并。将 `%s`、`%d`、`%u`、`%llu` 等动态占位归为 `{placeholder}`，保留固定文本、占位数量与顺序；这是静态分组，不等同于物理实例冲突。

如同时提供 `新老集群key合并.xlsx`，其 `xcache-集群汇总` 的 `database_type` 对应 CGK，`db_user` 对应 key 模板；仅统计同一 CGK 下多个不同 `db_user`，跨 CGK 复用不作为该口径的风险。未提供文件时不生成或沿用旧统计。扫描只排除确认的依赖/生成代码，无入口的公共包作为依赖线索保留，独立 PHP 脚本仍属于入口。

## RPC、奖励与不确定结果

查实际服务枚举、请求/响应包号、生成消息类和字段，再查公共返回解析。`freetalk/common/src/helper/thriftHelper.php::RsParseInnerCallBack()` 是一个现有解析入口；其已处理的传输、协议和业务错误优先复用，特殊结构按真实契约处理。

对改变状态的调用，超时、断连或解析失败可能发生在服务端提交之后。保留关联标识，返回“结果未确认”，先核对下游记录再决定是否重试；不能把所有异常视作未执行。业务成功但操作日志失败时，分别报告两者，避免用户重复发放。

记录请求结构时对 token、密码、解密连接信息和鉴权字段脱敏；日志方法名包含 `DEBUG` 不表示自动脱敏。分析需要原对象时返回原对象，仅对日志副本脱敏。

## 缓存和常驻进程

查 `freetalk_lcache`、`FrontCache/FrontData` 和当前调用方缓存封装，区分权威数据、前置缓存和本地缓存。Yac 分析需覆盖扩展存在与缺失、命中、未命中、空值、异常和 TTL；遵循现有 `class_exists('\\Yac')` 降级契约。

修改 worker、定时任务、静态缓存或公共类后，检查实际进程启动方式、类何时加载和缓存更新方式。发布审查列出需要重启/回收的进程与顺序，不直接操作生产环境。

## 本地验证

先读项目的测试矩阵和脚本 bootstrap，再按副作用分类：

| 分类 | 验证范围 | 执行条件 |
|---|---|---|
| 静态 | lint、JSON、diff、依赖声明与源码追踪 | 使用正确版本，通常可直接运行 |
| 隔离逻辑 | 纯函数、最小替身、协议/参数构造 | 确认不加载真实资源，不调用生产副作用 |
| 集成 | QConf、Redis、Thrift、Kafka、HTTP、数据库、对象存储 | 环境、测试数据、清理和执行授权明确 |

`tests/` 不代表可离线运行。`freetalk_common/docs/runnable-tests.md` 将发奖、联盟和地图脚本列为集成测试；不要为了得到“通过”直接执行。缺失 PHP 5.6 或目标扩展时可以继续静态分析，但不能用 PHP 7.3/8 的 lint 证明 PHP 5.6 可运行。
