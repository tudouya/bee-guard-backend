# Standard Error Codes — v1.0

本清单为跨项目的标准错误码集合，配合《API Response Specification v1.0》使用。错误码须稳定、可枚举、可检索；客户端应基于 `http status + code` 进行分支处理，而不是仅依赖 `message`。

## 命名约定
- 形式：小写蛇形（snake_case）。
- 语义：面向“问题类别”，非具体实现细节；相同问题在不同接口复用同一错误码。
- 唯一性：全局唯一，不与业务域名冲突（必要时使用前缀）。

## 通用类（跨领域）
- `bad_request`（400）：请求格式/语义错误（非字段级）。
- `unauthorized`（401）：未认证/令牌无效或过期。
- `forbidden`（403）：已认证但无权限。
- `not_found`（404）：资源不存在或不可见。
- `method_not_allowed`（405）：方法不被允许。
- `conflict`（409）：资源冲突（版本/重复状态）。
- `operation_conflict`（409）：操作冲突（并发/状态机条件不满足）。
- `gone`（410）：端点/资源已废弃。
- `precondition_failed`（412）：前置条件失败（例如 If-Match/版本校验未通过）。
- `payload_too_large`（413）：请求体过大。
- `unsupported_media_type`（415）：媒体类型不支持。
- `validation_failed`（422）：字段级校验失败（附 `errors` 明细）。
- `rate_limited`（429）：达到速率限制。
- `internal_error`（500）：服务器内部错误（通用兜底）。
- `service_unavailable`（503）：依赖服务不可用（维护/熔断）。
- `gateway_timeout`（504）：网关/下游超时。

## 鉴权与账户类
- `login_failed`（401）：登录失败（凭证无效/第三方校验失败）。
- `token_invalid`（401）：令牌无效（格式错误/伪造）。
- `token_expired`（401）：令牌过期。
- `mfa_required`（401/403）：需要二次验证。

## 资源与约束类
- `duplicate_resource`（409）：唯一性冲突（已存在/重复创建）。
- `invalid_state`（409/422）：资源状态不满足操作条件。
- `dependency_failed`（424/409）：依赖资源失败或不可用（可根据场景选 409/424）。
- `quota_exceeded`（429/403）：超配额/配额不足。

## 上传与文件类
- `file_type_not_allowed`（415）：文件类型不允许。
- `file_too_large`（413）：文件过大。
- `file_integrity_error`（422）：文件完整性/校验失败。

## 风控与安全类
- `suspicious_activity`（403）：可疑行为拦截。
- `verification_required`（403）：需要额外校验（风控触发）。

## 建议用法
- 统一在异常处理中将错误映射为 `{ status, code, message, errors?, requestId, timestamp }`。
- 对于字段级错误（422），务必提供 `errors` 对象，键为字段名，值为错误消息数组。
- 同一问题类别在不同接口重用同一 `code`，客户端通过 `code` 做用户态提示与引导。
- 保留代码与错误码的映射表，禁止在运行中动态拼接错误码。

## 扩展与域内错误码
- 允许在业务域内定义扩展错误码，但遵循命名规范并优先使用通用码：
  - 示例：检测号绑定冲突 → 统一使用 `operation_conflict`（409）；如需更细粒度，可扩展 `detection_code_conflict`（409），但需在文档中登记。
  - 示例：订单重复支付 → 使用 `duplicate_resource` 或扩展 `duplicate_payment`（409）。

## 版本与治理
- 版本：`v1.0`。
- 变更流程：新增需评审；修改/废弃必须提供兼容期与公告，并在清单中标注弃用状态（deprecated）。

