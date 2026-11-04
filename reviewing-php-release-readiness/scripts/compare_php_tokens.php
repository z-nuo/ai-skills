<?php
/**
 * 只读比较两个 PHP 文件的非注释 token；兼容 PHP 5.6+。
 * 退出码：0 一致，1 不同，2 参数或读取失败。语法正确性另用 php -l 验证。
 */
if ($argc !== 3) {
    fwrite(STDERR, "用法：php compare_php_tokens.php <原文件> <目标文件>\n");
    exit(2);
}

$streams = array();
foreach (array($argv[1], $argv[2]) as $path) {
    if (!is_file($path) || !is_readable($path)) {
        fwrite(STDERR, "文件不存在或不可读：" . $path . "\n");
        exit(2);
    }
    $source = file_get_contents($path);
    if ($source === false) {
        fwrite(STDERR, "读取失败：" . $path . "\n");
        exit(2);
    }
    $stream = array();
    foreach (token_get_all($source) as $token) {
        if (is_array($token)) {
            if (in_array($token[0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true)) {
                continue;
            }
            // 忽略行号元数据，保留 token 类型与完整内容。
            $stream[] = array($token[0], $token[1]);
        } else {
            $stream[] = $token;
        }
    }
    $streams[] = $stream;
}

if ($streams[0] === $streams[1]) {
    fwrite(STDOUT, "NON_COMMENT_TOKENS_IDENTICAL\n");
    exit(0);
}
// 不打印 token 内容，避免将源码中的密钥或载荷写入检查输出。
fwrite(STDOUT, "NON_COMMENT_TOKENS_DIFFERENT\n");
exit(1);
