# gRPC 与 Protobuf

遵循仓库 `package`、`go_package`、`php_namespace` 和生成版本约定。RPC 围绕领域操作，使用明确的请求/响应类型；时间、单位和字段 presence 按契约表达。

## 演进

- field number 永不复用；删除字段后 `reserved` 编号及名称。
- 改字段类型、`repeated`、`oneof` 或默认语义时，同时核对 wire、生成源码与消费者行为。
- enum 零值采用未指定语义，消费者能处理未知值；新增 enum 不自动保证穷举调用方兼容。

## 调用语义

使用稳定 gRPC status 与必要 details，区分未认证和无权限；metadata 不暴露敏感信息。deadline 向下传递剩余预算，取消须传到实际依赖。

写操作只有幂等且关联标识稳定时才可安全重试；超时不证明未执行。分页 token 绑定过滤与稳定排序。仅真实需要增量或双向通信时用 stream，并定义背压、断开与恢复。

使用现有 `buf lint`、`buf breaking` 或 `protoc`；breaking 基线来自实际已发布契约，不硬编码 `main`。生成成功不证明错误、顺序及幂等行为兼容。
