# 验证方法与发布边界

## 语法与运行版本

读取 `composer.json` 的 PHP/扩展约束、CI 与部署说明，再记录实际解释器的 `php -v`、必要的 `php --ini` / `php -m`。对目标 PHP 文件逐个执行 `php -l`。更高版本 lint 通过不证明低版本兼容；目标解释器缺失时明确未运行目标版本验证。

PHP 5.6、7.3 的项目不能复制现代示例中的 attributes、typed properties、arrow functions、union types 或 `match`。是否使用 nullable/return type、`iterable` 等同样按具体 PHP 版本确认。无需为注释改动加载 Web 入口或业务 bootstrap。

先运行 `git diff --check` 与 `git diff --cached --check`。确认目标文件本来就是 CRLF、报错来自行尾后，用单次命令复核：

```bash
git -c core.whitespace=cr-at-eol diff --check
git -c core.whitespace=cr-at-eol diff --cached --check
```

这是当前命令的兼容设置，无需改 Git 配置或批量转换换行。真实尾随空格等问题仍需处理。

## 注释与格式的 token 比较

读取原文件与目标快照，使用同一个解释器调用 `token_get_all()`，仅去除 `T_WHITESPACE`、`T_COMMENT`、`T_DOC_COMMENT`，严格比较其余 token 类型和值。使用 [比较脚本](../scripts/compare_php_tokens.php)：

```bash
php /path/to/reviewing-php-release-readiness/scripts/compare_php_tokens.php /tmp/before.php /tmp/after.php
```

参数替换为实际路径。脚本只读取两个文件并做词法分析，不 include 目标文件、不加载 autoload。退出码 `0` 为非注释 token 一致，`1` 为不同，`2` 为参数或读取错误。它不检查语法，仍须 lint。

根据实际发布范围选择快照：

| 比较目标 | 原版 | 新版 |
|---|---|---|
| 全部未提交修改 | `git show HEAD:path` | 当前文件 |
| 仅暂存 | `git show HEAD:path` | `git show :path` |
| 仅未暂存 | `git show :path` | 当前文件 |
| 提交或分支 | 已确认基线中的文件 | 目标提交/分支中的文件 |

将内容保存到临时目录，再调用脚本；路径正确引用，文件名含空格时同样保留边界。新增、删除、重命名文件单列检查，不能假装都有同名旧文件。

解释证据时保留以下限制：

- HTML、字符串、heredoc 的内容会保留；文件行尾变化可能影响实际输出，不能整体删除空白后比较。
- docblock 被反射、annotation parser 或生成器使用时属于行为输入；相关注释即使被比较器忽略，仍需单独核对。
- `__LINE__`、源码读取/哈希、错误堆栈和依赖具体行号的逻辑可能受插入注释影响。
- token 不同不自动代表缺陷；数组语法等预期格式转换需要逐项检查，不能自动扩大忽略规则使结果变绿。

token 相同是保持代码结构的强证据，不能独立证明所有运行行为或外部依赖等价。

## 业务、依赖与部署

对支付/奖励至少核对资格 → 订单/记录 → 扣款 → 入队 → 消费 → 发奖/到账各个实际存在的阶段。列出失败后已提交的状态、重试幂等和锁释放。更改归属、地图或赛季时逐项检查 key、额度、配置、RPC 和奖励接收方。

声明、lock、安装目录分开核对。依赖方法存在于兄弟源码仓库，不代表当前服务的版本包含它；本地手改依赖目录不能作为发布方案。只审查时不运行会改 lock、autoload 或源码的 Composer 操作。

对常驻 PHP worker、守护脚本、消费者、定时进程、OPcache 和本地静态/Yac 缓存，按实际加载与更新机制列出重启、回收或失效条件。不要默认每种进程都需要同一种操作，也不将“需重启”写成“已重启”。

建议交付三层证据：源码与本地检查已确认什么；真实测试环境验证了什么；生产配置、流量、外部服务与部署仍缺什么。结论不跨越已获得的证据。
