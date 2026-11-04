---
name: designing-microservice-interfaces
description: 设计或评审 REST/OpenAPI、gRPC/Protobuf 契约及其兼容演进、弃用和迁移。
---

# 微服务接口设计

先读已有 OpenAPI/Protobuf、路由、鉴权、错误和生成配置，确认所有者、消费者与数据边界。沿用现有协议约定，以调用方需要的最小稳定表面定义契约。

- 明确输入输出、缺省/空值、失败、超时与取消；只补当前操作需要的权限、幂等、并发或分页语义。
- 字段之外，默认值、排序、错误及重试行为也属于兼容契约。已有消费者或删除/迁移场景须核对消费者范围。
- 只有真正需要选择协议时才比较 REST/gRPC；已有协议不因语言偏好替换。双协议记录网关转换规则。
- 设计请求交付必要的契约草案；评审先给具体路径、RPC 或字段上的发现。业务实现按用户要求生成。
- 用仓库已有 parser、linter、`buf` 或 `protoc` 验证，说明未运行项；不为单次设计引入工具依赖。

## 按需参考

- REST/OpenAPI：[REST](references/rest.md)。
- gRPC/Protobuf：[gRPC](references/grpc.md)。
- 现有消费者、弃用与迁移：[兼容性](references/compatibility.md)。

消费者未知时不宣称向后兼容；契约工具通过也不证明行为兼容。
