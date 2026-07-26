# PHP 后端测试参考

## 目录

- [仓库约定](#仓库约定)
- [PHPUnit与Pest](#phpunit与pest)
- [用例与断言](#用例与断言)
- [依赖与Test-Double](#依赖与test-double)
- [HTTP与契约测试](#http与契约测试)
- [状态隔离](#状态隔离)
- [命令与证据](#命令与证据)
- [评审清单](#评审清单)

## 仓库约定

先阅读 `composer.json`、`phpunit.xml`、`phpunit.xml.dist`、`Pest.php`、测试基类、框架配置和 CI。优先运行 Composer scripts，复用仓库已有 factory、fixture、容器和数据库重置策略。

- 使用仓库已选定的 PHPUnit 或 Pest 版本和写法，不在同一模块混入另一种风格。
- 使用与生产一致的 PHP 版本、extensions、时区和序列化配置。
- 明确测试运行于普通 CLI 进程还是 Swoole/Hyperf 等长驻进程模型。
- 不为单个测试增加 Mockery、Prophecy 或新断言库；只有仓库已有时复用。

## PHPUnit 与 Pest

PHPUnit 测试方法或 Pest case 的名称必须表达场景和结果。共享输入矩阵时使用 data provider 或 Pest dataset，但每个 case 仍只验证一个行为。

PHPUnit data provider 示例：

```php
<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EmailNormalizerTest extends TestCase
{
    #[DataProvider('emailCases')]
    public function testItNormalizesValidEmail(string $input, string $expected): void
    {
        self::assertSame($expected, EmailNormalizer::normalize($input));
    }

    public static function emailCases(): iterable
    {
        yield 'trim and lowercase' => [' Alice@Example.COM ', 'alice@example.com'];
        yield 'already normalized' => ['bob@example.com', 'bob@example.com'];
    }
}
```

仓库使用旧版 PHPUnit 时遵循其 annotation 或配置，不为了复制示例升级框架。

## 用例与断言

- 优先使用严格断言，例如 `assertSame`，避免类型转换掩盖错误。
- 对异常断言稳定类型、业务 code 和必要字段；只有文本本身是契约时才比较完整 message。
- 对数组和 DTO 验证业务字段，不直接 snapshot 含时间戳、随机 ID 或无关顺序的整个对象。
- 时间和 ID 难以预测时注入 clock/ID generator，或复用框架已有 fake；不要放宽成“非空即可”。
- fixture/factory 显式展示决定行为的值，不让全局默认值悄悄改变测试含义。
- 使用 `setUp`/`tearDown` 或 framework hooks 管理每个测试的状态，不在测试间复用可变对象。

## 依赖与 Test Double

- 领域服务单元测试可使用手写 fake、PHPUnit test double 或仓库已有 Mockery，隔离外部 API、时钟和队列发送端。
- 先了解真实依赖的副作用，再决定 mock 边界；不要 mock 被测流程本身依赖的事务、缓存写入或状态转换。
- 不把 `expects(...)->once()` 当作主要业务断言，除非调用次数和顺序本身就是接口契约。
- mock response 必须符合真实响应结构和错误语义，禁止只填当前代码访问的几个字段。
- Repository、PDO/ORM、Redis、序列化和事务行为使用真实依赖集成测试，读取 [integration.md](integration.md)。

## HTTP 与契约测试

- 使用框架提供的 TestCase、HTTP client 或 kernel/application bootstrap，从路由边界调用真实 middleware、校验和错误处理。
- 验证状态码、headers、JSON schema、错误结构、鉴权、幂等和分页，不只断言响应片段。
- 测试上传、流式响应或 cookie/session 时验证完整协议行为和资源清理。
- 外部 HTTP 服务使用仓库已有 fake server、MockHandler 或等价边界替身；不要 mock 自己的 controller 后称为 API 测试。
- gRPC 使用生成 client 调用测试 server，并验证 status/details/metadata，不直接调用 service method 替代传输测试。

## 状态隔离

- 每个测试重置 service container、event listeners、facades、静态缓存和全局单例，遵循框架测试基类。
- 修改环境变量、locale、timezone、ini 设置或 error handler 后必须恢复。
- 数据库 transaction rollback 不能清理由其他连接、异步任务或自动提交 DDL 写入的数据；这些场景使用独立 schema/database 或显式清理。
- Swoole/Hyperf 等长驻进程中注意 coroutine context、连接池和静态属性跨请求泄漏，测试至少连续执行两个逻辑请求。
- 并行运行只用于文件、端口、数据库和全局状态已隔离的测试；仓库已有 ParaTest/Pest parallel 配置时复用。

## 命令与证据

优先使用 Composer 定义的命令：

```bash
composer test
```

需要缩小范围时，根据仓库框架使用：

```bash
vendor/bin/phpunit --filter 'EmailNormalizerTest'
vendor/bin/phpunit --testsuite unit
vendor/bin/pest --filter 'normalizes valid email'
```

先运行单个测试或文件，再运行所属 testsuite，最后按成本运行完整 Composer/CI 命令。记录 PHPUnit/Pest 版本、退出状态、失败数、跳过项和 warning/deprecation。

仓库已配置 Xdebug 或 PCOV 时可生成覆盖率：

```bash
vendor/bin/phpunit --coverage-text
```

不要只为覆盖率安装扩展，也不要忽略 risky、incomplete、warning 或 deprecation 后声称输出干净。

## 评审清单

- 测试是否验证可观察行为而不是 framework/mock 配置？
- PHPUnit/Pest 用法是否匹配仓库版本？
- data provider/dataset 是否覆盖真实边界且 case 名称可读？
- 异常、权限、事务和副作用是否有明确断言？
- 静态状态、容器、环境和数据库是否在测试间隔离？
- 长驻进程或并行执行是否可能泄漏上下文？
- 实际运行记录是否包含最小用例、testsuite 和未运行项？
