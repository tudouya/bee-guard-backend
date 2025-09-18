# 检测号标准化 Helper 设计（拟定）

目的
- 统一“检测号”字符串的规范化处理，避免各控制器重复实现，降低前后不一致风险。
- 适用范围：`/api/detection-codes/verify-bind`、`/api/shipping-notifications` 以及未来涉及“前缀+编码”匹配的接口。

现状
- shipping-notifications：已对 `detection_number` 执行“去短横线 + 转大写”的标准化。
- verify-bind：已加入同样标准化（去短横线 + 转大写），并使用 `UPPER(CONCAT(prefix, code))` 匹配。
- 两处存在重复实现，建议抽取共用。

规范化规则（最小一致性）
- 去除两端空白：`trim`。
- 去除短横线：将所有 `-` 移除（仅此一项，不移除其他字符）。
- 转大写：`mb_strtoupper` 或 `strtoupper`。
- 示例：
  - `zf-20240918-001` → `ZF20240918001`
  - ` ZF20240918 ` → `ZF20240918`
  - `qY123` → `QY123`

工具类与签名（建议）
- 文件：`app/Support/Detections/CodeNormalizer.php`
- 类与方法：
  - `namespace App\Support\Detections;`
  - `final class CodeNormalizer { public static function normalize(string $raw): string { /* 如上规则 */ } }`

接入方式（建议，不立即实施）
- verify-bind：
  - 替换现有局部标准化为 `CodeNormalizer::normalize($request->input('detection_number'))`。
  - 查询语句保持 `UPPER(CONCAT(prefix, code)) = ?`。
- shipping-notifications：
  - 替换现有局部标准化为同一 Helper 调用。

测试建议
- Unit：`tests/Unit/Support/CodeNormalizerTest.php`
  - 覆盖大小写、短横线、前后空白、空字符串边界。
- Feature（回归）：
  - verify-bind 能匹配大小写/带短横线的输入。
  - shipping-notifications 同样表现。

兼容性与风险
- 对外行为不变，仅内部消除重复实现，风险低。
- 暂不扩大规则（例如移除空格或其他非字母数字符号），以免影响既有输入期望；如需扩展，另行评审。

实施状态
- 当前仅记录设计与落地路径，未创建 Helper 文件、未替换控制器调用（按产品决定时机再实施）。

