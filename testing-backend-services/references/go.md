# Go 测试

先确认 `go.mod`、toolchain、build tags 与已有命令，优先标准 `testing` 及现有库。

## 用例与隔离

- table-driven cases 对应真实边界，名称说明场景与结果；辅助函数用 `t.Helper()`，资源用 `t.Cleanup()`、`t.TempDir()` 等管理。
- 用 `errors.Is/As` 断言错误，随机 ID/时间使用已有可控机制；断言实际业务字段，不因难比较而只验证“非空”。
- `t.Parallel()` 仅用于状态隔离的用例，循环变量捕获按项目 Go 版本处理。并发同步使用 channel/context，等待有 deadline，不靠固定 sleep。

## 验证边界

HTTP 使用 `httptest` 经过实际路由与中间件；gRPC 用生成 client 调用测试 server（可用现有 `bufconn`）。直接调 handler/service 只证明对应进程内行为，不证明传输契约。

SQL、锁、Redis Lua/TTL 或 broker 确认语义用真实依赖；纯逻辑不必引入容器。并发断言最终结果、幂等或资源释放，不只断言调用次数。

## 执行

先运行最小用例，再相关包：

```bash
go test ./path/to/pkg -run '^TestName$' -count=1
go test ./path/to/pkg -count=1
```

并发风险或仓库要求时运行 `-race`；覆盖率用于找盲区，不为数字补无意义用例。记录实际命令、失败、跳过及未运行范围。
