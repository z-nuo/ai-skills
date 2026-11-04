# 业务调用链的分析与交接

## 定位字段第一次变化

不要只对比入口与最终结果。在每个交接位置记录原字段、类型、来源配置、变换条件、输出字段和消费方式：

| 位置 | 原始值/来源 | 转换与条件 | 输出值 | 下游用途 |
|---|---|---|---|---|
| Controller → module | 请求参数或已有配置 | cast、默认值、查表 | 业务参数 | 校验或提交 |
| module → RPC/HTTP/队列 | 业务对象 | 序列化、字段映射 | 协议字段 | 下游接收 |
| 下游解析 → model | 请求/消息 | 过滤、重算、兼容 | 实际操作对象 | 扣除、记录或奖励 |

当前源码的一个搜索起点是：

```text
backendV2.ifreetalk.com/application/controllers/send.php
  → application/module/send/sailingProp/sendNavigationProps.php
  → HTTP /callBackGoods/sendGoods
prize_server/application/controllers/callBackGoods.php
  → application/module/callBackGoods/sendModule.php
  → application/module/goodsModule/planConfigFormat.php::filterGoods()
```

盘点时 `filterGoods()` 在 `goods_id > 1000000` 时拼接 `goods_type` 与 `goods_id`，否则按 `goods_type * 100000 + goods_id` 重算 `item_id`。复查时读取完整分支和当前配置，不能把一种输入下的公式当作所有奖励类型的规则。源码证明“存在变换”后，仍需实际请求、`TItems` 版本及日志确认线上是否经过该分支。

## 资格、支付与奖励

从资格展示追到实际购买和交付：展示/资格 → 订单或领取记录 → 扣款 → 队列 → 消费者 → 发奖 → 结果记录。路径实际没有某一阶段时说明，不能补画假想组件。

逐步回答：

- `user_id`、`section_id`、`map_id`、`season`、活动编号和时间来自哪里，是否参与配表、key 或 RPC？
- 显示条件与支付/领取时的条件是否采用同一业务契约？地图/赛季的获取失败如何返回？
- 锁在扣款前还是扣款后写入，过期和释放由谁负责，哪些失败可能留下锁？
- 超时、重复回调、队列重复或进程中断后，哪些状态已提交，谁能确认和恢复？
- 队列入队、RPC 应答或日志写入成功分别证明哪一层完成？

`shop.ifreetalk.com/application/app/Module/Shop/FIPowerGiftPkg.php` 和 `client_activity/Vendor/Shop/ShopPackageBuy.php` 是现有代码的定位入口，不代表二者规则可以互换。修改 `section_id` 前沿每个调用检查额度 key、RPC、配置和奖励归属；不能把一个入口接受 `0` 推广到全部入口。

## 推荐、缓存与队列

`family.ifreetalk.com/Application/controllers/RecommendChatList.php` 与 `Vendor/RecommendChatList/RecommendChatRedis.php` 可作为推荐业务的搜索起点。完整说明包括：

1. 原始数据由哪个事件/脚本写入，何时刷新，TTL 与清理条件是什么。
2. 实时来源、缓存和回退的优先级，空值、失败和旧值是否可区分。
3. 候选筛选、计分、排序、去重、截断与最终返回在哪一步完成。
4. pipeline 的命令与结果下标是否对应，失败值如何处理，分页范围的端点是否包含。
5. 队列消费、排名更新和业务副作用是否有不同的完成条件。

用户确认的空消息处理、固定归属或停查历史数据属于当前业务约束；保留这个约束并验证调用链，不额外加入历史回读或迁移。

## 废弃闭环

先明确用户要删除的是方法、入口还是整个功能。对定义、直接调用、字符串/路由注册、模板、配置、脚本/worker、测试和外部消费者做双向搜索；同时查文件名，避免只搜方法名。

对每个保留调用说明失败或降级契约。协议号相近、方法名字相似均不能证明替代行为等价。入口删除后如何返回由真实 dispatcher 决定，不能假定是 404。

若功能仍有状态读取、优惠计算、持久化或页面入口，只能说明已删除目标方法，不能宣布整个功能退役。用户只要求分析时，输出处置建议和影响，不执行删除。

## 交接文档

文档围绕指定入口组织，按需要包含业务目的、入口与调用链、数据来源、字段示例、key/TTL、排序规则、正常及失败流程、消费者/定时任务、源码索引和运行边界。示例使用虚构标识符，给出值之间的关系，避免写真实账号或凭据。

源码能确认的机制直接给出证据；配置值、队列积压、RPC 实际返回、到账或部署状态未查询时明确标注未验证。
