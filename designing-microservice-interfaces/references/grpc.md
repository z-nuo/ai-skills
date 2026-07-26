# gRPC 与 Protobuf 参考

## 目录

- [契约基线](#契约基线)
- [服务与消息设计](#服务与消息设计)
- [字段与枚举演进](#字段与枚举演进)
- [错误模型](#错误模型)
- [Deadline取消与重试](#deadline取消与重试)
- [分页与流式调用](#分页与流式调用)
- [Protobuf 示例](#protobuf-示例)
- [验证与评审](#验证与评审)

## 契约基线

- 使用仓库既有 `syntax`、package、目录和代码生成约定；新契约通常使用 `proto3`。
- package 表达稳定命名空间和显式 API 代际，例如 `orders.v1`，不要映射到内部目录层级。
- 明确 `go_package`、`php_namespace` 等生成选项，防止不同项目生成出冲突的类型。
- 为每个 RPC 使用独立 request/response message，为后续增加字段保留空间。
- 契约先于服务实现提交，并由生产者和消费者共同评审。

## 服务与消息设计

- Service 按业务能力聚合 RPC，不按数据库表或内部类拆分。
- RPC 名称使用清晰动词，例如 `GetOrder`、`CreateOrder`、`ListOrders`。
- 请求只包含调用方可控制的值；响应可以包含服务端 ID、状态和审计时间。
- 使用 `google.protobuf.Timestamp`、`Duration` 等标准 well-known types 表达时间语义，不使用无单位整数。
- 明确字段单位、格式、最大长度、归一化规则和敏感性；Protobuf 类型本身不能表达全部业务约束。
- 不用 `map<string, string>` 或无结构 `bytes` 逃避领域建模，除非键值空间和编码格式确实开放。

## 字段与枚举演进

字段一旦发布：

- 永远不要改变 field number，也不要将旧编号分配给新语义。
- 删除字段时同时 `reserved` 其编号和名称。
- 不要改变字段类型、`repeated`/singular 形态或 `oneof` 归属；线格式可解析不代表源代码或行为兼容。
- 新字段使用新编号并保持旧调用方可接受的默认语义。
- 需要区分“未提供”和默认值时使用 `optional`、wrapper 或显式状态字段，并遵循仓库约定。
- 谨慎移动字段到现有 `oneof`，旧客户端可能同时发送多个字段而改变最终解析结果。

每个 enum 的零值使用 `<TYPE>_UNSPECIFIED = 0`。增加 enum 值通常线兼容，但可能破坏调用方的穷举分支；迁移评审必须检查 Go/PHP 消费者如何处理未知值。

## 错误模型

- 使用规范 gRPC status code 区分参数错误、未认证、无权限、不存在、冲突、限流和内部故障。
- 仓库使用 Google RPC 模型时，以 `google.rpc.Status` 和稳定的 error details 传递机器可读信息。
- 不依赖自由文本判断错误类型，不向 description/details 写入堆栈、SQL 或敏感数据。
- 明确哪些错误可重试、建议等待时间以及请求是否可能已产生副作用。
- 将认证与授权分开：`UNAUTHENTICATED` 表示身份无效，`PERMISSION_DENIED` 表示身份有效但无权限。

## Deadline、取消与重试

- 客户端为每次调用设置 deadline，并从上游剩余预算向下传播。
- 服务端监听取消信号，停止不再需要的下游调用和昂贵工作。
- 只自动重试明确幂等或具有幂等键的 RPC；定义最大次数、退避、jitter 和可重试状态码。
- 创建、扣款等操作在 request 中提供稳定的业务请求 ID，或使用仓库统一的 metadata 幂等键。
- 不通过无限 deadline 或无限重试掩盖容量、依赖或契约问题。

## 分页与流式调用

普通列表 RPC 使用 `page_size`、不透明的 `page_token` 和响应 `next_page_token`。定义默认上限、最大值、稳定排序和 token 失效条件。

只在调用需要增量结果、长连接或双向交互时使用 streaming。对流式 RPC 明确消息顺序、背压、断线恢复、重复消息、半关闭和最大消息大小。

## Protobuf 示例

```proto
syntax = "proto3";

package orders.v1;

option go_package = "example.com/contracts/gen/orders/v1;ordersv1";
option php_namespace = "Example\\Contracts\\Orders\\V1";

service OrderService {
  rpc CreateOrder(CreateOrderRequest) returns (Order);
  rpc ListOrders(ListOrdersRequest) returns (ListOrdersResponse);
}

message CreateOrderRequest {
  string request_id = 1;
  string customer_id = 2;
}

message ListOrdersRequest {
  int32 page_size = 1;
  string page_token = 2;
}

message ListOrdersResponse {
  repeated Order orders = 1;
  string next_page_token = 2;
}

message Order {
  string id = 1;
  string customer_id = 2;
  OrderStatus status = 3;
  reserved 4;
  reserved "legacy_note";
}

enum OrderStatus {
  ORDER_STATUS_UNSPECIFIED = 0;
  ORDER_STATUS_PENDING = 1;
  ORDER_STATUS_CONFIRMED = 2;
}
```

## 兼容性提示

- 新增 message、service、method 或新编号的可选字段通常线兼容，但仍要检查生成代码和行为影响。
- 改变 package、service/method 名称、field number、字段类型或 RPC streaming 形态属于破坏性变更。
- 删除字段但未 `reserved` 会留下编号复用风险。
- 改变默认值解释、排序、错误状态或重试副作用属于行为破坏。

## 验证与评审

优先运行仓库已有命令，例如：

```bash
buf lint
buf breaking --against '.git#branch=main'
```

没有 Buf 配置时，至少使用仓库的 `protoc` 生成或 descriptor 构建命令验证 import、类型和语法。不要只凭文本外观判断 `.proto` 有效。

评审时确认：

- 字段编号唯一且没有复用，删除字段已 `reserved`。
- enum 有明确零值，调用方能处理未知枚举。
- deadline、取消、幂等和重试语义完整。
- 分页 token 不透明且排序稳定。
- 错误具有稳定机器语义且不泄露内部信息。
