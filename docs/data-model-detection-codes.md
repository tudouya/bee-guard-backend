# 数据模型 — 检测号/企业/产品/疾病/推荐（ER 与表结构）

本文档覆盖“检测号-企业-产品-疾病-推荐”范围的数据模型，用于指导迁移与接口/面板实现。暂不包含 `detections` 主表（按当前阶段要求推迟）。

## 1) ER 概览（ASCII）

- 主键以「#」标识，外键以「FK」标识；1 一对，* 多对，0..1 可空。

```
+-------------------+           1          *        +----------------------+
|      users        |------------------------------>|     enterprises      |
| # id              |                               | # id                 |
|   ...             |                               |   owner_user_id (FK) |
+-------------------+                               |   name, status, ...  |
                                                    +-----------+----------+
                                                                |
                                                                | 1
                                                                |          *
                                                    +-----------v----------+
                                                    |   detection_codes    |
                                                    | # id                 |
                                                    |   code (UNIQUE)      |
                                                    |   source_type        |
                                                    |   prefix             |
                                                    |   status             |
                                                    |   enterprise_id (FK) |
                                                    |   assigned_user_idFK |
                                                    |   assigned_at        |
                                                    |   used_at            |
                                                    |   meta (json)        |
                                                    +-----------+----------+
                                                                ^
                                       0..1                     |
+-------------------+            *      \                      |  *
|      users        |---------------------+--------------------+
| # id              |                     | assigned_user_id
|   ...             |                     |
+-------------------+                     |
                                          |
      1          *                        |
+-----+-----------+                       |
|   enterprises   | 1               *    |
| # id            |----------------------+
|   ...           |        products
+-----+-----------+       +------------------------+
                          |       products         |
                          | # id                   |
                          |   enterprise_id (FK)   |
                          |   name, brief, url     |
                          |   media(json), status  |
                          +------------+-----------+
                                       |
                             N..M      |      N..M
                          +------------v-----------+
                          |     disease_product    |
                          | # id (or PK pair)      |
                          |   disease_id (FK)      |
                          |   product_id (FK)      |
                          |   priority? note?      |
                          +------------+-----------+
                                       |
                                       |    *
                          +------------v-----------+
                          |       diseases         |
                          | # id                   |
                          |   code (UNIQUE)        |
                          |   name                 |
                          |   description?         |
                          +------------------------+

                推荐规则（区分全局/企业、自费/赠送）
+--------------------------------------------------+
|              recommendation_rules                 |
| # id                                             |
|   scope_type enum('global','enterprise')         |
|   applies_to enum('self_paid','gift','any')      |
|   enterprise_id (FK, global 时为空)              |
|   disease_id (FK)                                |
|   product_id (FK)                                |
|   priority int                                   |
|   active bool                                    |
|   starts_at? ends_at?                            |
+--------------------------------------------------+
```

## 2) 表结构定义（草案）

> 类型/索引以 MySQL 8+ 为参考；PG/SQLite 可做等价适配。长度按常见实践取值，可在实现时细化。

### 2.1 enterprises（企业）
- 字段
  - `id` bigint unsigned PK
  - `owner_user_id` bigint unsigned FK → `users.id`（可空）
  - `name` varchar(191) NOT NULL（唯一性建议：与业务决定是否 UNIQUE）
  - `contact_name` varchar(191) NULL
  - `contact_phone` varchar(32) NULL
  - `status` enum('active','inactive') NOT NULL DEFAULT 'active'
  - `meta` json NULL（额外资料/备注）
  - `created_at`/`updated_at`
- 索引/约束
  - KEY (`owner_user_id`), KEY (`status`)
  - 可选 UNIQUE(`name`)（如需强唯一）

### 2.2 detection_codes（检测号）
- 字段
  - `id` bigint unsigned PK
  - `code` varchar(64) NOT NULL UNIQUE
  - `source_type` enum('gift','self_paid') NOT NULL
  - `prefix` varchar(16) NOT NULL
  - `status` enum('available','assigned','used','expired') NOT NULL DEFAULT 'available'
  - `enterprise_id` bigint unsigned NULL FK → `enterprises.id`（自费码可空；赠送码通常非空）
  - `assigned_user_id` bigint unsigned NULL FK → `users.id`
  - `assigned_at` datetime NULL
  - `used_at` datetime NULL
  - `meta` json NULL（批次、导入来源、备注等）
  - `created_at`/`updated_at`
