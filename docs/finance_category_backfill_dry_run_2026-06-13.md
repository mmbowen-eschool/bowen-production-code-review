# Finance Category 历史数据安全回填 Dry Run 方案

**文档时间：** 2026-06-13 08:00  
**文档性质：** Dry Run 预览（只读分析 + 方案规划，不执行任何写入操作）  
**目标学校：** SCH202615  
**前置依赖：** Phase 3 `finance_uncategorized_data_check_2026-06-13.md`

---

## 1. 背景

Phase 3 只读检查确认了 Uncategorized Income / Expense 的生成机制：

| 问题 | 根因 |
|------|------|
| Uncategorized Income | `fees_class_types.finance_category_id IS NULL` → 通过该 fee item 收到的所有款项在 Finance Report 中显示为 Uncategorized |
| Uncategorized Expense | `expenses.finance_category_id IS NULL` → 支出在 Finance Report 中显示为 Uncategorized |
| 历史数据 | `finance_category_id` 字段于 2026-06-11 新增，历史记录未被自动赋值 |

**关键保证：** 分类回填不会影响金额。Total Income、Net Income、Outstanding 完全不变。仅改变 Category Breakdown 中的标签名称。

---

## 2. 当前 Uncategorized 来源（回顾）

```
收入端（Income）:
  fees_class_types (optional=0) → finance_category_id IS NULL
    → 关联的 fees_paid → compulsory_fees 全部归入 "Uncategorized"

  fees_class_types (optional=1) → finance_category_id IS NULL
    → 关联的 optional_fees 全部归入 "Uncategorized"

支出端（Expense）:
  expenses.finance_category_id IS NULL
    → Finance Report / Dashboard 中显示为 "Uncategorized"
```

---

## 3. 可用 Finance Categories

### 3.1 查询（需在服务器执行）

```sql
SELECT
    id,
    type,
    category_code,
    name,
    local_name,
    is_default,
    is_active,
    sort_order
FROM finance_categories
ORDER BY type DESC, sort_order, name;
```

### 3.2 预期分类清单（收入）

| id | category_code | name | local_name | type | is_active |
|----|---------------|------|------------|------|-----------|
| ? | TUITION_FEE | Tuition Fee | 学费 | income | true |
| ? | REGISTRATION_FEE | Registration Fee | 报名费/注册费 | income | true |
| ? | MATERIAL_FEE | Material Fee | 教材费 | income | true |
| ? | UNIFORM_FEE | Uniform Fee | 校服 | income | true |
| ? | ACTIVITY_FEE | Activity Fee | 活动费 | income | true |
| ? | EXAM_FEE | Exam Fee | 考试费 | income | true |
| ? | TRANSPORTATION_FEE | Transportation Fee | 交通费 | income | true |
| ? | OTHER_INCOME | Other Income | 其他收入 | income | true |

### 3.3 预期分类清单（支出）

| id | category_code | name | local_name | type | is_active |
|----|---------------|------|------------|------|-----------|
| ? | SALARY | Salary | 工资 | expense | true |
| ? | RENT | Rent | 房租 | expense | true |
| ? | UTILITIES | Utilities | 水电网 | expense | true |
| ? | TEACHING_MATERIALS | Teaching Materials | 教材教具 | expense | true |
| ? | MARKETING | Marketing | 宣传/广告 | expense | true |
| ? | MAINTENANCE | Maintenance | 维修 | expense | true |
| ? | TRANSPORTATION | Transportation | 交通 | expense | true |
| ? | OFFICE_SUPPLIES | Office Supplies | 办公用品 | expense | true |
| ? | OTHER_EXPENSES | Other Expenses | 其他支出 | expense | true |

> **注意：** 以上 id 需要在服务器上通过查询确认。如果某个分类不存在（例如 `Activity Fee` 或 `Teaching Materials`），不能自动创建，需要先在管理后台手动新增后再回填。

---

## 4. 自动匹配规则

### 4.1 Income 匹配规则（`recommendIncomeCategory`）

基于 `fix_finance_categories.php` 的匹配逻辑：

| 关键词（大小写不敏感） | 匹配分类 | 置信度 |
|------------------------|----------|--------|
| `tuition` / `学费` | Tuition Fee | HIGH |
| `registration` / `报名` / `注册` | Registration Fee | HIGH |
| `material` / `book` / `教材` / `课本` / `书本` | Material Fee | HIGH |
| `uniform` / `校服` | Uniform Fee | HIGH |
| `activity` / `camp` / `活动` / `研学` / `夏令营` | Activity Fee | HIGH |
| `exam` / `hsk` / `考试` | Exam Fee | HIGH |
| `transport` / `bus` / `校车` | Transportation Fee | HIGH |
| 以上均不匹配 | **不自动回填 → Manual Review** | N/A |

### 4.2 Expense 匹配规则（`recommendExpenseCategory`）

基于 `fix_finance_categories.php` 的匹配逻辑：

| 关键词（大小写不敏感，搜索 title + description） | 匹配分类 | 置信度 |
|--------------------------------------------------|----------|--------|
| `salary` / `payroll` / `工资` / `薪资` | Salary | HIGH |
| `rent` / `房租` | Rent | HIGH |
| `utility` / `electric` / `水电` / `网费` | Utilities | HIGH |
| `teaching` / `教材` / `教具` | Teaching Materials | HIGH |
| `market` / `宣传` / `广告` | Marketing | HIGH |
| `maintenance` / `repair` / `维修` | Maintenance | HIGH |
| `transport` / `bus` / `car` / `交通` | Transportation | HIGH |
| `office` / `stationery` / `办公` / `文具` | Office Supplies | HIGH |
| 以上均不匹配 | **不自动回填 → Manual Review** | N/A |

### 4.3 模糊/歧义匹配的特殊处理

某些关键词可能匹配多个分类，需要额外判断：

| 关键词 | 可能的分类 | 额外判断逻辑 |
|--------|-----------|-------------|
| `transport` / `交通` (收入端) | Transportation Fee | 收入端只有 Transportation Fee |
| `transport` / `交通` / `bus` (支出端) | Transportation | 支出端只有 Transportation |
| `book` / `material` (收入端) | Material Fee | 明确属于教材类收入 |
| `material` / `教材` (支出端) | Teaching Materials | 支出端是 Teaching Materials |
| `activity` / `camp` (收入端) | Activity Fee | 活动/夏令营收费 |

---

## 5. Fee Items Dry Run 清单（需在服务器执行）

### 5.1 查询 SQL（SELECT ONLY - 只读）

