# API 时间格式统一改造清单（对齐 v1.0）

目标：将所有 API 响应中的日期时间字段统一为 `YYYY-MM-DD HH:MM:SS`（秒级），日期字段统一为 `YYYY-MM-DD`，时区以 Asia/Shanghai 输出，不附加时区后缀。

本清单仅覆盖对外 API（不含仅限后台面板 UI 的显示性格式）。

## 需调整项（分钟精度 → 秒级）

- 文件：`app/Http/Controllers/Api/DetectionsController.php:174`
  - 位置：私有方法 `fmt($value)` 当前为 `return $value->format('Y-m-d H:i');`
  - 影响字段：
    - 列表 `index()`：`sampleTime`、`submitTime`、`reportedAt`
    - 明细 `show()`：`sampleTime`、`submitTime`、`testedAt`、`reportedAt`
  - 调整建议：改为 `return $value->format('Y-m-d H:i:s');`

## 已符合规范（秒级或日期）

- 文件：`app/Http/Controllers/Api/DetectionCodesController.php:30-31`
  - 字段：`assigned_at`、`used_at` → `Y-m-d H:i:s`（秒级），无需调整。
- 文件：`app/Http/Controllers/Api/SurveysController.php:250`
  - 字段：`submitted_at` → `Y-m-d H:i:s`（秒级），无需调整。
- 文件：`app/Http/Controllers/Api/OrdersController.php:50`
  - 字段：`paid_at` → `toDateTimeString()`（等同 `Y-m-d H:i:s`），无需调整。
- 文件：`app/Http/Controllers/Api/ShippingNotificationsController.php:86-87`
  - 字段：`shippedAt` → `Y-m-d`（日期字段，保持不变）；`createdAt` → `toDateTimeString()`（秒级）。
- 文件：`app/Http/Controllers/Api/Knowledge/ArticleController.php:37,49`、`.../DiseaseController.php:101`
  - 字段：`date` → `Y-m-d`（展示日期），保持不变。

## 说明
- 本清单仅跟踪 API 控制器的输出格式；Filament Admin/Enterprise 面板内的 `format('Y-m-d H:i')` 属于后台 UI 展示，不在本次统一范围内。
- 若未来引入统一序列化/资源层，可在一处集中处理时间格式。
- 建议新增集成测试覆盖 `DetectionsController` 的时间字段，确保改造后为秒级格式。