- 索引/约束
  - UNIQUE(`code`)
  - KEY(`enterprise_id`), KEY(`assigned_user_id`), KEY(`status`), KEY(`source_type`)
  -（可选）CHECK：`status` 与 `assigned_at/used_at` 一致性（应用层仍需事务与条件更新保障）

### 2.3 products（产品）
- 字段
  - `id` bigint unsigned PK
  - `enterprise_id` bigint unsigned NOT NULL FK → `enterprises.id`
  - `name` varchar(191) NOT NULL
  - `brief` text NULL
  - `url` varchar(512) NULL
  - `media` json NULL（图/视频等）
  - `status` enum('active','inactive') NOT NULL DEFAULT 'active'
  - `created_at`/`updated_at`
- 索引/约束
  - KEY(`enterprise_id`), KEY(`enterprise_id`,`status`)
  - 可选 UNIQUE(`enterprise_id`,`name`)（避免同企业同名重复）

### 2.4 diseases（疾病字典）
- 字段
  - `id` bigint unsigned PK
  - `code` varchar(64) NOT NULL UNIQUE（如 SBV/IAPV 等）
  - `name` varchar(191) NOT NULL
  - `description` text NULL
  - `created_at`/`updated_at`
- 索引/约束
  - UNIQUE(`code`)

### 2.5 disease_product（疾病-产品映射，多对多）
- 字段
  - `id` bigint unsigned PK（或以复合主键 `disease_id + product_id` 替代）
  - `disease_id` bigint unsigned NOT NULL FK → `diseases.id`
  - `product_id` bigint unsigned NOT NULL FK → `products.id`
  - `priority` int NULL DEFAULT 0（用于排序）
  - `note` varchar(191) NULL
  - `created_at`/`updated_at`
- 索引/约束
  - UNIQUE(`disease_id`,`product_id`)
  - KEY(`disease_id`), KEY(`product_id`)

### 2.6 recommendation_rules（推荐规则）
- 字段
  - `id` bigint unsigned PK
  - `scope_type` enum('global','enterprise') NOT NULL
  - `applies_to` enum('self_paid','gift','any') NOT NULL DEFAULT 'any'
  - `enterprise_id` bigint unsigned NULL FK → `enterprises.id`（当 `scope_type='enterprise'` 时必填）
  - `disease_id` bigint unsigned NOT NULL FK → `diseases.id`
  - `product_id` bigint unsigned NOT NULL FK → `products.id`
  - `priority` int NOT NULL DEFAULT 0
  - `active` boolean NOT NULL DEFAULT true
  - `starts_at` datetime NULL
  - `ends_at` datetime NULL
  - `created_at`/`updated_at`
- 索引/约束
  - 建议唯一性避免重复：
    - `UNIQUE(scope_type, COALESCE(enterprise_id,0), applies_to, disease_id, product_id)`
  - 常用索引：KEY(`enterprise_id`), KEY(`disease_id`), KEY(`product_id`), KEY(`active`), KEY(`scope_type`,`applies_to`)

## 3) 推荐计算（落地建议）
- 自费码（source_type=self_paid）：优先匹配 `scope=global` 且 `applies_to in ('self_paid','any')` 的规则，按 `priority` 排序；若无命中，回退 `disease_product` 映射。
- 企业码（source_type=gift 且有 enterprise_id）：优先 `scope=enterprise & enterprise_id=E & applies_to in ('gift','any')`；其后回退 `global`；再回退 `disease_product`。
- 仅返回 `products.status='active'` 的产品；时间窗（`starts_at/ends_at`）过滤有效规则。

## 4) 一致性与并发（与后续实现配合）
- `detection_codes.code` 唯一约束是并发绑定的基础；应用层需使用“事务 + 条件更新”完成 `available → assigned` 与 `assigned → used` 的原子流转。
- 企业绑定：`enterprise_id` 控制企业维度的推荐选择域，避免跨企业误推。

## 5) 面板与接口映射（预告）
- Admin 面板
  - 企业（enterprises）：基本资料管理
  - 检测号池（detection_codes）：导入/生成/状态流转/导出
  - 产品（products）：按企业管理、上架/下架
  - 疾病（diseases）：字典维护
  - 疾病-产品（disease_product）：多对多配置、排序
  - 推荐规则（recommendation_rules）：全局与企业规则配置
- API（当对接检测结果时）
  - 基于 `detection_codes.enterprise_id + disease` 计算推荐集（优先规则，其次映射）

## 6) 附注
- 本文档不包含 `detections` 主表；后续接入问卷/邮寄/结果后，再补充其与当前 ER 的关联与状态机。