```sql
-- A. Fee Items Dry Run: 列出所有未配置 finance_category_id 的 fees_class_types
SELECT 
    fct.id                           AS fct_id,
    fct.fees_id                      AS fees_id,
    fct.fees_type_id                 AS fees_type_id,
    ft.name                          AS fee_type_name,
    CASE WHEN fct.optional = 1 THEN 'Optional' ELSE 'Compulsory' END AS fee_nature,
    fct.amount                       AS fee_amount,
    fct.fee_currency                 AS currency,
    COALESCE(fct.fee_amount_mmk, fct.amount) AS amount_mmk,
    sy.name                          AS session_year,
    cs.name                          AS class_name,
    f.name                           AS fee_structure_name,
    fct.finance_category_id          AS current_fc_id,
    -- 已收金额 (Compulsory)
    COALESCE(
        (SELECT SUM(cf.amount) 
         FROM compulsory_fees cf 
         JOIN fees_paids fp ON fp.id = cf.fees_paid_id AND fp.deleted_at IS NULL
         WHERE fp.fees_id = fct.fees_id 
           AND cf.status = 'Success' 
           AND cf.deleted_at IS NULL
           AND cf.school_id = (SELECT id FROM schools WHERE code = 'SCH202615' LIMIT 1)
        ), 0
    ) AS historical_compulsory_paid,
    -- 已收金额 (Optional)
    COALESCE(
        (SELECT SUM(of2.amount) 
         FROM optional_fees of2 
         WHERE of2.fees_class_id = fct.id 
           AND of2.status = 'Success' 
           AND of2.deleted_at IS NULL
           AND of2.school_id = (SELECT id FROM schools WHERE code = 'SCH202615' LIMIT 1)
        ), 0
    ) AS historical_optional_paid,
    CASE
        -- Income 匹配规则
        WHEN LOWER(ft.name) LIKE '%tuition%' OR LOWER(ft.name) LIKE '%学费%'
            THEN (SELECT id FROM finance_categories WHERE name = 'Tuition Fee' LIMIT 1)
        WHEN LOWER(ft.name) LIKE '%registration%' OR LOWER(ft.name) LIKE '%报名%' OR LOWER(ft.name) LIKE '%注册%'
            THEN (SELECT id FROM finance_categories WHERE name = 'Registration Fee' LIMIT 1)
        WHEN LOWER(ft.name) LIKE '%material%' OR LOWER(ft.name) LIKE '%book%' OR LOWER(ft.name) LIKE '%教材%' OR LOWER(ft.name) LIKE '%课本%' OR LOWER(ft.name) LIKE '%书本%'
            THEN (SELECT id FROM finance_categories WHERE name = 'Material Fee' LIMIT 1)
        WHEN LOWER(ft.name) LIKE '%uniform%' OR LOWER(ft.name) LIKE '%校服%'
            THEN (SELECT id FROM finance_categories WHERE name = 'Uniform Fee' LIMIT 1)
        WHEN LOWER(ft.name) LIKE '%activity%' OR LOWER(ft.name) LIKE '%camp%' OR LOWER(ft.name) LIKE '%活动%' OR LOWER(ft.name) LIKE '%研学%' OR LOWER(ft.name) LIKE '%夏令营%'
            THEN (SELECT id FROM finance_categories WHERE name = 'Activity Fee' LIMIT 1)
        WHEN LOWER(ft.name) LIKE '%exam%' OR LOWER(ft.name) LIKE '%hsk%' OR LOWER(ft.name) LIKE '%考试%'
            THEN (SELECT id FROM finance_categories WHERE name = 'Exam Fee' LIMIT 1)
        WHEN LOWER(ft.name) LIKE '%transport%' OR LOWER(ft.name) LIKE '%bus%' OR LOWER(ft.name) LIKE '%校车%'
            THEN (SELECT id FROM finance_categories WHERE name = 'Transportation Fee' LIMIT 1)
        ELSE NULL
    END                               AS suggested_finance_category_id,
    CASE
        WHEN LOWER(ft.name) LIKE '%tuition%' OR LOWER(ft.name) LIKE '%学费%' THEN 'Tuition Fee'
        WHEN LOWER(ft.name) LIKE '%registration%' OR LOWER(ft.name) LIKE '%报名%' OR LOWER(ft.name) LIKE '%注册%' THEN 'Registration Fee'
        WHEN LOWER(ft.name) LIKE '%material%' OR LOWER(ft.name) LIKE '%book%' OR LOWER(ft.name) LIKE '%教材%' OR LOWER(ft.name) LIKE '%课本%' OR LOWER(ft.name) LIKE '%书本%' THEN 'Material Fee'
        WHEN LOWER(ft.name) LIKE '%uniform%' OR LOWER(ft.name) LIKE '%校服%' THEN 'Uniform Fee'
        WHEN LOWER(ft.name) LIKE '%activity%' OR LOWER(ft.name) LIKE '%camp%' OR LOWER(ft.name) LIKE '%活动%' OR LOWER(ft.name) LIKE '%研学%' OR LOWER(ft.name) LIKE '%夏令营%' THEN 'Activity Fee'
        WHEN LOWER(ft.name) LIKE '%exam%' OR LOWER(ft.name) LIKE '%hsk%' OR LOWER(ft.name) LIKE '%考试%' THEN 'Exam Fee'
        WHEN LOWER(ft.name) LIKE '%transport%' OR LOWER(ft.name) LIKE '%bus%' OR LOWER(ft.name) LIKE '%校车%' THEN 'Transportation Fee'
        ELSE NULL
    END                               AS suggested_category_name,
    CASE
        WHEN LOWER(ft.name) LIKE '%tuition%' OR LOWER(ft.name) LIKE '%学费%' THEN 'HIGH'
        WHEN LOWER(ft.name) LIKE '%registration%' OR LOWER(ft.name) LIKE '%报名%' OR LOWER(ft.name) LIKE '%注册%' THEN 'HIGH'
        WHEN LOWER(ft.name) LIKE '%material%' OR LOWER(ft.name) LIKE '%book%' OR LOWER(ft.name) LIKE '%教材%' OR LOWER(ft.name) LIKE '%课本%' OR LOWER(ft.name) LIKE '%书本%' THEN 'HIGH'
        WHEN LOWER(ft.name) LIKE '%uniform%' OR LOWER(ft.name) LIKE '%校服%' THEN 'HIGH'
        WHEN LOWER(ft.name) LIKE '%activity%' OR LOWER(ft.name) LIKE '%camp%' OR LOWER(ft.name) LIKE '%活动%' OR LOWER(ft.name) LIKE '%研学%' OR LOWER(ft.name) LIKE '%夏令营%' THEN 'HIGH'
        WHEN LOWER(ft.name) LIKE '%exam%' OR LOWER(ft.name) LIKE '%hsk%' OR LOWER(ft.name) LIKE '%考试%' THEN 'HIGH'
        WHEN LOWER(ft.name) LIKE '%transport%' OR LOWER(ft.name) LIKE '%bus%' OR LOWER(ft.name) LIKE '%校车%' THEN 'HIGH'
        WHEN LOWER(ft.name) LIKE '%other%' OR LOWER(ft.name) LIKE '%misc%' OR LOWER(ft.name) LIKE '%其他%' THEN 'LOW'
        WHEN LOWER(ft.name) LIKE '%fee%' THEN 'MEDIUM'
        ELSE 'LOW'
    END                               AS confidence,
    CASE
        WHEN LOWER(ft.name) LIKE '%tuition%' OR LOWER(ft.name) LIKE '%学费%' THEN 'AUTO_BACKFILL'
        WHEN LOWER(ft.name) LIKE '%registration%' OR LOWER(ft.name) LIKE '%报名%' OR LOWER(ft.name) LIKE '%注册%' THEN 'AUTO_BACKFILL'
        WHEN LOWER(ft.name) LIKE '%material%' OR LOWER(ft.name) LIKE '%book%' OR LOWER(ft.name) LIKE '%教材%' OR LOWER(ft.name) LIKE '%课本%' OR LOWER(ft.name) LIKE '%书本%' THEN 'AUTO_BACKFILL'
        WHEN LOWER(ft.name) LIKE '%uniform%' OR LOWER(ft.name) LIKE '%校服%' THEN 'AUTO_BACKFILL'
        WHEN LOWER(ft.name) LIKE '%activity%' OR LOWER(ft.name) LIKE '%camp%' OR LOWER(ft.name) LIKE '%活动%' OR LOWER(ft.name) LIKE '%研学%' OR LOWER(ft.name) LIKE '%夏令营%' THEN 'AUTO_BACKFILL'
        WHEN LOWER(ft.name) LIKE '%exam%' OR LOWER(ft.name) LIKE '%hsk%' OR LOWER(ft.name) LIKE '%考试%' THEN 'AUTO_BACKFILL'
        WHEN LOWER(ft.name) LIKE '%transport%' OR LOWER(ft.name) LIKE '%bus%' OR LOWER(ft.name) LIKE '%校车%' THEN 'AUTO_BACKFILL'
        WHEN LOWER(ft.name) LIKE '%other%' OR LOWER(ft.name) LIKE '%misc%' OR LOWER(ft.name) LIKE '%其他%' THEN 'MANUAL_REVIEW'
        WHEN LOWER(ft.name) LIKE '%fee%' THEN 'MANUAL_REVIEW'
        ELSE 'MANUAL_REVIEW'
    END                               AS action,
    CASE
        WHEN LOWER(ft.name) LIKE '%tuition%' OR LOWER(ft.name) LIKE '%学费%' THEN 'Name clearly matches Tuition Fee'
        WHEN LOWER(ft.name) LIKE '%registration%' OR LOWER(ft.name) LIKE '%报名%' OR LOWER(ft.name) LIKE '%注册%' THEN 'Name clearly matches Registration Fee'
        WHEN LOWER(ft.name) LIKE '%material%' OR LOWER(ft.name) LIKE '%book%' OR LOWER(ft.name) LIKE '%教材%' OR LOWER(ft.name) LIKE '%课本%' OR LOWER(ft.name) LIKE '%书本%' THEN 'Name clearly matches Material Fee'
        WHEN LOWER(ft.name) LIKE '%uniform%' OR LOWER(ft.name) LIKE '%校服%' THEN 'Name clearly matches Uniform Fee'
        WHEN LOWER(ft.name) LIKE '%activity%' OR LOWER(ft.name) LIKE '%camp%' OR LOWER(ft.name) LIKE '%活动%' OR LOWER(ft.name) LIKE '%研学%' OR LOWER(ft.name) LIKE '%夏令营%' THEN 'Name clearly matches Activity Fee'
        WHEN LOWER(ft.name) LIKE '%exam%' OR LOWER(ft.name) LIKE '%hsk%' OR LOWER(ft.name) LIKE '%考试%' THEN 'Name clearly matches Exam Fee'
        WHEN LOWER(ft.name) LIKE '%transport%' OR LOWER(ft.name) LIKE '%bus%' OR LOWER(ft.name) LIKE '%校车%' THEN 'Name clearly matches Transportation Fee'
        WHEN LOWER(ft.name) LIKE '%other%' OR LOWER(ft.name) LIKE '%misc%' OR LOWER(ft.name) LIKE '%其他%' THEN 'Ambiguous name - needs manual review'
        WHEN LOWER(ft.name) LIKE '%fee%' THEN 'Contains "fee" but ambiguous - needs manual review'
        ELSE 'No keyword match - needs manual review'
    END                               AS reason
FROM fees_class_types fct
LEFT JOIN fees_types ft ON ft.id = fct.fees_type_id AND ft.deleted_at IS NULL
LEFT JOIN fees f ON f.id = fct.fees_id AND f.deleted_at IS NULL
LEFT JOIN session_years sy ON sy.id = f.session_year_id
LEFT JOIN classes cs ON cs.id = fct.class_id AND cs.deleted_at IS NULL
WHERE fct.finance_category_id IS NULL
  AND fct.deleted_at IS NULL
  AND cs.school_id = (SELECT id FROM schools WHERE code = 'SCH202615' LIMIT 1)
ORDER BY 
    CASE
        WHEN LOWER(ft.name) LIKE '%tuition%' OR LOWER(ft.name) LIKE '%学费%' THEN 1
        WHEN LOWER(ft.name) LIKE '%registration%' OR LOWER(ft.name) LIKE '%报名%' OR LOWER(ft.name) LIKE '%注册%' THEN 2
        WHEN LOWER(ft.name) LIKE '%material%' OR LOWER(ft.name) LIKE '%book%' OR LOWER(ft.name) LIKE '%教材%' OR LOWER(ft.name) LIKE '%课本%' OR LOWER(ft.name) LIKE '%书本%' THEN 3
        WHEN LOWER(ft.name) LIKE '%uniform%' OR LOWER(ft.name) LIKE '%校服%' THEN 4
        WHEN LOWER(ft.name) LIKE '%activity%' OR LOWER(ft.name) LIKE '%camp%' OR LOWER(ft.name) LIKE '%活动%' OR LOWER(ft.name) LIKE '%研学%' OR LOWER(ft.name) LIKE '%夏令营%' THEN 5
        WHEN LOWER(ft.name) LIKE '%exam%' OR LOWER(ft.name) LIKE '%hsk%' OR LOWER(ft.name) LIKE '%考试%' THEN 6
        WHEN LOWER(ft.name) LIKE '%transport%' OR LOWER(ft.name) LIKE '%bus%' OR LOWER(ft.name) LIKE '%校车%' THEN 7
        ELSE 99
    END,
    fct.id;
```

