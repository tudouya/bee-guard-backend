# Bee Guard API Response Specification — v1.0

本规范用于统一团队在各项目中的 API 响应格式与行为，强调简单、可演进、与 HTTP 语义一致，并兼容移动端/小程序/网页等多种客户端。适用于面向外部或内部的 REST 风格接口。

## 目标与原则
- 一致性：所有接口统一成功/失败外层结构，分页结构一致。
- 语义化：充分利用 HTTP 状态码，错误体可机器处理（稳定错误码）。
- 可观测：每个响应附带 `requestId` 与 `timestamp`，便于排障追踪。
- 可演进：在不破坏客户端的前提下可增添字段；保留向后兼容策略。

## 通用字段
- `requestId`：字符串。一次请求的唯一标识，建议从请求头 `X-Request-Id` 透传，若无则由服务端生成；同时回写到响应头。
- `timestamp`：字符串。`YYYY-MM-DD HH:MM:SS`，按 `Asia/Shanghai`（UTC+08:00）输出，例如 `2025-09-17 12:34:56`。
- `code`：成功固定为 `0`；错误为稳定的业务错误码（小写蛇形或短横线，推荐蛇形：`validation_failed`）。
- `message`：面向用户/前端可展示的简短文案（中文优先）。

## 成功响应
统一采用轻量包裹，便于前端处理与监控联动。

### 非分页
```
HTTP/1.1 200 OK
Content-Type: application/json
X-Request-Id: <uuid>

{
  "code": 0,
  "message": "ok",
  "data": { /* 任意对象 */ },
  "requestId": "<uuid>",
  "timestamp": "2025-09-17 12:34:56"
}
```

### 分页（列表）
```
HTTP/1.1 200 OK
{
  "code": 0,
  "message": "ok",
  "data": [ /* 项列表 */ ],
  "meta": {
    "page": 1,
    "per_page": 20,
    "total": 135,
    "has_more": true
  },
  "links": {
    "next": "/api/resources?page=2&per_page=20",
    "prev": null
  },
  "requestId": "<uuid>",
  "timestamp": "2025-09-17 12:34:56"
}
```

说明：
- `meta` 必含 `page`、`per_page`、`total`、`has_more`。
- `links` 可选，提供 `next/prev/self` 等 URL 以便客户端导航。
- 入参统一使用 `page`/`per_page`。

### 状态码与语义
- 200 OK：读取/计算成功。
- 201 Created：创建成功，建议返回资源 `data` 或至少 `id`。
- 204 No Content：删除或无需返回体的成功操作。

## 错误响应（Problem Details 风格，v1 精简版）
所有非 2xx 一律返回统一错误体；结合 HTTP 状态码与 `code` 进行机器处理。

```
HTTP/1.1 422 Unprocessable Entity
Content-Type: application/json
X-Request-Id: <uuid>

{
  "status": 422,
  "code": "validation_failed",
  "message": "参数校验失败",
  "errors": {
    "phone": ["手机号格式不合法"],
    "amount": ["金额必须为正数"]
  },
  "requestId": "<uuid>",
  "timestamp": "2025-09-17 12:34:56"
}
```

字段定义：
- `status`：HTTP 状态码（数值）。
- `code`：稳定业务错误码（蛇形，详见错误码清单）。
- `message`：简短说明，适合展示。
- `errors`：对象（可选），用于字段级错误（422 时强烈建议提供）。
- `requestId`、`timestamp`：同上。
- 可选扩展字段（保留位）：`type`（错误文档 URL）、`instance`（错误发生的资源/上下文标识）。

### 推荐状态码映射
- 400 Bad Request：通用参数错误（非字段级）。
- 401 Unauthorized：未认证/Token 失效。
- 403 Forbidden：无访问权限。
- 404 Not Found：资源不存在。
- 409 Conflict：并发/状态冲突（例如重复使用、版本冲突）。
- 410 Gone：端点/资源废弃。
- 415 Unsupported Media Type：上传类型不支持。
- 422 Unprocessable Entity：字段校验失败。
- 429 Too Many Requests：限流触发。
- 500 Internal Server Error：服务器内部错误。
- 503 Service Unavailable / 504 Gateway Timeout：下游不可用或超时。

## 命名与序列化约定
- 命名风格：
  - 默认推荐 snake_case（示例：`per_page`、`created_at`）。
  - 如项目强约定 camelCase（前端偏好），应全局一致并由序列化层适配。
