# Phase 3.2: Finance Category Backfill Server Dry Run Report

> **检查时间**: 2026-06-13  
> **目标学校**: SCH202615 (Bowen International School)  
> **数据库类型**: MySQL (多租户架构，每学校独立数据库)  
> **操作类型**: SELECT ONLY（只读，未修改任何数据）  
> **Dry Run 脚本**: `finance_category_server_dry_run.php`

---

## 1. 执行摘要

| 指标 | 数值 |
|------|------|
| 可用 Finance Categories | **17 个** (8 Income + 9 Expense) |
| 总 Fee Items (fees_class_types) | **12 条** |
| Fee Items 已有分类 | 3 条 (25.0%) |
| Fee Items 未分类 (NULL) | **9 条** (75.0%) |
| → 可自动回填 | **6 条** |
| → 需要人工确认 | **3 条** |
| Uncategorized Fee Configured Total | **1,880,000.00 MMK** |
| 总 Expenses | **14 条** |
| Expenses 已有分类 | 2 条 (14.3%) |
| Expenses 未分类 (NULL) | **12 条** (85.7%) |
| → 可自动回填 | **9 条** |
| → 需要人工确认 | **3 条** |
| Uncategorized Expenses Total | **1,415,000.00 MMK** |

> **⚠️ 注意**: 以上为本地测试环境数据。生产环境真实数据需在服务器上运行 Dry Run 脚本获取。

---

## 2. 可用 Finance Categories

系统当前有 17 个 Finance Category：

| ID | Name | Type | Sort | Active |
|----|------|------|------|--------|
| 1 | Tuition Fee | income | 1 | Y |
| 2 | Registration Fee | income | 2 | Y |
| 3 | Material Fee | income | 3 | Y |
| 4 | Uniform Fee | income | 4 | Y |
| 5 | Activity Fee | income | 5 | Y |
| 6 | Exam Fee | income | 6 | Y |
| 7 | Transportation Fee | income | 7 | Y |
| 8 | Other Income | income | 99 | Y |
| 9 | Salary | expense | 1 | Y |
| 10 | Rent | expense | 2 | Y |
| 11 | Utilities | expense | 3 | Y |
| 12 | Teaching Materials | expense | 4 | Y |
| 13 | Marketing | expense | 5 | Y |
| 14 | Maintenance | expense | 6 | Y |
| 15 | Transportation | expense | 7 | Y |
| 16 | Office Supplies | expense | 8 | Y |
| 17 | Other Expenses | expense | 99 | Y |

---

## 3. 费用类型字典 (Fees Types)

| ID | Name | Description |
|----|------|-------------|
| 1 | Tuition Fee | Tuition Fee description |
| 2 | Registration Fee | Registration Fee description |
| 3 | Material Fee | Material Fee description |
| 4 | Uniform Fee | Uniform Fee description |
| 5 | Activity Fee | Activity Fee description |
| 6 | Exam Fee | Exam Fee description |
| 7 | Transport Fee | Transport Fee description |
| 8 | Other Fee | Other Fee description |
| 9 | Library Fee | Library Fee description |
| 10 | Sports Fee | Sports Fee description |

---

## 4. 旧 Expense Categories 参考

| ID | Name |
|----|------|
| 1 | Salary |
| 2 | Rent |
| 3 | Utilities |
| 4 | Teaching Materials |
| 5 | Marketing |
| 6 | Maintenance |
| 7 | Transport |
| 8 | Office Supplies |
| 9 | Other |

---

## 5. Uncategorized Income (Fee Items) 统计

### 5.1 总览

| 类别 | 数量 | 金额 (MMK) |
|------|------|-----------|
| 全部 Fee Items | 12 | 3,510,000.00 |
| 已有 Finance Category | 3 | 1,630,000.00 |
| **Finance Category IS NULL** | **9** | **1,880,000.00** |
| → Auto Backfill (High Confidence) | 6 | 1,755,000.00 |
| → Manual Review Required | 3 | 125,000.00 |

### 5.2 Fee Items Dry Run 明细

#### Auto Backfill Candidates (6 条)

| FCT_ID | Fee_Name | Item_Name | Optional | Amount | Class | → Category | Confidence |
|--------|----------|-----------|----------|--------|-------|-------------|------------|
| 4 | 2025-2026 Standard Fees | Tuition Fee | Compulsory | 1,500,000 | Grade 2B | Tuition Fee (ID=1) | high |
| 5 | 2025-2026 Standard Fees | Uniform Fee | Optional | 45,000 | Grade 2B | Uniform Fee (ID=4) | high |
| 6 | 2025-2026 Standard Fees | Registration Fee | Compulsory | 50,000 | Grade 3C | Registration Fee (ID=2) | high |
| 7 | 2025-2026 Standard Fees | Activity Fee | Optional | 60,000 | Grade 3C | Activity Fee (ID=5) | high |
| 8 | 2025-2026 Standard Fees | Exam Fee | Compulsory | 30,000 | Grade 2B | Exam Fee (ID=6) | high |
| 9 | 2025-2026 Standard Fees | Transport Fee | Optional | 120,000 | Grade 3C | Transportation Fee (ID=7) | high |