### 5.2 统计查询

```sql
-- 统计：Fee Items 按置信度分组
SELECT 
    CASE
        WHEN LOWER(ft.name) LIKE '%tuition%' OR LOWER(ft.name) LIKE '%学费%' THEN 'AUTO:Tution Fee'
        WHEN LOWER(ft.name) LIKE '%registration%' OR LOWER(ft.name) LIKE '%报名%' OR LOWER(ft.name) LIKE '%注册%' THEN 'AUTO:Registration Fee'
        WHEN LOWER(ft.name) LIKE '%material%' OR LOWER(ft.name) LIKE '%book%' OR LOWER(ft.name) LIKE '%教材%' OR LOWER(ft.name) LIKE '%课本%' OR LOWER(ft.name) LIKE '%书本%' THEN 'AUTO:Material Fee'
        WHEN LOWER(ft.name) LIKE '%uniform%' OR LOWER(ft.name) LIKE '%校服%' THEN 'AUTO:Uniform Fee'
        WHEN LOWER(ft.name) LIKE '%activity%' OR LOWER(ft.name) LIKE '%camp%' OR LOWER(ft.name) LIKE '%活动%' OR LOWER(ft.name) LIKE '%研学%' OR LOWER(ft.name) LIKE '%夏令营%' THEN 'AUTO:Activity Fee'
        WHEN LOWER(ft.name) LIKE '%exam%' OR LOWER(ft.name) LIKE '%hsk%' OR LOWER(ft.name) LIKE '%考试%' THEN 'AUTO:Exam Fee'
        WHEN LOWER(ft.name) LIKE '%transport%' OR LOWER(ft.name) LIKE '%bus%' OR LOWER(ft.name) LIKE '%校车%' THEN 'AUTO:Transportation Fee'
        ELSE 'MANUAL_REVIEW'
    END AS proposed_action,
    COUNT(*) AS item_count,
    SUM(COALESCE(fct.fee_amount_mmk, fct.amount)) AS total_fee_amount,
    SUM(
        COALESCE(
            (SELECT SUM(cf.amount) FROM compulsory_fees cf 
             JOIN fees_paids fp ON fp.id = cf.fees_paid_id AND fp.deleted_at IS NULL
             WHERE fp.fees_id = fct.fees_id AND cf.status = 'Success' AND cf.deleted_at IS NULL
             AND cf.school_id = (SELECT id FROM schools WHERE code = 'SCH202615' LIMIT 1)), 0
        )
    ) AS compulsory_paid_amount,
    SUM(
        COALESCE(
            (SELECT SUM(of2.amount) FROM optional_fees of2 
             WHERE of2.fees_class_id = fct.id AND of2.status = 'Success' AND of2.deleted_at IS NULL
             AND of2.school_id = (SELECT id FROM schools WHERE code = 'SCH202615' LIMIT 1)), 0
        )
    ) AS optional_paid_amount
FROM fees_class_types fct
LEFT JOIN fees_types ft ON ft.id = fct.fees_type_id AND ft.deleted_at IS NULL
LEFT JOIN classes cs ON cs.id = fct.class_id AND cs.deleted_at IS NULL
WHERE fct.finance_category_id IS NULL
  AND fct.deleted_at IS NULL
  AND cs.school_id = (SELECT id FROM schools WHERE code = 'SCH202615' LIMIT 1)
GROUP BY proposed_action
ORDER BY proposed_action;
```

