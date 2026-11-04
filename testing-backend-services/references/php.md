# PHP 测试

读取 Composer scripts、PHPUnit/Pest 配置、bootstrap 和 CI，沿用已安装版本及现有写法。依赖目录从 `config.vendor-dir` 与 autoload 确认，不默认 `vendor/`，不排除自有业务 `Vendor/`。

## 版本与副作用

目标 PHP、扩展、时区和进程模型须匹配；新版 lint 不证明 PHP 5.6/7.3 兼容，示例语法也按目标版本选择。

没有测试框架时先读脚本；`tests/` 可能真实调用 QConf、Redis、RPC、支付和奖励。环境不满足则做静态检查或隔离替身验证，不为单次任务升级 runtime、安装框架或运行真实副作用。

仅注释/格式变化使用 lint 和适当 token 比较，另查 docblock、`__LINE__` 等特殊语义。

## 断言与边界

- 使用严格类型断言，如 `assertSame`；异常断言稳定类型/code/必要字段，完整文本只有属于契约时才比较。
- fixture 展示决定行为的值；已有 fake/clock 可控时间与 ID，不通过弱断言隐藏问题。
- 替身只隔离外部边界，结构与错误语义符合真实响应。事务、持久化和缓存语义不能靠 mock 证明。
- HTTP 从真实路由、中间件及错误处理调用；gRPC 使用生成 client。按契约验证权限、错误、幂等及必要副作用。

## 状态与执行

恢复本次修改的静态缓存、全局状态、环境、timezone、ini 和 error handler。长驻进程验证连续请求不串上下文；事务 rollback 不能清理其他连接、异步写入或自动提交 DDL。

优先运行已有 Composer 命令，缩小范围时用实际依赖目录内 PHPUnit/Pest 的 filter 或 suite。先目标用例、再相关套件；记录退出码、失败/跳过、warning/deprecation，不忽略异常输出后声称全部通过。
