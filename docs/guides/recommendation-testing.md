# 推荐机制测试指南（管理员与开发）

本文档给出一条可复用的、端到端验证“推荐规则 → 小程序展示”的完整测试路径，覆盖企业优先、平台补足、赞助置顶、时间窗生效等关键场景。

## 1. 目标与范围
- 管理端：在 Admin 面板配置推荐规则（Recommendation Rules）。
- API：在检测结果详情接口中返回符合排序规则的 `recommendations[]`。
- 小程序：结果详情页展示前 2 条推荐（“企业推荐/平台推荐”徽标）。

## 2. 前置准备
1) 安装与迁移
```
composer install && npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
```
2) 启动（任选）
```
php artisan serve
# 或
composer dev
```

## 3. 准备最小数据（Tinker 逐行执行）
打开 Tinker：
```
php artisan tinker
```

在 Tinker 中依次执行：
```php
// 3.1 用户与 Token
$u = \App\Models\User::factory()->create(['role'=>'farmer','phone'=>'13800000001']);
$token = $u->createToken('dev')->plainTextToken;

// 3.2 两家企业（企业A、企业B）
$eA = \App\Models\Enterprise::create(['name'=>'企业A','owner_user_id'=>$u->id,'status'=>'active','code_prefix'=>'QYA']);
$eB = \App\Models\Enterprise::create(['name'=>'企业B','owner_user_id'=>$u->id,'status'=>'active','code_prefix'=>'QYB']);

// 3.3 疾病字典（示例 SBV）
$sbv = \App\Models\Disease::firstOrCreate(['code'=>'SBV'],['name'=>'囊状幼虫病']);

// 3.4 各企业上架一个产品
$pA = \App\Models\Product::create(['enterprise_id'=>$eA->id,'name'=>'企业A产品','brief'=>'A产品简介','url'=>null,'status'=>'active']);
$pB = \App\Models\Product::create(['enterprise_id'=>$eB->id,'name'=>'企业B产品','brief'=>'B产品简介','url'=>'https://example.com/b','status'=>'active']);

// 3.5 （可选）疾病-产品兜底映射
\DB::table('disease_product')->insert(['disease_id'=>$sbv->id,'product_id'=>$pA->id,'priority'=>0]);
\DB::table('disease_product')->insert(['disease_id'=>$sbv->id,'product_id'=>$pB->id,'priority'=>1]);
```

## 4. 配置推荐规则（快速路径）
> 推荐：也可在 Admin → Recommendation Rules 中可视化创建；默认层级 Tier：企业=10，全局=20。

```php
// 4.1 企业规则（企业A，gift 专属；企业优先）
\App\Models\RecommendationRule::create([
  'scope_type'=>'enterprise', 'applies_to'=>'gift', 'enterprise_id'=>$eA->id,
  'disease_id'=>$sbv->id, 'product_id'=>$pA->id,
  'priority'=>0, 'tier'=>10, 'active'=>true,
]);

// 4.2 平台全局规则（gift 补足）
\App\Models\RecommendationRule::create([
  'scope_type'=>'global', 'applies_to'=>'gift',
  'disease_id'=>$sbv->id, 'product_id'=>$pB->id,
  'priority'=>0, 'tier'=>20, 'active'=>true,
]);

// 4.3 （可选）赞助置顶：将全局推广置于最前（Tier 更小）
\App\Models\RecommendationRule::create([
  'scope_type'=>'global', 'applies_to'=>'gift',
  'disease_id'=>$sbv->id, 'product_id'=>$pB->id,
  'priority'=>0, 'tier'=>8, 'sponsored'=>true, 'active'=>true,
]);
```

时间窗验证（可选）：设置 `starts_at/ends_at`，仅时间窗内有效。

## 5. 构造检测数据（企业检测码 gift 场景）
```php
// 5.1 发放企业A检测码给用户
$code = \App\Models\DetectionCode::create([
  'code'=>'TESTA001','source_type'=>'gift','enterprise_id'=>$eA->id,
  'status'=>'assigned','assigned_user_id'=>$u->id,'assigned_at'=>now(),
]);

// 5.2 生成检测记录（让 SBV 呈阳性；状态已完成）
$d = \App\Models\Detection::create([
  'user_id'=>$u->id,'detection_code_id'=>$code->id,
  'status'=>'completed','submitted_at'=>now(),'reported_at'=>now(),
  'rna_sbv_level'=>'weak','sample_no'=>'S-001'
]);
```

## 6. 验证 API 返回（详情推荐）
将 `{TOKEN}` 与 `{ID}` 替换为上文 `$token` 与 `$d->id`：
```
curl -H "Authorization: Bearer {TOKEN}" \
     http://127.0.0.1:8000/api/detections/{ID}
```

预期：
- `recommendations` 为数组，按以下顺序：
  - 如存在“赞助置顶”（Tier=8 的全局规则）→ 第 1 条为企业B产品（source='platform'，external）。
  - 否则 → 第 1 条为企业A规则（source='enterprise'；若无 url 则 targetType='internal'）。
- 仅返回 `active=1`、处于时间窗内的规则，且 `products.status='active'`。
- 若规则不足，则使用 `disease_product` 兜底（先本企业产品，再任意企业产品）。

## 7. 小程序联调（可选）
1) 在 weapp 项目配置后端地址：`/Users/tudouya/WWW/bee-guard-weapp/utils/config.js` 的 `apiBase`。
2) 注入 Token（走登录或手动写入存储）。
3) 打开“结果详情”页面，推荐区域默认展示前 2 条（徽标：企业推荐/平台推荐）。

## 8. 自费码对比测试（可选）
```php
$code2 = \App\Models\DetectionCode::create([
  'code'=>'TESTS001','source_type'=>'self_paid',
  'status'=>'assigned','assigned_user_id'=>$u->id,'assigned_at'=>now(),
]);

$d2 = \App\Models\Detection::create([
  'user_id'=>$u->id,'detection_code_id'=>$code2->id,
  'status'=>'completed','submitted_at'=>now(),'reported_at'=>now(),
  'rna_sbv_level'=>'weak','sample_no'=>'S-002'
]);
```
调用详情接口：仅走“全局规则（按 Tier→Priority）→ 兜底映射”。

## 9. 核心检查点
- 企业优先：企业规则默认 `Tier=10`，全局 `Tier=20`。
- 赞助置顶：将赞助规则 `Tier` 设更小（如 8）即可排在最前。
- 有效性：仅 `active=1`、时间窗内；产品必须 `status='active'`。
- 去重：同一产品不重复出现。
- 兜底：规则不命中时按映射补全（先本企业，再任意企业）。

## 10. 常见问题
- 推荐为空：检查是否有阳性病原；规则是否 active 且在时间窗内；产品是否 active；applies_to 是否匹配来源；Tier/优先级是否被更高层盖住。
- “平台推广”不靠前：调小该规则的 Tier（数值越小越靠前），如 8。

—— 完 ——