**匹配规则**: 关键词精确匹配 (Tuition, Uniform, Registration, Activity, Exam, Transport) → 对应 Finance Category

#### Manual Review Required (3 条)

| FCT_ID | Fee_Name | Item_Name | Optional | Amount | Class | Reason |
|--------|----------|-----------|----------|--------|-------|--------|
| 11 | 2025-2026 Standard Fees | Library Fee | Optional | 15,000 | Grade 1A | 名称 "Library Fee" 无匹配规则 → 需要人工分配 |
| 12 | 2025-2026 Standard Fees | Sports Fee | Optional | 35,000 | Grade 2B | 名称 "Sports Fee" 无匹配规则 → 需要人工分配 |
| 10 | 2025-2026 Standard Fees | Other Fee | Optional | 25,000 | Grade 3C | 名称含 "Other" → 模糊分类，需人工确认 |

**建议**:
- Library Fee → 可归入 Material Fee (ID=3) 或 Other Income (ID=8)，需财务人员确认
- Sports Fee → 可归入 Activity Fee (ID=5) 或 Other Income (ID=8)，需财务人员确认
- Other Fee → 建议归入 Other Income (ID=8)

---

## 6. Uncategorized Expenses 统计

### 6.1 总览

| 类别 | 数量 | 金额 (MMK) |
|------|------|-----------|
| 全部 Expenses | 14 | 6,365,000.00 |
| 已有 Finance Category | 2 | 5,000,000.00 |
| **Finance Category IS NULL** | **12** | **1,415,000.00** |
| → Auto Backfill (High Confidence) | 9 | 1,325,000.00 |
| → Manual Review Required | 3 | 90,000.00 |

### 6.2 Expenses Dry Run 明细

#### Auto Backfill Candidates (9 条)

| Exp_ID | Title | Amount | Date | Old Cat | → Category | Confidence |
|--------|-------|--------|------|---------|-------------|------------|
| 3 | Staff Salary Bonus May | 500,000 | 2026-05-15 | Salary (1) | Salary (ID=9) | high |
| 4 | Electricity Bill May | 80,000 | 2026-05-20 | Utilities (3) | Utilities (ID=11) | high |
| 5 | Water Bill June | 15,000 | 2026-06-05 | Utilities (3) | Utilities (ID=11) | high |
| 6 | Textbook Purchase Grade 3 | 250,000 | 2026-05-10 | Teaching Materials (4) | Teaching Materials (ID=12) | high |
| 7 | Facebook Ads Campaign | 100,000 | 2026-05-25 | Marketing (5) | Marketing (ID=13) | high |
| 8 | AC Repair Service | 75,000 | 2026-06-02 | Maintenance (6) | Maintenance (ID=14) | high |
| 9 | Office Stationery Buy | 35,000 | 2026-05-18 | Office Supplies (8) | Office Supplies (ID=16) | high |
| 10 | Bus Fuel May | 120,000 | 2026-05-30 | Transport (7) | Transportation (ID=15) | high |
| 11 | Security Deposit May | 150,000 | 2026-05-03 | Rent (2) | Rent (ID=10) | high |

**匹配规则**: 标题关键词匹配 + 旧 category 回退匹配 → 对应 Finance Category

#### Manual Review Required (3 条)

| Exp_ID | Title | Amount | Date | Old Cat | Reason |
|--------|-------|--------|------|---------|--------|
| 12 | Other Misc Expense | 50,000 | 2026-06-08 | Other (9) | 名称含 "Other"/"Misc" → 需人工确认 |
| 13 | General Admin Cost | 30,000 | 2026-05-12 | Other (9) | 名称含 "General"/"Admin" → 需人工确认 |
| 14 | (empty) | 10,000 | 2026-06-10 | NULL | 空 title + 空 description → 无法自动分类 |

**建议**:
- Other Misc Expense → 建议归入 Other Expenses (ID=17)
- General Admin Cost → 建议归入 Other Expenses (ID=17)
- 空名称 → 需财务人员根据实际用途分配

---

## 7. 按旧 Expense Category 分组

