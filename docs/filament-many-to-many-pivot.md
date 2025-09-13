# Filament v4 多对多关联与 Pivot 字段实现笔记

本文档记录“疾病（diseases）⇄ 产品（products）”多对多关联在 Eloquent 与 Filament v4 中的实现要点，以及典型坑位与解决方式。适用于本仓库中病种-产品映射模块（disease_product）。

## 1) 多对多与中间表
- 关系：`diseases <-> disease_product <-> products`
- 中间表字段：`disease_id`、`product_id`、业务字段 `priority`、`note`、`timestamps`
- 约束：`UNIQUE(disease_id, product_id)`，两侧外键建议级联删除（已在迁移中设置）

## 2) 模型关系（关键：withPivot）
在 Eloquent 中声明 pivot 字段，既影响读取行为，也影响 Filament 的写入行为。

```php
// app/Models/Disease.php
public function products()
{
    return $this->belongsToMany(Product::class, 'disease_product')
        ->withPivot(['priority', 'note'])
        ->withTimestamps();
}

// app/Models/Product.php
public function diseases()
{
    return $this->belongsToMany(Disease::class, 'disease_product')
        ->withPivot(['priority', 'note'])
        ->withTimestamps();
}
```

- 读取：声明了 `withPivot(['priority','note'])` 后，可通过 `$record->pivot->priority/note` 访问值。
- 写入（Filament）：Filament v4 的 Attach/Edit 动作会参考关系上的 pivot 列清单，将表单中“同名字段”写入中间表；未声明则会被忽略。
- 表单命名：在 Relation Manager 的表单中，pivot 字段直接使用字段名本身（如 `priority`、`note`），不要使用 `pivot.priority` 这种表单字段名。

## 3) Filament v4 命名空间与动作
- v4 中动作类位于 `\Filament\Actions\...`
  - 常用：`AttachAction`、`EditAction`、`DetachAction`、`DetachBulkAction`
- 旧的 `\Filament\Tables\Actions\...` 不再适用，使用会出现 Class not found。

## 4) AttachAction 的“记录选择器”与 pivot 字段
- AttachAction 默认会提供“记录选择器”（选择要关联的另一侧记录）。
- 若直接调用 `->schema([...])`，会完全替换默认 Schema，导致“记录选择器”消失、弹窗中只剩自定义字段。
- 正确方式：在 `schema` 闭包参数中接收 `Schema $schema`，保留默认组件后，再“追加” pivot 字段。

```php
// Disease → Products 关系页签的示例（简化）
->headerActions([
    \Filament\Actions\AttachAction::make()
        ->preloadRecordSelect() // 预加载选项（可选）
        // 仅允许选择 active 产品（可选）
        ->recordSelectOptionsQuery(fn (\Illuminate\Database\Eloquent\Builder $q) => $q->where('status', 'active'))
        // 关键：保留默认的“记录选择器”，并在其后追加 pivot 字段
        ->schema(fn (\Filament\Schemas\Schema $schema) => [
            ...$schema->getComponents(), // 默认组件（含记录选择器）
            \Filament\Forms\Components\TextInput::make('priority')->numeric()->default(0),
            \Filament\Forms\Components\TextInput::make('note')->maxLength(191),
        ]),
])
```

- EditAction：Relation Manager 的 `form(Schema $schema)` 中声明的字段用于编辑时显示；字段名应与 pivot 列同名（如 `priority`、`note`），v4 会自动更新中间表。

```php
public function form(\Filament\Schemas\Schema $schema): \Filament\Schemas\Schema
{
    return $schema->schema([
        \Filament\Forms\Components\TextInput::make('priority')->numeric()->default(0),
        \Filament\Forms\Components\TextInput::make('note')->maxLength(191),
    ]);
}
```

- Detach / 批量 Detach：

```php
->actions([
    \Filament\Actions\EditAction::make(),
    \Filament\Actions\DetachAction::make(),
])
->bulkActions([
    \Filament\Actions\DetachBulkAction::make(),
])
```

## 5) “创建时”为何看不到关系页签？
- 多对多写入需要两侧主键；创建产品时尚未有 `product_id`，Relation Manager 无法提前写入 pivot，所以 Filament 仅在“记录存在”后（编辑/查看页）显示关系页签。

### 创建时也要建立关联的两种方案
- 简版（不含 pivot 字段）：
  - 在主表单中加入 `Select::relationship('diseases','name')->multiple()`，创建成功后 Filament 会自动同步关联（但不写 pivot 业务字段）。
- 完整版（含 pivot 字段）：
  - 在创建表单中放一个 Repeater（每行包含 `disease_id` + `priority/note`），在 `afterCreate()` 或 `afterSave()` 钩子里手动 `$record->diseases()->attach($diseaseId, [pivot...])`。

## 6) 常见问题与排查
- “Class not found: Filament\\Tables\\Actions\\...” → 使用了 v4 之前的命名空间，改为 `\Filament\Actions\...`。
- “Attach 弹窗没有记录选择器” → 使用 `->schema([...])` 覆盖了默认 Schema。改为 `->schema(fn (Schema $schema) => [...$schema->getComponents(), ...])` 合并默认组件。
- “pivot 字段未保存” → 模型关系缺少 `withPivot([...])`；或表单字段名写成 `pivot.priority`。应声明 withPivot 并用 `priority`/`note` 字段名。
- “创建时无法在页签中建立关联” → 记录尚未有主键。用 Select 多选（不含 pivot）或 Repeater + afterSave 手动 attach（含 pivot）。

## 7) 验证清单
- 迁移：`disease_product` 存在唯一约束与外键；`priority/note` 字段正常。
- 模型：多对多关系均声明 `withPivot(['priority','note'])->withTimestamps()`。
- Relation Manager：
  - 列表展示 `pivot.priority/note`。
  - AttachAction 合并默认记录选择器，并追加 `priority/note` 字段。
  - EditAction 表单字段命名使用 `priority/note`。
  - Detach 与批量 Detach 可用。

以上约定已在本项目的“疾病-产品映射”模块中落地，可按此文档自查与扩展。