---

## 6. Expenses Dry Run 清单（需在服务器执行）

### 6.1 查询 SQL（SELECT ONLY - 只读）

```sql
-- B. Expenses Dry Run: 列出所有未配置 finance_category_id 的 expenses
SELECT 
    e.id                             AS expense_id,
    COALESCE(e.title, e.description) AS expense_name,
    e.description                    AS description,
    e.amount                         AS amount,
    e.amount_mmk                     AS amount_mmk,
    e.date                           AS expense_date,
    e.category_id                    AS old_expense_category_id,
    ec.name                          AS old_expense_category_name,
    e.finance_category_id            AS current_fc_id,
    CASE
        -- Expense 匹配规则（搜索 title + description）
        WHEN LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%salary%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%payroll%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%工资%'
            THEN (SELECT id FROM finance_categories WHERE name = 'Salary' LIMIT 1)
        WHEN LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%rent%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%房租%'
            THEN (SELECT id FROM finance_categories WHERE name = 'Rent' LIMIT 1)
        WHEN LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%utilit%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%electric%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%水电%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%网费%'
            THEN (SELECT id FROM finance_categories WHERE name = 'Utilities' LIMIT 1)
        WHEN LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%teaching%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%教材%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%教具%'
            THEN (SELECT id FROM finance_categories WHERE name = 'Teaching Materials' LIMIT 1)
        WHEN LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%market%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%宣传%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%广告%'
            THEN (SELECT id FROM finance_categories WHERE name = 'Marketing' LIMIT 1)
        WHEN LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%maintenance%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%repair%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%维修%'
            THEN (SELECT id FROM finance_categories WHERE name = 'Maintenance' LIMIT 1)
        WHEN LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%transport%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%bus%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%car%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%交通%'
            THEN (SELECT id FROM finance_categories WHERE name = 'Transportation' LIMIT 1)
        WHEN LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%office%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%stationery%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%办公%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%文具%'
            THEN (SELECT id FROM finance_categories WHERE name = 'Office Supplies' LIMIT 1)
        ELSE NULL
    END                               AS suggested_finance_category_id,
    CASE
        WHEN LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%salary%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%payroll%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%工资%'
            THEN 'Salary'
        WHEN LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%rent%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%房租%'
            THEN 'Rent'
        WHEN LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%utilit%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%electric%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%水电%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%网费%'
            THEN 'Utilities'
        WHEN LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%teaching%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%教材%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%教具%'
            THEN 'Teaching Materials'
        WHEN LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%market%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%宣传%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%广告%'
            THEN 'Marketing'
        WHEN LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%maintenance%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%repair%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%维修%'
            THEN 'Maintenance'
        WHEN LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%transport%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%bus%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%car%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%交通%'
            THEN 'Transportation'
        WHEN LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%office%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%stationery%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%办公%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%文具%'
            THEN 'Office Supplies'
        ELSE NULL
    END                               AS suggested_category_name,
    CASE
        WHEN LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%salary%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%payroll%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%工资%'
            THEN 'HIGH'
        WHEN LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%rent%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%房租%'
            THEN 'HIGH'
        WHEN LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%utilit%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%electric%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%水电%' OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%网费%'
            THEN 'HIGH'
        WHEN LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%teaching%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%教材%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%教具%'
            THEN 'HIGH'
        WHEN LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%market%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%宣传%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%广告%'
            THEN 'HIGH'
        WHEN LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%maintenance%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%repair%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%维修%'
            THEN 'HIGH'
        WHEN LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%transport%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%bus%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%car%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%交通%'
            THEN 'HIGH'
        WHEN LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%office%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%stationery%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%办公%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%文具%'
            THEN 'HIGH'
        WHEN LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%other%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%misc%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%其他%'
            THEN 'LOW'
        WHEN COALESCE(e.title, e.description) IS NULL OR COALESCE(e.title, e.description) = '' 
            THEN 'LOW'
        ELSE 'LOW'
    END                               AS confidence,
    CASE
        WHEN LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%salary%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%payroll%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%工资%'
            THEN 'AUTO_BACKFILL'
        WHEN LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%rent%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%房租%'
            THEN 'AUTO_BACKFILL'
        WHEN LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%utilit%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%electric%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%水电%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%网费%'
            THEN 'AUTO_BACKFILL'
        WHEN LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%teaching%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%教材%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%教具%'
            THEN 'AUTO_BACKFILL'
        WHEN LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%market%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%宣传%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%广告%'
            THEN 'AUTO_BACKFILL'
        WHEN LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%maintenance%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%repair%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%维修%'
            THEN 'AUTO_BACKFILL'
        WHEN LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%transport%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%bus%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%car%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%交通%'
            THEN 'AUTO_BACKFILL'
        WHEN LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%office%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%stationery%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%办公%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%文具%'
            THEN 'AUTO_BACKFILL'
        ELSE 'MANUAL_REVIEW'
    END                               AS action,
    CASE
        WHEN LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%salary%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%payroll%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%工资%'
            THEN 'Name matches Salary'
        WHEN LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%rent%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%房租%'
            THEN 'Name matches Rent'
        WHEN LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%utilit%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%electric%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%水电%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%网费%'
            THEN 'Name matches Utilities'
        WHEN LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%teaching%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%教材%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%教具%'
            THEN 'Name matches Teaching Materials'
        WHEN LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%market%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%宣传%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%广告%'
            THEN 'Name matches Marketing'
        WHEN LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%maintenance%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%repair%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%维修%'
            THEN 'Name matches Maintenance'
        WHEN LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%transport%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%bus%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%car%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%交通%'
            THEN 'Name matches Transportation'
        WHEN LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%office%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%stationery%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%办公%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%文具%'
            THEN 'Name matches Office Supplies'
        ELSE 'No keyword match - needs manual review'
    END                               AS reason
FROM expenses e
LEFT JOIN expense_categories ec ON ec.id = e.category_id AND ec.deleted_at IS NULL
WHERE e.finance_category_id IS NULL
  AND e.school_id = (SELECT id FROM schools WHERE code = 'SCH202615' LIMIT 1)
  AND e.deleted_at IS NULL
ORDER BY 
    CASE
        WHEN LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%salary%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%payroll%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%工资%' THEN 1
        WHEN LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%rent%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%房租%' THEN 2
        WHEN LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%utilit%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%electric%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%水电%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%网费%' THEN 3
        WHEN LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%teaching%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%教材%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%教具%' THEN 4
        WHEN LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%market%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%宣传%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%广告%' THEN 5
        WHEN LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%maintenance%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%repair%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%维修%' THEN 6
        WHEN LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%transport%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%bus%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%car%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%交通%' THEN 7
        WHEN LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%office%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%stationery%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%办公%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%文具%' THEN 8
        ELSE 99
    END,
    e.date DESC;
```

