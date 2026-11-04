# PHP 验证与发布边界

## 解释器与差异

按 Composer、CI 和部署确认目标 PHP/扩展，记录实际 `php -v`，必要时查 ini/模块，对修改 PHP 逐个 `php -l`。PHP 5.6/7.3 不能复制新版本语法；目标解释器缺失就标未验证，新版通过不代替旧版兼容。

检查暂存与未暂存 diff 的 whitespace；文件原本为 CRLF 且报错来自行尾时，用命令级设置复核，不改全局配置或批量换行：

```bash
git -c core.whitespace=cr-at-eol diff --check
git -c core.whitespace=cr-at-eol diff --cached --check
```

## 注释 / 格式

同一解释器 `token_get_all()` 仅忽略 whitespace/comment/doccomment，其余 token 类型和值严格比较，使用 [比较脚本](../scripts/compare_php_tokens.php)：

```bash
php /path/to/reviewing-php-release-readiness/scripts/compare_php_tokens.php /tmp/before.php /tmp/after.php
```

脚本只读、不加载目标代码；退出码 0 一致、1 不同、2 读取/参数错误，仍须 lint。快照按实际范围选择：

| 范围 | before | after |
|---|---|---|
| 全部未提交 | `HEAD:path` | 工作树 |
| 仅暂存 | `HEAD:path` | `:path` |
| 仅未暂存 | `:path` | 工作树 |
| 提交/分支 | 确认基线 | 目标版本 |

将快照保存到正确引用路径的临时文件，新增/删除/重命名单列。字符串、HTML、heredoc 空白仍有语义；docblock 反射/注解、`__LINE__`、源码哈希及行号依赖另查。token 相同是结构证据，不能独自证明完整行为；不同也不自动是缺陷，不能扩大忽略规则制造通过。

## 业务与部署

支付/奖励追全部实际阶段，检查失败残留、幂等与锁释放；归属/map/season 改动核对 key、配置、额度、RPC 和接收方。

Composer 声明、lock、安装实现分别确认，兄弟源码 HEAD 不证明已安装；只读审查不运行修改 lock/autoload 的命令，手改依赖目录不作发布方案。

worker、守护/定时进程、OPcache、静态/Yac 按实际生命周期列重启、回收或失效条件，不默认同一种操作。交付区分源码/本地检查、真实测试环境和生产配置/流量/外部服务/部署，结论不越过证据。
