# 检测流程联调审计与对齐清单（2025-09-19）

本文档汇总当前小程序端“开始检测 → 问卷 → 邮寄”三页流程与后端实现的映射、差异与整改建议，作为后续按项整改的依据。

## 一、流程与实现映射

- 开始检测（pages/detection/index）
  - 使用 API：`POST /api/detection-codes/verify-bind`（校验 + 原子绑定）
    - 路由：routes/api.php:60
    - 控制器：app/Http/Controllers/Api/DetectionCodesController.php:103
    - 入参：`{ detection_number, phone }`
  - 数据表：`detection_codes`（database/migrations/2025_09_12_095000_create_detection_codes_table.php）

- 问卷调查（pages/detection/survey/index）
  - API：`POST /api/surveys`
    - 路由：routes/api.php:63
    - 控制器：app/Http/Controllers/Api/SurveysController.php:18
    - 入参：`{ detection_code_id, fill_date, fill_time, owner_name, phone, ... }`
  - 事务与并发：锁定 `detection_codes` 行（assigned→used）app/Http/Controllers/Api/SurveysController.php:94:100, 238:242
  - 数据表：
    - `surveys`（database/migrations/2025_09_13_162606_create_surveys_table.php）
    - `detection_codes`（状态从 assigned → used）
    - `detections`（问卷提交后自动创建一条记录，绑定 user 与 detection_code，状态 `pending`，submitted_at=now）

- 邮寄提交（pages/detection/shipping/index）
  - API：`POST /api/shipping-notifications`
    - 路由：routes/api.php:66
    - 控制器：app/Http/Controllers/Api/ShippingNotificationsController.php:15
    - 验证器：app/Http/Requests/Api/Shipping/ShippingNotificationStoreRequest.php:1
    - 入参：`{ detection_number, courier_company, tracking_no, shipped_at?, phone }`
  - 事务与幂等：同检测码+单号唯一，返回 409 app/Http/Controllers/Api/ShippingNotificationsController.php:42:74
  - 数据表：
    - `shipping_notifications`（database/migrations/2025_09_15_000000_create_shipping_notifications_table.php）
    - `detection_codes`（归属校验）

- 结果查询（供链路参考）
  - 列表：`GET /api/detections` routes/api.php:69 → app/Http/Controllers/Api/DetectionsController.php:1
  - 明细：`GET /api/detections/{id}` routes/api.php:70 → 同上
  - 数据表：`detections`（database/migrations/2025_09_15_130000_create_detections_table.php, 2025_09_15_141000_alter_detections_add_wide_columns.php）

## 二、发现的问题与对齐清单

- 接口选择与参数命名
  - 开始检测应使用 `verify-bind` 完成“校验+绑定”，避免可用码未绑定导致的失败。
  - 参数名对齐：`detectionNumber`（前端）→ `detection_number`（后端）；当前前端调用 `verify` 使用的是 `detection_code`（需调整）。
- 前端已切换：services/detectionNumbers.js 使用 `/api/detection-codes/verify-bind` 并进行输入标准化。

- 检测号格式规范
  - 邮寄接口对 `detection_number` 做“去短横线 + 转大写”的标准化（app/Http/Controllers/Api/ShippingNotificationsController.php:20:26）。
  - 已对 `verify-bind` 加入同样标准化：app/Http/Controllers/Api/DetectionCodesController.php:1（方法 verifyBind）采用 `UPPER(CONCAT(prefix, code))` 比较与去短横线的输入匹配。
  - 前端仍在提交前做标准化，双重容错。

- 问卷字段与取值一致性
  - 特殊值需精确匹配：“没有或已是当年最后一个生产期”（用于分支）app/Http/Controllers/Api/SurveysController.php:130
  - 枚举值需与后端完全一致（‘是/否’、‘蜂蜜/花粉/蜂王浆/其他’、‘定地/省内小转地/跨省大转地’、‘中华蜜蜂/西方蜜蜂（意大利蜜蜂等）’等）。
  - 空值传递建议：尽量“不传或 null”，避免空字符串在 Laravel 中被转换导致歧义。

- 邮寄表单字段与校验
  - 邮寄上报已支持 `phone` 字段（必填），用于快递联系人号码，可能与问卷/资料不一致。
  - 快递公司需在白名单内，前端公司列表与配置一致（config/shipping.php:1）。

