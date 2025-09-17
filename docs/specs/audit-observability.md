# 审计与可观测性（Audit & Observability）规划说明

本文档说明当前系统在“审计与可观测性”方面的不足，并提出渐进式落地方案（仅文档，不立即改代码）。

## 背景与现状
- 已有：社区奖励发放（RewardIssuance）具备一定审计轨迹（`addAuditEntry()` 等）。
- 缺失：以下关键状态变更未统一写入审计或事件：
  - 检测码分配（available → assigned），包括：
    - 用户自行绑定（verify-bind）
    - 后台审核支付凭证后分配（自费）
  - 检测码置已用（assigned → used）
  - 订单支付完成（人工审核通过，标记 paid）
  - 邮寄提交（shipping notification 创建）
- 影响：排障追踪困难、风控/合规不足、跨模块数据难以关联（缺少统一 requestId、主体/客体、动作与上下文）。

## 目标
- 为所有关键业务动作形成一致的“可追溯记录”（Who/When/What/Which/Result/Why/Context）。
- 统一日志/审计结构，便于检索、聚合与对账；可逐步过渡到表级审计。
- 为后续风控、数据分析与合规（留痕/保留期/脱敏）提供基础设施。

## 审计模型（推荐最小字段）
- 基本字段：
  - `action`（字符串）：动作名（命名示例见“事件命名”）。
  - `subject_type` / `subject_id`：被操作对象（如 DetectionCode/Order/ShippingNotification）。
  - `actor_type` / `actor_id` / `actor_role`：操作者（User/Admin/System）；若系统自动则标注 `system`。
  - `status`：`success|failure`。
  - `message`：简短说明（可为空）。
  - `metadata`（JSON）：上下文（如 `order_id`、`payment_proof_id`、`full_code`、`previous_status`、`ip`、`user_agent` 等）。
  - `request_id`：与 HTTP 日志统一关联。
  - `occurred_at`：时间戳。
- 可选扩展：`tenant_id`（适用于多租场景）、`correlation_id`（跨服务）。

## 事件命名（建议）
- 检测码相关：
  - `detection_code.assign_by_user`（用户 verify-bind）
  - `detection_code.assign_by_review`（后台审核分配）
  - `detection_code.mark_used`（问卷提交后置已用）
  - `detection_code.mark_available` / `detection_code.mark_expired`（后台批量操作）
- 订单与支付：
  - `order.create`
  - `payment_proof.upload`
  - `payment_proof.approve`
  - `payment_proof.reject`
  - `order.mark_paid`
- 邮寄：
  - `shipping_notification.create`
  - `shipping_notification.duplicate`

## 落地方案（分阶段）
- Phase 1：结构化日志（最小成本）
  - 新建 `audit` 日志通道（JSON Lines），在关键控制器/面板动作处写入统一结构日志。
  - 引入 `requestId` 中间件：无 `X-Request-Id` 时生成 UUID，并注入日志上下文与响应体。
  - 优点：不改表结构，便于快速全覆盖；缺点：检索依赖日志系统，跨维度查询相对不便。

- Phase 2：表级审计（更强检索能力）
  - 新增 `audits` 表（字段参考“审计模型”），提供 `AuditLogger` 服务写入记录。
  - 关键动作统一调用 `AuditLogger`；可选择“同步写入”或“异步队列写入”。
  - 为常用维度建索引：(`subject_type`,`subject_id`)、(`actor_type`,`actor_id`)、`request_id`、`occurred_at`。

- Phase 3：观察与指标（可选）
  - 指标（Metrics）：按动作统计计数/耗时（如 `detection_code_assign_total`、`assign_duration_seconds`）。
  - 链路追踪（Tracing）：与 `requestId`/traceId 对接（如 OpenTelemetry），便于端到端分析。

## 建议的集成点（不改代码，作为实施指南）
- 控制器/面板动作 → 统一调用 `AuditLogger`（或日志通道）：
  - DetectionCodesController::verifyBind → `detection_code.assign_by_user`
  - SurveysController::store（成功）→ `detection_code.mark_used`
  - PaymentProofResource 审核通过/拒绝 → `payment_proof.approve|reject`、`order.mark_paid`（当审核通过）
  - ShippingNotificationsController::store → `shipping_notification.create` / `shipping_notification.duplicate`（409 时也可记失败）
- 领域服务（若后续引入 DetectionCodeService）：在服务内部统一记录审计，控制器仅负责调用。

## 错误与失败记录
- 所有失败也应记录（`status=failure`），包括：
  - 并发冲突（409）、权限不足（403）、未找到（404）、校验失败（422）。
  - `metadata` 包含 `error_code`、`http_status`、`validation_errors?` 等，遵循响应规范。

## 数据安全与合规
- 脱敏处理：
  - `phone` 仅在必要时记录，或仅记录尾号；`session/token` 不写入。
- 保留期：
  - 依据合规要求设定（如 180 天/1 年），超期归档或清理。
- 访问控制：
  - 审计日志/表仅限管理员与合规角色访问；普通用户不可见。

## 示例（结构化日志）
```json
{
  "action": "detection_code.assign_by_user",
  "subject_type": "DetectionCode",
  "subject_id": 123,
  "actor_type": "User",
  "actor_id": 456,
  "actor_role": "farmer",
  "status": "success",
  "message": "verify-bind success",
  "metadata": {
    "full_code": "ZFNF4TTC6YZU",
    "previous_status": "available"
  },
  "request_id": "8a2d1f1d-...",
  "occurred_at": "2025-09-17 12:34:56"
}
```

## 示例（audits 表结构草案）
- 字段：
  - `id` BIGINT PK
  - `action` VARCHAR(64) NOT NULL
  - `subject_type` VARCHAR(64) NOT NULL
  - `subject_id` BIGINT NOT NULL
  - `actor_type` VARCHAR(32) NOT NULL
  - `actor_id` BIGINT NULL
  - `actor_role` VARCHAR(32) NULL
  - `status` ENUM('success','failure') NOT NULL
  - `message` VARCHAR(255) NULL
  - `metadata` JSON NULL
  - `request_id` CHAR(36) NULL
  - `ip` VARCHAR(45) NULL
  - `user_agent` VARCHAR(255) NULL
  - `occurred_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
- 索引：
  - KEY (`subject_type`,`subject_id`)
  - KEY (`actor_type`,`actor_id`)
  - KEY (`request_id`)
  - KEY (`occurred_at`)

## 推进步骤与验收
- 先在高优先动作接入（verify-bind、问卷置 used、凭证审核、邮寄创建）。
- 验收：
  - 可在日志/表中按 `request_id` 把一次前端操作串起来。
  - 可在审计列表中按对象反查历史（某个检测码/订单的动作轨迹）。