- 时间格式与时区：
  - 日期时间：`YYYY-MM-DD HH:MM:SS`（24 小时制，秒级精度）。
  - 日期：`YYYY-MM-DD`。
  - 时区：统一按 `Asia/Shanghai`（UTC+08:00）输出，不附加时区后缀（见下方“时间模式说明”）。
  - 空值：无值字段返回 `null`，不返回空字符串。
  - 存储建议：后端可内部使用 UTC 存储，输出时转换为 `Asia/Shanghai`。

### 时间模式说明（取舍）
- 默认模式（CN 模式）：为简化国内客户端展示，所有时间以 `Asia/Shanghai` 输出，格式 `YYYY-MM-DD HH:MM:SS`，不附带时区后缀。
- 可选扩展（ISO 模式，供外部集成）：当对外平台或跨时区对接需要严格时区语义时，推荐返回 ISO 8601 带时区的时间戳，例如：
  - UTC：`2025-09-17T12:34:56Z`
  - 东八区：`2025-09-17T20:34:56+08:00`
- 启用方式（建议）：通过网关/配置或协商头（例如 `X-Time-Format: iso`）/查询参数开启；双方约定后统一处理。当前规范默认使用 CN 模式，ISO 模式为可选增强。
- 金额：使用字符串形式的十进制（示例：`"12.34"`）并显式返回 `currency`（例如 `"CNY"`）。
- 大整数 ID：以字符串返回，避免 JS 精度问题。
- 布尔与空值：使用 JSON 原生语义（`true/false/null`）。

### 空值与类型约定（强制）
- 字段稳定：对外契约中的字段尽量固定存在；无值时返回 `null`，不要返回空字符串 `""`。
- 字符串：未知/缺失用 `null`；确有“用户输入了空内容”才使用空字符串。
- 日期/时间/数字/金额/ID：未知用 `null`，不要用空字符串代替。
- 布尔：`true/false`；确需三态（是/否/未知）时未知用 `null`。
- 数组：无元素时返回 `[]`，不要返回 `null`。
- 对象：整体缺失可返回 `null`；也可返回对象且其内部允许 `null` 字段。
- 展示建议：前端统一对 `null` 做兜底（例如显示 `—` 或空字符串），避免直接字符串化 `null`。


## 头部与追踪
- 输入：
  - `X-Request-Id`（可选）：客户端提供的请求 ID，服务端透传。
- 输出：
  - `X-Request-Id`：始终返回。
  - `X-Response-Spec`: `v1`（可选，用于灰度与兼容期标示）。

### 关于 `code: 0` 的取舍
- 从 REST 语义看，HTTP 2xx 已表示成功；`code: 0` 有一定冗余。
- 考虑到移动端/小程序常用统一拦截器，保留 `code: 0` 便于前端快速判断，简化接入。团队共识：在 v1 中保留 `code: 0`。

### 关于分页 `links` 的 URL 形态
- 默认返回相对路径（示例：`/api/resources?page=2&per_page=20`）。
- 若部署在反向代理/多域场景需要绝对 URL，可通过网关或服务端配置生成完整地址（基于 `APP_URL` 等），或由 API 网关拼装。

## 版本与兼容
- 本规范版本：`v1.0`；仅定义“响应格式”版本，不等同于“业务 API 版本”。
- 兼容策略：
  - 新增字段：允许（默认向后兼容）。
  - 字段重命名/移除：需提供兼容期（同时返回新旧字段），并在文档与公告中声明下线时间。

## 示例

### 成功（创建）
```
HTTP/1.1 201 Created
{
  "code": 0,
  "message": "ok",
  "data": {
    "id": "123456789012345678",
    "name": "example"
  },
  "requestId": "<uuid>",
  "timestamp": "2025-09-17 12:34:56"
}
```

### 错误（未认证）
```
HTTP/1.1 401 Unauthorized
{
  "status": 401,
  "code": "unauthorized",
  "message": "登录状态已过期，请重新登录",
  "requestId": "<uuid>",
  "timestamp": "2025-09-17 12:34:56"
}
```

### 错误（并发冲突）
```
HTTP/1.1 409 Conflict
{
  "status": 409,
  "code": "operation_conflict",
  "message": "资源状态已改变，请刷新后重试",
  "requestId": "<uuid>",
  "timestamp": "2025-09-17 12:34:56"
}
```

---

附：本规范与 RFC 7807 的关系：
- 采用其核心思想（以 HTTP 状态 + 结构化错误体表达错误），字段命名精简为团队通用格式；若未来需要，可直接扩展 `type/instance` 等字段以无缝对齐。
