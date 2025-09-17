# API 资源形状说明（检测码为锚点）

本项目接口以“检测码（detection_code）”为主线组织流程，原因：用户拿到检测码后才能进入流程，在问卷阶段实验室尚未创建 `detections` 主记录，因此无法稳定提供 `detection.id`。

## 流程与资源
- 绑定检测码：`POST /api/detection-codes/verify-bind`
  - 入参：`{ detection_number, phone }`
  - 行为：原子化将码从 available → assigned，并绑定到当前用户。
  - 出参：`{ detection_code_id, full_code }`
- 问卷提交：`POST /api/surveys`
  - 入参：`{ detection_code_id, ...问卷字段 }`
  - 行为：写入问卷并将码置为 used（一次性使用）。
- 邮寄上报：`POST /api/shipping-notifications`
  - 入参：`{ detection_number, courier_company, tracking_no, shipped_at? }`
  - 行为：记录邮寄信息（同一检测码+运单号幂等）。
- 检测结果：
  - 列表：`GET /api/detections`（按用户）
  - 明细：`GET /api/detections/{id}`
  - 说明：`detections` 主记录一般由实验室在接收/检测环节创建，并与 `detection_code_id` 关联。

## 选择该形状的理由
- 符合真实业务顺序：先有码 → 才能问卷与邮寄。
- 减少空记录：避免为了路径需要而过早创建空的 `detections`。
- 授权与幂等清晰：围绕检测码的归属（`assigned_user_id`）和唯一键（如 `detection_code_id + tracking_no`）。

## 兼容性与演进
- 如需“检测为中心”的语义路由（例如 `/detections/{id}/shipping`），可在将来提供别名路由并在内部解析到 `detection_code_id`，不改变当前数据流与事务边界。
- 建议在文档与前端保持上述约定，降低协作心智成本。