### 6.2 统计查询

```sql
-- 统计：Expenses 按置信度分组
SELECT 
    CASE
        WHEN LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%salary%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%payroll%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%工资%' THEN 'AUTO:Salary'
        WHEN LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%rent%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%房租%' THEN 'AUTO:Rent'
        WHEN LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%utilit%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%electric%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%水电%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%网费%' THEN 'AUTO:Utilities'
        WHEN LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%teaching%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%教材%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%教具%' THEN 'AUTO:Teaching Materials'
        WHEN LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%market%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%宣传%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%广告%' THEN 'AUTO:Marketing'
        WHEN LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%maintenance%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%repair%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%维修%' THEN 'AUTO:Maintenance'
        WHEN LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%transport%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%bus%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%car%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%交通%' THEN 'AUTO:Transportation'
        WHEN LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%office%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%stationery%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%办公%'
          OR LOWER(CONCAT(COALESCE(e.title,''), ' ', COALESCE(e.description,''))) LIKE '%文具%' THEN 'AUTO:Office Supplies'
        ELSE 'MANUAL_REVIEW'
    END AS proposed_action,
    COUNT(*) AS expense_count,
    SUM(COALESCE(e.amount_mmk, e.amount)) AS total_amount
FROM expenses e
WHERE e.finance_category_id IS NULL
  AND e.school_id = (SELECT id FROM schools WHERE code = 'SCH202615' LIMIT 1)
  AND e.deleted_at IS NULL
GROUP BY proposed_action
ORDER BY proposed_action;
```

---

## 7. 自动匹配规则边界分析

### 7.1 已知可能的经验教训

`fix_finance_categories.php` 的匹配逻辑已在 Phase 3 执行过一次。以下是该脚本可能覆盖和未覆盖的场景：

| 场景 | 脚本覆盖 | 说明 |
|------|---------|------|
| Tuition Fee / 学费 | ✅ 已匹配 | HIGH 置信度 |
| Registration Fee / 报名费 | ✅ 已匹配 | HIGH 置信度 |
| Material / Book / 教材 | ✅ 已匹配 | HIGH 置信度 |
| Uniform / 校服 | ✅ 已匹配 | HIGH 置信度 |
| Activity / Camp / 活动 | ✅ 已匹配 | HIGH 置信度 |
| Exam / HSK / 考试 | ✅ 已匹配 | HIGH 置信度 |
| Transport / 校车 | ✅ 已匹配 | HIGH 置信度 |
| Other / Misc / 其他 | ❌ 跳过了 | LOW 置信度，需手动 |
| 名称包含 "fee" 但不明确 | ❌ 跳过了 | 如 "General Fee"、"Admin Fee" |
| Payroll expense | ✅ 已匹配 | HIGH 置信度（如果有 Salary 分类） |
| 空名称 / NULL 名称 | ❌ 跳过了 | 无法匹配 |
| 已回填的记录 | ✅ 正确跳过 | `finance_category_id IS NOT NULL` 跳过 |

### 7.2 跨界场景处理

某些名称可能同时匹配 Income 和 Expense 关键词。这是安全的，因为：
- Fee Items 查询只搜索 `fees_class_types`（必然是 income）
- Expenses 查询只搜索 `expenses`（必然是 expense）
- 两边不会混淆

---

## 8. Auto Backfill Candidate 汇总

### 8.1 自动回填条件

以下条件必须**全部满足**：

1. `finance_category_id IS NULL`
2. 名称匹配 HIGH 置信度规则
3. 目标 `finance_category` 在 `finance_categories` 表中存在且 `is_active = true`
4. 目标 `finance_category.type` 匹配（income 或 expense）
5. 记录未被软删除（`deleted_at IS NULL`）

### 8.2 自动回填影响估算

**Fee Items（预估）：**

| 分类 | 预计自动匹配 | 备注 |
|------|-------------|------|
| Tuition Fee | 绝大部分 | 学校必有学费 |
| Registration Fee | 部分 | 取决于是否单独计报名费 |
| Material Fee | 部分 | Book/Material Fee |
| Uniform Fee | 部分 | 如有校服收费 |
| Activity Fee | 部分 | 如有活动/夏令营收费 |
| Exam Fee | 部分 | 如有 HSK/考试收费 |
| Transportation Fee | 部分 | 如有校车收费 |

**Expenses（预估）：**

