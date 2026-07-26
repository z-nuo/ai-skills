# REST 与 OpenAPI 参考

## 目录

- [契约基线](#契约基线)
- [HTTP 语义](#http-语义)
- [分页过滤与排序](#分页过滤与排序)
- [幂等与并发控制](#幂等与并发控制)
- [鉴权与安全](#鉴权与安全)
- [OpenAPI 31 示例](#openapi-31-示例)
- [兼容性提示](#兼容性提示)
- [评审清单](#评审清单)

## 契约基线

- 优先使用仓库已固定的 OpenAPI 版本；没有约定时使用 OpenAPI 3.1。
- 使用资源名表达领域对象，使用 HTTP method 表达操作。仅在无法建模为资源状态变化时使用动作端点。
- 为每个操作定义稳定且唯一的 `operationId`、请求约束、成功响应和所有预期错误。
- 分离创建或更新输入与响应模型，不允许客户端提交服务端生成、审计或权限字段。
- 明确字段的 `required`、`nullable`、默认值、格式和边界。区分“未提供”“空值”和“空集合”。
- 遵循现有字段命名和 envelope 约定；不要只为新接口引入另一种风格。

## HTTP 语义

| Method | 典型用途 | 关键约束 |
|---|---|---|
| `GET` | 查询资源或集合 | 安全且幂等，不产生业务副作用 |
| `POST` | 创建资源或提交非幂等动作 | 可重试操作必须设计幂等机制 |
| `PUT` | 完整替换已知 URI 的资源 | 幂等，必须说明缺失字段的含义 |
| `PATCH` | 部分更新 | 明确未提供字段和显式 `null` 的区别 |
| `DELETE` | 删除资源 | 优先设计为幂等，并说明重复删除结果 |

常用状态码：

- `200`：查询或带响应体的成功操作。
- `201`：资源已创建，同时返回资源或 `Location`。
- `202`：请求已接收但异步处理尚未完成，并提供状态查询方式。
- `204`：成功且没有响应体。
- `400`：请求结构、语法或参数无效。
- `401`：未认证；`403`：已认证但没有权限。
- `404`：资源不存在，或基于安全策略隐藏其存在性。
- `409`：资源状态、唯一性、幂等键或业务并发冲突。
- `412`：`If-Match` 等前置条件失败。
- `422`：请求结构有效但业务语义无法处理；使用前先遵循仓库约定。
- `429`：超过限流，并在可用时返回重试信息。

错误响应必须统一。已有错误模型时复用；没有约定时优先采用 RFC 9457 `application/problem+json`，至少提供稳定的机器可读类型、HTTP 状态和安全的人类可读说明。不要返回堆栈、SQL、内部主机或敏感字段。

## 分页、过滤与排序

- 高频变化或大数据集合优先使用 cursor pagination；后台管理等稳定小集合可使用 offset/page pagination。
- cursor 必须不透明，并绑定过滤与排序上下文，避免调用方构造内部游标。
- 定义默认和最大 page size、稳定排序以及唯一的 tie-breaker。
- 返回下一页游标或明确的分页元数据，不依赖响应数组长度猜测是否结束。
- 明确过滤参数是否可组合、时间边界是否包含，以及未知参数如何处理。

## 幂等与并发控制

客户端可能重试的创建、扣款、提交等 `POST` 操作使用 `Idempotency-Key` 或等价业务键：

- 定义键的作用域、保留时间和请求 fingerprint。
- 相同键与相同请求返回同一业务结果；相同键与不同请求返回冲突。
- 幂等记录与业务写入必须具有原子性，避免并发请求同时执行。
- 文档化处理中、成功和失败结果是否可重放。

需要防止丢失更新时返回 `ETag`，写操作要求 `If-Match`。版本不匹配返回 `412`，不要静默覆盖较新的状态。

## 鉴权与安全

- 在 OpenAPI `securitySchemes` 和每个操作的 `security` 中表达认证方式。
- 定义操作所需 scope 或权限，并说明租户边界和资源级授权。
- 标记敏感字段，限制其输入、输出、日志和错误回显。
- 写明限流维度、审计字段和关联请求的 trace/request ID；不要将敏感数据放入 ID。

## OpenAPI 3.1 示例

下面的片段展示幂等创建和统一错误的核心形状。实际交付时补齐领域字段、鉴权与所有预期响应。

```yaml
openapi: 3.1.0
info:
  title: Order API
  version: 1.0.0
paths:
  /orders:
    post:
      operationId: createOrder
      summary: Create an order
      parameters:
        - name: Idempotency-Key
          in: header
          required: true
          schema:
            type: string
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/CreateOrderRequest'
      responses:
        '201':
          description: Order created
          headers:
            Location:
              schema:
                type: string
                format: uri-reference
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/Order'
        '409':
          description: Idempotency or state conflict
          content:
            application/problem+json:
              schema:
                $ref: '#/components/schemas/Problem'
components:
  schemas:
    CreateOrderRequest:
      type: object
      required: [customerId]
      properties:
        customerId:
          type: string
    Order:
      type: object
      required: [id, customerId, status]
      properties:
        id: { type: string }
        customerId: { type: string }
        status: { type: string, enum: [PENDING, CONFIRMED] }
    Problem:
      type: object
      required: [type, title, status]
      properties:
        type: { type: string, format: uri-reference }
        title: { type: string }
        status: { type: integer, minimum: 400, maximum: 599 }
        detail: { type: string }
```

## 兼容性提示

- 增加可选请求字段通常兼容；增加必填请求字段是破坏性变更。
- 增加响应字段通常兼容，但必须检查严格反序列化客户端。
- 删除、改名、改变字段类型或枚举语义属于破坏性变更。
- 改变默认排序、错误码、空值语义或幂等行为也是行为破坏，即使 schema 未改变。

## 评审清单

- 每个操作是否有明确输入、输出、错误和权限？
- 列表是否具有有界分页和稳定排序？
- 可重试写操作是否具有完整幂等语义？
- 并发写是否可能丢失更新，是否需要 `ETag`/`If-Match`？
- OpenAPI 是否可解析，所有 `$ref` 是否存在，示例是否符合 schema？