- 跳转与参数透传
  - 当前链路：开始检测 → 问卷（带 `codeId`/`detectId`）→ 指南页（展示地址）→ 邮寄页（用户可能手填）。
  - 建议：在完成问卷后直接跳邮寄页，并透传 `detectId`，减少手输错误；或在指南页提供“去提交邮寄信息”并带参跳转。

- 无效/缺失接口引用清理
  - services/detectionNumbers.js 中的 `/api/detection-numbers/use` 与 `/api/detection-numbers/{id}` 后端不存在，建议移除或改造为现有接口。

## 三、weapp 实际调用对照

- 开始检测（pages/detection/index.js:1, services/detectionNumbers.js:8）
  - 已切换：`POST /api/detection-codes/verify-bind`，参数 `{ detection_number, phone }`，并在提交前标准化检测号。

- 问卷提交（pages/detection/survey/index.js:520）
  - 现状：`POST /api/surveys`，字段与后端规则一致；提交成功后跳转指南页。
  - 后端行为更新：提交成功即创建 `detections` 记录（绑定用户与检测码），后台可直接在该记录上补录样品编号与结果。
  - 建议：提交成功后直接跳邮寄页并透传 `detectId`；或在指南页加跳转按钮并携带参数。

- 邮寄提交（pages/detection/shipping/index.js:1, services/shipping.js:1）
  - 现状：`POST /api/shipping-notifications`，payload `{ detection_number, courier_company, tracking_no, shipped_at? }`，错误码提示 403/404/409/422 已覆盖。
  - 建议：从前序页透传 `detectId`，减少用户手填；可移除未使用的 `phone` 字段校验。

## 四、整改建议（按优先级）

1) 接口切换与参数标准化（高）
   - 前端：开始检测切换至 `/api/detection-codes/verify-bind`，参数名改为 `detection_number`；输入标准化（去 `-`、转大写）。
   - 后端（可选）：在 `verify/verifyBind` 加入与邮寄相同的标准化，提升容错。

2) 链路透传与跳转优化（中）
   - 问卷完成后跳转邮寄页并透传 `detectId`；或指南页提供带参跳转按钮。

3) 字段与枚举统一（中）
   - 校验问卷枚举值与特殊字符串的精确一致；空值尽量不传或传 null。

4) 清理与简化（低）
   - 移除无后端支持的 `/api/detection-numbers/*` 方法。（已完成，2025-09-19）
   - 邮寄页移除未上送的 `phone` 字段校验或标注为仅前端提示。

5) Helper 抽取（记录，不立即实施）
   - 目标：抽取“检测号标准化”共用方法，统一供 verify-bind 与 shipping-notifications 调用。
   - 规范与签名：见 `docs/specs/detection-code-normalization.md`。

## 五、引用位置（便于快速定位）

- 路由
  - routes/api.php:60
  - routes/api.php:63
  - routes/api.php:66
  - routes/api.php:69
  - routes/api.php:70

- 控制器与验证
  - app/Http/Controllers/Api/DetectionCodesController.php:103
  - app/Http/Controllers/Api/SurveysController.php:18
  - app/Http/Controllers/Api/SurveysController.php:94
  - app/Http/Controllers/Api/SurveysController.php:130
  - app/Http/Controllers/Api/ShippingNotificationsController.php:15
  - app/Http/Controllers/Api/ShippingNotificationsController.php:20
  - app/Http/Requests/Api/Shipping/ShippingNotificationStoreRequest.php:1

- 数据表迁移
  - database/migrations/2025_09_12_095000_create_detection_codes_table.php
  - database/migrations/2025_09_13_162606_create_surveys_table.php
  - database/migrations/2025_09_15_000000_create_shipping_notifications_table.php
  - database/migrations/2025_09_15_130000_create_detections_table.php
  - database/migrations/2025_09_15_141000_alter_detections_add_wide_columns.php

- weapp 关键文件
  - pages/detection/index.js:1
  - pages/detection/survey/index.js:520
  - pages/detection/guide/index.js:1
  - pages/detection/shipping/index.js:1
  - services/detectionNumbers.js:8
  - services/shipping.js:1
  - utils/api.js:1

---

后续将按“整改建议”分步骤提交改动（先前端接口切换与标准化，其次链路透传，再做字段/清理优化）。