| 分类 | 预计自动匹配 | 备注 |
|------|-------------|------|
| Salary | 大部分工资支出 | 如名称含 "Salary" 或 "工资" |
| Rent | 部分 | 如名称含 "Rent" 或 "房租" |
| Utilities | 部分 | 如名称含 "Electric" 或 "水电" |
| Teaching Materials | 部分 | 如名称含 "Teaching" 或 "教材" |
| Marketing | 部分 | 如名称含 "Marketing" 或 "宣传" |
| Maintenance | 部分 | 如名称含 "Repair" 或 "维修" |
| Transportation | 部分 | 如名称含 "Transport" 或 "交通" |
| Office Supplies | 部分 | 如名称含 "Office" 或 "办公" |

> **注意：** 以上为基于代码逻辑的结构化预估。实际数字取决于数据库中的数据。执行服务器上的 SELECT 统计查询后可获得精确数字。

---

## 9. Manual Review Required 汇总

### 9.1 需要人工确认的 Fee Items

| 类型 | 示例 | 原因 |
|------|------|------|
| 名称含 "Other" / "Misc" / "其他" | "Other Fee", "其他费用" | 无法从名称判断用途 |
| 名称含 "General" / "Admin" / "Service" | "General Fee", "Service Charge" | 歧义，可能是多种用途 |
| 名称中无可识别关键词 | 自定义名称 | 需要业务人员确认 |
| 同一 fee 多个 fees_class_types | 某 Fee 下有 Tuition + Material 两个 fee type | 需要确认分类粒度 |

### 9.2 需要人工确认的 Expenses

| 类型 | 示例 | 原因 |
|------|------|------|
| 名称含 "Other" / "Misc" / "其他" | "Other Expense", "杂项" | 无法从名称判断用途 |
| 空 title / description | NULL 或空字符串 | 无信息可匹配 |
| Payroll 模块产生的 expense | 通过 Payroll 创建的记录 | 可能已有特殊分类逻辑 |
| Transportation 模块产生的 expense | 通过 Transportation 创建的记录 | 可能已有特殊分类逻辑 |
| 打包费用（多项并一） | "Rent + Utilities Package" | 需要拆分或选主要分类 |

### 9.3 人工确认流程建议

1. 将 MANUAL_REVIEW 列表导出为 Excel
2. 由财务人员标注每条的实际分类
3. 标注后通过管理后台逐一设置
4. 或编写专门的手动回填脚本

---

## 10. Skip 汇总

以下记录在任何情况下都应**跳过**，不回填：

| 条件 | 原因 |
|------|------|
| `finance_category_id IS NOT NULL` | 已有分类，不可覆盖 |
| `deleted_at IS NOT NULL` | 软删除记录，不应修改 |
| 目标 `finance_category` 不存在 | 必须先创建分类，手动处理后才能回填 |
| 非 SCH202615 学校 | 本次仅限测试学校 |
| `school_id` 不匹配 | 数据隔离 |

---

## 11. 安全回填原则

### 11.1 执行前必须做的事

1. **备份相关表**：
   ```sql
   -- 备份 SCH202615 的 fees_class_types
   CREATE TABLE fees_class_types_backup_20260613 AS
   SELECT * FROM fees_class_types 
   WHERE id IN (
       SELECT fct.id FROM fees_class_types fct
       JOIN classes c ON c.id = fct.class_id
       WHERE c.school_id = (SELECT id FROM schools WHERE code = 'SCH202615')
   );
   
   -- 备份 SCH202615 的 expenses
   CREATE TABLE expenses_backup_20260613 AS
   SELECT * FROM expenses 
   WHERE school_id = (SELECT id FROM schools WHERE code = 'SCH202615');
   ```

2. **先执行 Dry Run SELECT**（本文档第 5、6 节的查询），确认每一条匹配结果

3. **人工审查 LOW/MEDIUM 置信度项**

4. **只用一条事务执行**：
   ```sql
   START TRANSACTION;
   
   -- 回填 fees_class_types
   UPDATE fees_class_types fct
   JOIN fees_types ft ON ft.id = fct.fees_type_id
   JOIN classes c ON c.id = fct.class_id
   SET fct.finance_category_id = (
       SELECT id FROM finance_categories WHERE name = 'Tuition Fee' LIMIT 1
   )
   WHERE fct.finance_category_id IS NULL
     AND fct.deleted_at IS NULL
     AND c.school_id = (SELECT id FROM schools WHERE code = 'SCH202615')
     AND (LOWER(ft.name) LIKE '%tuition%' OR LOWER(ft.name) LIKE '%学费%')
     AND EXISTS (SELECT 1 FROM finance_categories WHERE name = 'Tuition Fee');
   
   -- ... 其他分类同理 ...
   
   -- 验证后再 COMMIT
   COMMIT;
   ```

### 11.2 必须遵守的铁律

| # | 规则 | 为什么 |
|---|------|--------|
| 1 | **只更新 `finance_category_id IS NULL` 的记录** | 不覆盖已有分类 |
| 2 | **限定 `school_id = SCH202615`** | 不影响其他学校 |
| 3 | **限定 `deleted_at IS NULL`** | 不修改软删除数据 |
| 4 | **不修改 amount / status / student_id / fees_id / date** | 这些字段与分类无关 |
| 5 | **同一分类名称不存在时跳过** | 避免无效外键引用 |
| 6 | **只用一条 SQL UPDATE（每个分类），不用逐行循环** | 减少锁定时间 |
| 7 | **在一条事务中执行所有 UPDATE** | 原子性保证 |
| 8 | **执行后运行验证 SELECT** | 确认更新数量和正确性 |
| 9 | **执行后不要立即部署** | 先验证 Finance Report 显示正确 |

### 11.3 回填后验证清单

回填后必须在服务器上执行以下验证：

1. **Finance Report 验证**：访问 `/finance-report`，确认 Uncategorized 金额减少（或归零）
2. **Dashboard 验证**：访问 `/finance-dashboard`，确认 Category Breakdown 中所有收入/支出正确归类
3. **金额一致性验证**：
   ```sql
   -- 验证回填前后的 Total Income 不变
   SELECT SUM(amount) FROM compulsory_fees 
   WHERE status='Success' AND school_id=(SELECT id FROM schools WHERE code='SCH202615')
   UNION ALL
   SELECT SUM(amount) FROM optional_fees 
   WHERE status='Success' AND school_id=(SELECT id FROM schools WHERE code='SCH202615');
   ```
4. **更新记录统计**：
   ```sql
   -- 统计各分类更新了多少条
   SELECT fc.name, COUNT(*) as updated_count
   FROM fees_class_types fct
   JOIN finance_categories fc ON fc.id = fct.finance_category_id
   JOIN classes c ON c.id = fct.class_id
   WHERE c.school_id = (SELECT id FROM schools WHERE code = 'SCH202615')
     AND fct.updated_at >= NOW() - INTERVAL 10 MINUTE
   GROUP BY fc.name;
   ```

---

## 12. 需要新增 Finance Category 的判断