| 旧 Category ID | 旧 Category Name | Total | With Finance | Without Finance |
|----------------|-----------------|-------|-------------|-----------------|
| 1 | Salary | 2 | 1 | 1 |
| 2 | Rent | 2 | 1 | 1 |
| 3 | Utilities | 2 | 0 | 2 |
| 4 | Teaching Materials | 1 | 0 | 1 |
| 5 | Marketing | 1 | 0 | 1 |
| 6 | Maintenance | 1 | 0 | 1 |
| 7 | Transport | 1 | 0 | 1 |
| 8 | Office Supplies | 1 | 0 | 1 |
| 9 | Other | 2 | 0 | 2 |
| NULL | - | 1 | 0 | 1 |

---

## 8. 自动匹配规则

### 8.1 Income (Fee Items) 匹配规则

| 关键词 | → Finance Category | 匹配方式 |
|--------|-------------------|---------|
| Tuition / 学费 | Tuition Fee | 精确关键词 |
| Registration / Enrollment / 报名 / 注册 | Registration Fee | 精确关键词 |
| Material / Book / Textbook / 教材 / 课本 | Material Fee | 精确关键词 |
| Uniform / 校服 | Uniform Fee | 精确关键词 |
| Activity / 活动 / Camp | Activity Fee | 精确关键词 |
| Exam / 考试 / HSK | Exam Fee | 精确关键词 |
| Transport / Bus / 校车 / 交通 | Transportation Fee | 精确关键词 |

### 8.2 Expense 匹配规则

| 关键词 | → Finance Category | 匹配方式 |
|--------|-------------------|---------|
| Salary / Payroll / 工资 / 薪资 | Salary | 精确关键词 + 旧 cat |
| Rent / 租金 / Lease | Rent | 精确关键词 + 旧 cat |
| Utility / Water / Electric / 水电 / 电费 | Utilities | 精确关键词 + 旧 cat |
| Teaching / 教材 / 教具 | Teaching Materials | 精确关键词 + 旧 cat |
| Marketing / Advertis / 宣传 / 广告 | Marketing | 精确关键词 + 旧 cat |
| Maintenance / Repair / 维修 | Maintenance | 精确关键词 + 旧 cat |
| Transport / Bus / Fuel / 交通 / 运输 | Transportation | 精确关键词 + 旧 cat |
| Office / Stationery / 办公 / 文具 | Office Supplies | 精确关键词 + 旧 cat |

### 8.3 不自动匹配的情形

以下情形标记为 `manual_review`：
- 名称含 `Other`、`Misc`、`其他`、`General`、`Admin`、`杂项`
- 空名称 (title 和 description 都为空)
- 无匹配规则且不属于以上模糊情形

---

## 9. 自动回填候选汇总

### Fee Items: 6 条

| ID | 名称 | 目标 Category |
|----|------|---------------|
| 4 | Tuition Fee (Grade 2B) | Tuition Fee (1) |
| 5 | Uniform Fee (Grade 2B) | Uniform Fee (4) |
| 6 | Registration Fee (Grade 3C) | Registration Fee (2) |
| 7 | Activity Fee (Grade 3C) | Activity Fee (5) |
| 8 | Exam Fee (Grade 2B) | Exam Fee (6) |
| 9 | Transport Fee (Grade 3C) | Transportation Fee (7) |

**自动回填金额**: 1,755,000.00 MMK

### Expenses: 9 条

| ID | 标题 | 目标 Category |
|----|------|---------------|
| 3 | Staff Salary Bonus May | Salary (9) |
| 4 | Electricity Bill May | Utilities (11) |
| 5 | Water Bill June | Utilities (11) |
| 6 | Textbook Purchase Grade 3 | Teaching Materials (12) |
| 7 | Facebook Ads Campaign | Marketing (13) |
| 8 | AC Repair Service | Maintenance (14) |
| 9 | Office Stationery Buy | Office Supplies (16) |
| 10 | Bus Fuel May | Transportation (15) |
| 11 | Security Deposit May | Rent (10) |

**自动回填金额**: 1,325,000.00 MMK

---

## 10. 需人工确认汇总

### Fee Items: 3 条

| ID | 名称 | 问题 | 金额 |
|----|------|------|------|
| 11 | Library Fee | 无匹配 Finance Category | 15,000 |
| 12 | Sports Fee | 无匹配 Finance Category | 35,000 |
| 10 | Other Fee | 模糊名称 (Other) | 25,000 |

### Expenses: 3 条

| ID | 标题 | 问题 | 金额 |
|----|------|------|------|
| 12 | Other Misc Expense | 模糊名称 | 50,000 |
| 13 | General Admin Cost | 模糊名称 | 30,000 |
| 14 | (empty) | 空名称 | 10,000 |

---

## 11. 需要新增 Finance Category 的项目

基于当前测试数据，以下项目引用的名称在当前 Finance Category 中不存在：

