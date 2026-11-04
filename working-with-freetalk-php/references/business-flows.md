# 业务链路与交接

## 字段与发奖

沿“来源配置/请求 → Controller → module → HTTP/RPC/队列 → 接收解析 → 最终操作”记录关键值、类型、转换条件和用途，定位第一次变化，不能只比入口与结果。

奖励问题从 `backendV2.ifreetalk.com/application/controllers/send.php` 和 `application/module/send/sailingProp/sendNavigationProps.php` 追到 `/callBackGoods/sendGoods`，再查 `prize_server/application/controllers/callBackGoods.php`、`application/module/callBackGoods/sendModule.php`、`application/module/goodsModule/planConfigFormat.php::filterGoods()` 及最终发放。

盘点时 `filterGoods()` 对 `goods_id > 1000000` 拼接 type/id，否则按 `goods_type * 100000 + goods_id` 重算 `item_id`；使用前重读当前完整分支。线上命中与实际 `TItems` 版本仍需请求和日志，发送成功不证明到账。

## 资格、支付与奖励

追实际存在的阶段：展示/资格 → 订单或领取记录 → 扣款 → 队列 → 消费者 → 发奖 → 结果。核对：

- user/section/map/season、活动和时间的来源，以及配置、额度 key、RPC 和奖励归属。
- 展示与实际购买契约、获取失败如何返回；锁在扣款前后的位置、释放与过期。
- 超时、重复回调、重投或中断时已提交什么，由谁确认和恢复。

定位可从 `shop.ifreetalk.com/application/app/Module/Shop/FIPowerGiftPkg.php`、`client_activity/Vendor/Shop/ShopPackageBuy.php` 开始，规则不能互换。`section_id=0` 的接受范围逐调用确认，不全局套用。

## 推荐、缓存与队列

从 `family.ifreetalk.com/Application/controllers/RecommendChatList.php` 与 `Vendor/RecommendChatList/RecommendChatRedis.php` 追写入/刷新事件、TTL、实时/缓存/回退优先级，再查筛选、计分、去重、排序和截断。

区分空值、失败与旧值；pipeline 命令和结果下标、范围端点和失败值逐项核对。队列消费成功与排名/业务更新分别确认。用户已确认的空消息处理、固定归属或停查历史数据作为当前验收约束，不自行加回读或迁移。

## 废弃与交接

删除前明确是方法、入口还是整个功能，双向搜索定义/调用及路由字符串、配置、模板、脚本/worker、测试和跨仓消费者，配合文件名检索。无静态调用不证明无流量；相似协议号不证明替代等价，入口删除后的响应由实际 dispatcher 决定。

仍有状态读取、优惠计算、持久化或页面时，不宣称整个功能已退役。只分析就交付影响与建议。

要求 Markdown 交接时围绕指定入口写业务目的、调用链、输入输出示例、数据结构与排序、配置/key/TTL、失败恢复和源码索引，复杂流程配 Mermaid。示例用虚构值，源码结论和线上未知项分开。