如果 Dry Run SELECT 查询发现以下情况，说明需要先新增 Finance Category：

| 场景 | 处理方式 |
|------|---------|
| 存在费用类型名称如 "Library Fee" 但没有对应 Finance Category | 先在后台新增 "Library Fee" 分类 |
| 存在费用类型名称如 "Sports Fee" 但没有对应 Finance Category | 先在后台新增 "Sports Fee" 分类 |
| Expense 有 "Insurance" 但无对应分类 | 先在后台新增 "Insurance" 分类 |
| 任何无法归入现有 17 个分类的费用/支出 | 先在后台新增，再分配 |

**新增分类的安全方式：**
- 通过管理后台 UI 创建（推荐）
- 或通过 INSERT：
  ```sql
  INSERT INTO finance_categories (type, category_code, name, local_name, is_active, sort_order, created_at, updated_at)
  VALUES ('income', 'LIBRARY_FEE', 'Library Fee', '图书费', 1, 10, NOW(), NOW());
  ```

---

## 13. 风险点

| 风险 | 概率 | 影响 | 缓解措施 |
|------|------|------|---------|
| 自动匹配错误分类 | 低 | 报表分类标签错误，金额不变 | HIGH 置信度规则已经过严格关键词匹配；可先 手工抽查 5-10 条再批量执行 |
| UPDATE 覆盖已有分类 | 极低 | 已有分类被错误覆盖 | 强制 `WHERE finance_category_id IS NULL` |
| 事务中断导致部分更新 | 低 | 数据不一致 | 所有 UPDATE 在一个事务中执行；失败则 ROLLBACK |
| 新产生 orphan 外键 | 极低 | Finance Report 显示 Uncategorized | 只关联已存在的 finance_category |
| Payroll 模块特定行为 | 低 | Payroll expense 可能需特殊处理 | Skip Payroll 相关的 expense，标注 MANUAL_REVIEW |
| 回填后历史报表变化 | 低 | 历史数据分类标签变化 | 金额不变，只是分类更清晰 |

---

## 14. 下一步建议

### 14.1 本次 Dry Run 后

1. 在**服务器**上执行本文档第 5、6 节的 Dry Run SELECT 查询
2. 将输出粘贴到本报告对应章节下，生成完整版报告
3. 由财务人员审查 MANUAL_REVIEW 部分

### 14.2 未来真正执行时

1. 先在 SCH202615 测试环境执行
2. 测试环境验证通过后再推生产
3. 用 `fix_finance_categories.php` 脚本或本文档的 UPDATE SQL 执行回填
4. 回填后验证 Finance Report、Dashboard、Outstanding Fees、Student Ledger

### 14.3 长期治理

- 新增 Fee Item 时表单强制要求选择 `finance_category_id`
- 新增 Expense 时表单强制要求选择 `finance_category_id`
- 后台可展示未分类数量告警

---

## 附录 A：一键 Dry Run 汇总脚本

以下 PHP 脚本可在服务器上执行，生成完整的 Dry Run 汇总表格（SELECT ONLY）：