| 引用名称 | 出现次数 | 建议新建 Category |
|---------|---------|------------------|
| Library Fee | 1 条 | `Library Fee` (income) |
| Sports Fee | 1 条 | `Sports Fee` (income) |

**替代方案**: 将 Library Fee 和 Sports Fee 归入 `Other Income` (ID=8)，无需新建分类。

> **⚠️ 生产环境**: 实际是否需要新建 Finance Category 取决于服务器上的真实数据。如果存在多种特殊费用类型，建议通过管理后台先创建对应的 Finance Category，再执行回填。

---

## 12. 安全回填原则（生产环境执行前必读）

### 前置条件

1. **备份表数据**:
   ```sql
   CREATE TABLE fees_class_types_backup_20260613 AS SELECT * FROM fees_class_types;
   CREATE TABLE expenses_backup_20260613 AS SELECT * FROM expenses;
   ```

2. **只更新 finance_category_id IS NULL 的记录**:
   ```sql
   -- 安全示例（按需调整为具体 school_id）
   UPDATE fees_class_types SET finance_category_id = ? 
   WHERE finance_category_id IS NULL AND id = ?;
   
   UPDATE expenses SET finance_category_id = ? 
   WHERE finance_category_id IS NULL AND id = ?;
   ```

3. **按 school_id 限定范围**: 只操作 SCH202615 对应的 school_id

4. **不覆盖已有 finance_category_id**: 仅更新 `finance_category_id IS NULL` 的记录

5. **禁止修改的字段**: amount, status, student_id, fees_id, date, title, description

6. **不影响付款记录**: compulsory_fees, optional_fees, fees_paids 表不修改

### 执行流程

```
1. Dry Run (本次)        → 生成分类建议
2. 人工确认              → 审查 manual_review 项
3. 备份表                → 创建备份
4. 执行回填              → UPDATE finance_category_id
5. 验证                  → 检查 Finance Report
```

### 回填后验证清单

- [ ] Finance Report → Category Breakdown 中 Uncategorized 减少
- [ ] Total Income 金额不变
- [ ] Net Income 金额不变
- [ ] Outstanding Fees 不变
- [ ] Student Ledger 不变
- [ ] Dashboard 统计正确

---

## 13. Dry Run 脚本使用说明

### 路径
```
finance_category_server_dry_run.php
```

### 部署到服务器执行
```bash
# 1. 将脚本上传到服务器项目根目录
scp finance_category_server_dry_run.php user@server:/path/to/project/

# 2. 在服务器上执行
cd /path/to/project/
php finance_category_server_dry_run.php > dry_run_output.txt

# 3. 查看结果
cat dry_run_output.txt
```

### 脚本安全保证
- ✅ 仅执行 SELECT / COUNT / GROUP BY / LEFT JOIN
- ✅ 不执行 UPDATE / INSERT / DELETE / ALTER / DROP
- ✅ 不修改代码 / Controller / Blade / Routes
- ✅ 不部署
- ✅ 不覆盖已有 finance_category_id

---

## 14. 风险点

| 风险 | 影响 | 缓解措施 |
|------|------|---------|
| 关键词误匹配 | 费用被分配到错误的 Category | 仅自动回填 high confidence 项，medium/low 需人工确认 |
| 新 Finance Category 缺失 | 无法回填某些特殊费用 | 先在管理后台创建缺失分类 |
| 空名称数据 | 无法自动分类 | 标记 manual_review，需财务人员手动补充 |
| Amount 精度丢失 | 多币种换算偏差 | 使用 amount_mmk 列，不修改原始 amount |
| 已有分类被覆盖 | 导致分类混乱 | 只更新 finance_category_id IS NULL 的记录 |

---

## 15. 建议

### 是否建议今天执行真实回填？

**分两步走**:

1. **今天（先做）**: 将 `finance_category_server_dry_run.php` 部署到生产服务器，执行 Dry Run 获取真实数据清单
2. **明天（确认后）**: 
   - 财务人员审查 MANUAL_REVIEW 项
   - 确认是否需要新建 Finance Category (如 Library Fee, Sports Fee)
   - 备份 `fees_class_types` 和 `expenses` 表
   - 使用 `fix_finance_categories.php` 或报告中的 UPDATE SQL 执行回填
   - 验证 Finance Report 显示正确

### 关键决策点
- **Library Fee / Sports Fee** → 归入 Other Income 还是新建独立 Category？
- **Other / General / Admin 类费用** → 归入 Other Expenses 还是具体分类？
- **空名称 Expense** → 财务人员需根据上下文补充名称

---

*报告自动生成于 2026-06-13 | Phase 3.2 Finance Category Backfill Server Dry Run*
