# Go 后端测试参考

## 目录

- [仓库约定](#仓库约定)
- [测试组织](#测试组织)
- [断言与辅助函数](#断言与辅助函数)
- [依赖与Test-Double](#依赖与test-double)
- [HTTP与gRPC](#http与grpc)
- [并发与时间](#并发与时间)
- [命令与证据](#命令与证据)
- [评审清单](#评审清单)

## 仓库约定

先阅读 `go.mod`、Makefile、现有 `_test.go`、CI 和测试辅助包。复用已有断言库、fixture、container helper 和命令，不为单个测试引入新框架。

- 标准库 `testing` 是默认选择。
- 仓库已有 Testify、go-cmp、gomock 或其他库时遵循现有模式，但仍测试行为而不是框架调用。
- 使用与生产代码相同的 Go toolchain 和 build tags。
- 明确测试是否需要 CGO、Docker、网络、时区或环境变量。

## 测试组织

- 测试文件使用 `_test.go`，测试名描述场景和结果。
- 需要访问未导出实现且仓库已有惯例时使用同 package；验证公共 API 时优先使用 `_test` package。
- 多个输入共享同一行为时使用 table-driven tests 和 `t.Run`，不要把不同业务规则塞进一张巨大表。
- 每个 subtest 只验证一个清晰结果；失败信息包含输入和实际值。
- 使用 `t.Helper()` 标记辅助函数，使失败定位到调用测试。
- 使用 `t.Cleanup()`、`t.TempDir()` 和 `t.Setenv()` 管理生命周期，避免手工全局清理。

典型 table-driven 结构：

```go
func TestNormalizeEmail(t *testing.T) {
	tests := []struct {
		name    string
		input   string
		want    string
		wantErr bool
	}{
		{name: "trims and lowercases", input: " Alice@Example.COM ", want: "alice@example.com"},
		{name: "rejects empty", input: "  ", wantErr: true},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			got, err := NormalizeEmail(tt.input)
			if (err != nil) != tt.wantErr {
				t.Fatalf("NormalizeEmail() error = %v, wantErr %v", err, tt.wantErr)
			}
			if !tt.wantErr && got != tt.want {
				t.Errorf("NormalizeEmail() = %q, want %q", got, tt.want)
			}
		})
	}
}
```

## 断言与辅助函数

- 对标量和小结构优先使用清晰的标准库比较。
- 复杂结构且仓库已有 go-cmp 时使用 `cmp.Diff(want, got)`，显式处理不可比较或需忽略字段。
- 不因为时间戳或随机 ID 难断言就忽略整个对象；注入 clock/ID generator 或逐字段验证契约。
- 错误测试使用 `errors.Is`/`errors.As` 或稳定错误类型，不比较易变的完整错误文本，除非文本本身是公共契约。
- helper 不隐藏 Arrange 中决定行为的关键数据，也不吞掉错误。

## 依赖与 Test Double

- 领域服务单元测试可使用手写 fake 或 stub 隔离数据库、时钟和外部 API。
- 先理解真实依赖的输入、输出和副作用，再决定 mock 层级。
- 不断言 mock 调用次数来替代业务结果；只有交互本身是契约时才验证调用。
- Repository、SQL、Redis、序列化和事务语义使用真实依赖集成测试，读取 [integration.md](integration.md)。仓库没有封装时通常使用 `testcontainers-go`；不要重复实现已有 container helper。
- 外部 HTTP 服务可用 `httptest.Server` 提供真实协议边界，并返回完整、符合契约的响应。

## HTTP 与 gRPC

### HTTP

- 使用 `httptest.NewRequest` 和 `httptest.NewRecorder` 验证 handler/router 的状态码、headers 和响应体。
- 需要 middleware、真实端口或客户端行为时使用 `httptest.NewServer`。
- 通过生产路由装配入口构建测试服务，避免绕过鉴权、解码或错误中间件。
- 验证错误、鉴权、幂等、分页和 malformed payload，不只验证 `200`。

### gRPC

- 使用生成 client 调用测试 server；仓库已有 in-process/bufconn helper 时复用。
- 验证 gRPC status code、details、metadata、deadline 和取消传播。
- 不直接调用 service method 后声称完成了 gRPC 传输契约测试。

## 并发与时间

- 只有测试和被测代码不共享可变全局状态、固定端口、同一数据库记录或环境变量时才调用 `t.Parallel()`。
- 在循环 subtest 中确保当前 Go 版本的变量捕获语义正确，必要时显式复制 case。
- 并发行为使用 channel、barrier、context 或条件等待同步，不使用固定 `time.Sleep` 猜测时序。
- 时间相关逻辑优先注入 clock；必须使用真实时间时给出合理 deadline，并避免过窄窗口。
- 涉及 goroutine、共享状态或缓存时运行 race detector。

## 命令与证据

先运行最小测试：

```bash
go test ./path/to/package -run '^TestName$' -count=1
```

再扩大范围：

```bash
go test ./path/to/package -count=1
go test ./... -count=1
go test -race ./... -count=1
```

命令范围以仓库脚本和成本为准。缓存可能隐藏重复性问题时使用 `-count=1`。只在并发相关或项目要求时运行昂贵的全仓库 `go test -race`，并报告未运行原因。

覆盖率用于发现盲区：

```bash
go test ./... -coverprofile=coverage.out
go tool cover -func=coverage.out
```

不要为了百分比测试 getter、标准库或无业务意义的分支。

## 评审清单

- 测试是否通过公共行为捕获回归，而不是复制实现？
- RED 是否因目标行为缺失而失败？
- table cases 是否代表真实边界，而非只增加数量？
- `t.Parallel()`、环境变量、端口和测试数据是否隔离？
- goroutine 是否能结束，资源是否通过 `t.Cleanup()` 释放？
- HTTP/gRPC 测试是否真正经过传输和 middleware 边界？
- 运行记录是否包含最小测试和相关测试集的实际退出结果？