```php
<?php
/**
 * Finance Category Backfill Dry Run
 * Usage: php dry_run_backfill.php
 * 特点：只 SELECT，不 UPDATE/INSERT/DELETE
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\FeesClassType;
use App\Models\FinanceCategory;
use App\Models\Expense;
use App\Models\School;
use Illuminate\Support\Facades\DB;

echo "========================================\n";
echo "Finance Category Backfill DRY RUN\n";
echo "No writes will be performed.\n";
echo "========================================\n\n";

// ---- 1. Find school ----
$school = School::where('code', 'SCH202615')->first();
if (!$school) {
    echo "ERROR: SCH202615 not found!\n";
    exit(1);
}
$schoolId = $school->id;
echo "School: {$school->name} (ID={$schoolId})\n\n";

// ---- 2. Available Finance Categories ----
echo "=== Available Finance Categories ===\n";
$cats = FinanceCategory::orderBy('type')->orderBy('sort_order')->get();
$catMapByName = []; // name => id
$incomeCats = [];
$expenseCats = [];
foreach ($cats as $cat) {
    $catMapByName[$cat->name] = $cat->id;
    if ($cat->type === 'income') $incomeCats[$cat->name] = $cat->id;
    if ($cat->type === 'expense') $expenseCats[$cat->name] = $cat->id;
    echo "  [{$cat->type}] ID={$cat->id} | {$cat->name} ({$cat->local_name}) | active={$cat->is_active}\n";
}

// ---- 3. Matching functions ----
function matchIncomeCategory($name) {
    $name = strtolower($name ?? '');
    if (str_contains($name, 'tuition') || str_contains($name, '学费')) return ['Tuition Fee', 'HIGH'];
    if (str_contains($name, 'registration') || str_contains($name, '报名') || str_contains($name, '注册')) return ['Registration Fee', 'HIGH'];
    if (str_contains($name, 'material') || str_contains($name, 'book') || str_contains($name, '教材') || str_contains($name, '课本') || str_contains($name, '书本')) return ['Material Fee', 'HIGH'];
    if (str_contains($name, 'uniform') || str_contains($name, '校服')) return ['Uniform Fee', 'HIGH'];
    if (str_contains($name, 'activity') || str_contains($name, 'camp') || str_contains($name, '活动') || str_contains($name, '研学') || str_contains($name, '夏令营')) return ['Activity Fee', 'HIGH'];
    if (str_contains($name, 'exam') || str_contains($name, 'hsk') || str_contains($name, '考试')) return ['Exam Fee', 'HIGH'];
    if (str_contains($name, 'transport') || str_contains($name, 'bus') || str_contains($name, '校车')) return ['Transportation Fee', 'HIGH'];
    if (str_contains($name, 'other') || str_contains($name, 'misc') || str_contains($name, '其他')) return [null, 'LOW'];
    if (str_contains($name, 'fee')) return [null, 'MEDIUM'];
    return [null, 'LOW'];
}

function matchExpenseCategory($title, $desc) {
    $text = strtolower(($title ?? '') . ' ' . ($desc ?? ''));
    if (str_contains($text, 'salary') || str_contains($text, 'payroll') || str_contains($text, '工资') || str_contains($text, '薪资')) return ['Salary', 'HIGH'];
    if (str_contains($text, 'rent') || str_contains($text, '房租')) return ['Rent', 'HIGH'];
    if (str_contains($text, 'utilit') || str_contains($text, 'electric') || str_contains($text, '水电') || str_contains($text, '网费')) return ['Utilities', 'HIGH'];
    if (str_contains($text, 'teaching') || str_contains($text, '教材') || str_contains($text, '教具')) return ['Teaching Materials', 'HIGH'];
    if (str_contains($text, 'market') || str_contains($text, '宣传') || str_contains($text, '广告')) return ['Marketing', 'HIGH'];
    if (str_contains($text, 'maintenance') || str_contains($text, 'repair') || str_contains($text, '维修')) return ['Maintenance', 'HIGH'];
    if (str_contains($text, 'transport') || str_contains($text, 'bus') || str_contains($text, 'car') || str_contains($text, '交通')) return ['Transportation', 'HIGH'];
    if (str_contains($text, 'office') || str_contains($text, 'stationery') || str_contains($text, '办公') || str_contains($text, '文具')) return ['Office Supplies', 'HIGH'];
    if (str_contains($text, 'other') || str_contains($text, 'misc') || str_contains($text, '其他')) return [null, 'LOW'];
    if (empty(trim($text))) return [null, 'LOW'];
    return [null, 'LOW'];
}

// ---- 4. Fee Items Dry Run ----
echo "\n=== Fee Items Dry Run ===\n";
$feeItems = FeesClassType::with(['fees_type', 'class'])
    ->whereNull('finance_category_id')
    ->whereHas('class', fn($q) => $q->where('school_id', $schoolId))
    ->get();

$autoFees = 0;
$manualFees = 0;
echo "Total uncategorized fee items: " . $feeItems->count() . "\n\n";

printf("%-6s %-25s %-15s %-12s %-10s %-20s %-10s %-15s\n",
    'FCT_ID', 'Name', 'Nature', 'Amount', 'Conf', 'Suggested', 'Action', 'Reason');
echo str_repeat('-', 130) . "\n";

foreach ($feeItems as $fct) {
    $name = $fct->fees_type_name ?? 'N/A';
    $nature = $fct->optional ? 'Optional' : 'Compulsory';
    $amount = $fct->fee_amount_mmk > 0 ? $fct->fee_amount_mmk : $fct->amount;
    
    [$suggested, $conf] = matchIncomeCategory($name);
    
    if ($suggested && isset($catMapByName[$suggested])) {
        $action = 'AUTO_BACKFILL';
        $reason = "Name matches {$suggested}";
        $autoFees++;
        
        printf("%-6d %-25s %-15s %-12s %-10s %-20s %-10s %-15s\n",
            $fct->id, substr($name, 0, 25), $nature, number_format($amount),
            $conf, $suggested, 'AUTO', substr($reason, 0, 60));
    } elseif ($suggested && !isset($catMapByName[$suggested])) {
        $action = 'MANUAL_REVIEW';
        $reason = "Category '{$suggested}' NOT FOUND - create first";
        $manualFees++;
        
        printf("%-6d %-25s %-15s %-12s %-10s %-20s %-10s %-15s\n",
            $fct->id, substr($name, 0, 25), $nature, number_format($amount),
            $conf, $suggested ?: 'n/a', 'MANUAL', substr($reason, 0, 60));
    } else {
        $action = 'MANUAL_REVIEW';
        $reason = "No keyword match - needs review";
        $manualFees++;
        
        printf("%-6d %-25s %-15s %-12s %-10s %-20s %-10s %-15s\n",
            $fct->id, substr($name, 0, 25), $nature, number_format($amount),
            $conf, 'n/a', 'MANUAL', $reason);
    }
}

echo "\nFee Items Summary: AUTO={$autoFees}, MANUAL={$manualFees}\n";

// ---- 5. Expenses Dry Run ----
echo "\n=== Expenses Dry Run ===\n";
$expenses = Expense::where('school_id', $schoolId)
    ->whereNull('finance_category_id')
    ->get();

$autoExp = 0;
$manualExp = 0;
echo "Total uncategorized expenses: " . $expenses->count() . "\n\n";

printf("%-6s %-30s %-12s %-10s %-20s %-10s %-15s\n",
    'EXP_ID', 'Title/Desc', 'Amount', 'Conf', 'Suggested', 'Action', 'Reason');
echo str_repeat('-', 110) . "\n";

foreach ($expenses as $exp) {
    $title = $exp->title ?? $exp->description ?? 'N/A';
    $amount = $exp->amount_mmk > 0 ? $exp->amount_mmk : $exp->amount;
    
    [$suggested, $conf] = matchExpenseCategory($exp->title, $exp->description);
    
    if ($suggested && isset($catMapByName[$suggested])) {
        $action = 'AUTO_BACKFILL';
        $reason = "Name matches {$suggested}";
        $autoExp++;
        
        printf("%-6d %-30s %-12s %-10s %-20s %-10s %-15s\n",
            $exp->id, substr($title, 0, 30), number_format($amount),
            $conf, $suggested, 'AUTO', substr($reason, 0, 60));
    } elseif ($suggested && !isset($catMapByName[$suggested])) {
        $action = 'MANUAL_REVIEW';
        $reason = "Category '{$suggested}' NOT FOUND";
        $manualExp++;
        
        printf("%-6d %-30s %-12s %-10s %-20s %-10s %-15s\n",
            $exp->id, substr($title, 0, 30), number_format($amount),
            $conf, $suggested ?: 'n/a', 'MANUAL', substr($reason, 0, 60));
    } else {
        $action = 'MANUAL_REVIEW';
        $reason = "No keyword match";
        $manualExp++;
        
        printf("%-6d %-30s %-12s %-10s %-20s %-10s %-15s\n",
            $exp->id, substr($title, 0, 30), number_format($amount),
            $conf, 'n/a', 'MANUAL', $reason);
    }
}

echo "\nExpenses Summary: AUTO={$autoExp}, MANUAL={$manualExp}\n";

// ---- 6. Final Summary ----
echo "\n========================================\n";
echo "DRY RUN SUMMARY\n";
echo "========================================\n";
echo "Fee Items:  AUTO={$autoFees} | MANUAL={$manualFees}\n";
echo "Expenses:   AUTO={$autoExp} | MANUAL={$manualExp}\n";
echo "Total:      AUTO=" . ($autoFees + $autoExp) . " | MANUAL=" . ($manualFees + $manualExp) . "\n";
echo "\nNOTE: This was a READ-ONLY dry run. No data was modified.\n";
echo "To execute, review MANUAL_REVIEW items first, then run the backfill.\n";
```

---

## 附录 B：expense_categories 到 finance_categories 映射参考

如果某些 expense 有旧分类（`expense_categories`）但没有新分类（`finance_categories`），以下映射可供参考：

| 旧分类名称（expense_categories） | 推测的 Finance Category（expense） |
|--------------------------------|----------------------------------|
| 工资 / Salary / Payroll | Salary |
| 房租 / Rent | Rent |
| 水电 / Utilities / Electricity | Utilities |
| 教材 / Teaching / Books | Teaching Materials |
| 宣传 / Marketing / Advertisement | Marketing |
| 维修 / Maintenance / Repair | Maintenance |
| 交通 / Transportation / Bus | Transportation |
| 办公 / Office / Stationery | Office Supplies |
| 其他 / Misc / Other | Other Expenses → MANUAL_REVIEW |

> 该映射可在手动审查模糊 expense 时作为辅助参考。

---

*本报告为 Dry Run 方案，仅包含 SELECT 查询建议和安全回填策略。未执行任何 INSERT/UPDATE/DELETE 操作。*
