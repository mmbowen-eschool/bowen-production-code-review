# Laravel 10 + MySQL 数据库设计（MVP）

> 目标：支撑多个舞蹈室（SaaS 多租户）的 Regular 班固定课表、Private Lesson 预约、签到与课包扣减，并为后续扩展预留空间。  
> 约定：主键统一 `BIGINT UNSIGNED` 自增；统一 `created_at`/`updated_at`；金额统一 `DECIMAL(12,2)`；时间统一使用 Studio 的 `timezone` 进行展示，DB 存储建议使用 UTC（实现层处理）。

## 关键设计点（与 PRD 对齐）

- 多租户：除 `studios` 外，大部分业务表包含 `studio_id`，并建立索引以便按租户过滤。
- Regular 班：以“每周固定课表”存储（`day_of_week + start_time/end_time`），并可设置生效日期范围。
- Private Booking：以具体日期时间段存储（`start_at/end_at`），支持申请/确认/取消/完成状态机。
- 签到与扣课：只有产生 `attendance_records` 且 `status = present` 时，才生成 `package_transactions` 扣课记录。
- 冲突防止（教室/老师同时间重复）：MySQL 无法用单一唯一索引完全阻止“时间区间重叠”，需“应用层事务校验 + 行级锁”实现；DB 层提供强索引与“同一开始时间唯一”约束来降低误差与并发风险（详见 `private_bookings` 的索引与说明）。

---

## 1) studios

### 字段
| 字段 | 类型 | 说明 |
|---|---|---|
| id | BIGINT UNSIGNED PK AI | 主键 |
| name | VARCHAR(120) | 舞蹈室名称 |
| timezone | VARCHAR(64) | 时区（如 `Asia/Yangon`） |
| currency | CHAR(3) | 币种（如 `MMK`） |
| phone | VARCHAR(40) NULL | 联系电话 |
| address | VARCHAR(255) NULL | 地址 |
| is_active | TINYINT(1) | 是否启用 |
| created_at | TIMESTAMP | 创建时间 |
| updated_at | TIMESTAMP | 更新时间 |

### 主要索引
- `PRIMARY KEY (id)`
- `INDEX studios_is_active (is_active)`

### 表之间关系
- `studios 1 - N rooms`
- `studios 1 - N users`
- `studios 1 - N students`
- `studios 1 - N class_types / package_types / regular_classes / private_bookings / attendance_records / student_packages / package_transactions / payments`

---

## 2) rooms

### 字段
| 字段 | 类型 | 说明 |
|---|---|---|
| id | BIGINT UNSIGNED PK AI | 主键 |
| studio_id | BIGINT UNSIGNED FK | 所属舞蹈室 |
| name | VARCHAR(80) | 教室名称（不写死数量） |
| capacity | SMALLINT UNSIGNED NULL | 容量（可选） |
| is_active | TINYINT(1) | 是否可用 |
| created_at | TIMESTAMP | 创建时间 |
| updated_at | TIMESTAMP | 更新时间 |

### 主要索引
- `PRIMARY KEY (id)`
- `INDEX rooms_studio_id (studio_id)`
- `UNIQUE rooms_studio_name_unique (studio_id, name)`

### 表之间关系
- `rooms N - 1 studios`
- `rooms 1 - N regular_classes`
- `rooms 1 - N private_bookings`

---

## 3) users

> 用于后台员工账号（Admin / Front Desk / Teacher）。学生不放在 `users`，单独用 `students`。

### 字段
| 字段 | 类型 | 说明 |
|---|---|---|
| id | BIGINT UNSIGNED PK AI | 主键 |
| studio_id | BIGINT UNSIGNED FK | 所属舞蹈室（MVP 先 1 用户归属 1 舞蹈室） |
| name | VARCHAR(120) | 姓名 |
| email | VARCHAR(191) NULL | 邮箱（可选） |
| phone | VARCHAR(40) NULL | 手机（可选） |
| password | VARCHAR(255) | 密码哈希 |
| role | ENUM('admin','front_desk','teacher') | 角色 |
| is_active | TINYINT(1) | 是否启用 |
| last_login_at | DATETIME NULL | 最近登录 |
| remember_token | VARCHAR(100) NULL | Laravel 记住我 |
| created_at | TIMESTAMP | 创建时间 |
| updated_at | TIMESTAMP | 更新时间 |

### 主要索引
- `PRIMARY KEY (id)`
- `INDEX users_studio_role (studio_id, role)`
- `INDEX users_studio_is_active (studio_id, is_active)`
- 可选唯一（按你的业务要求决定）：`UNIQUE users_studio_email_unique (studio_id, email)`、`UNIQUE users_studio_phone_unique (studio_id, phone)`

### 表之间关系
- `users N - 1 studios`
- `users 1 - 1 teachers`（当 `role = teacher` 时）
- `users 1 - N private_bookings`（request/confirm/cancel 操作人）
- `users 1 - N attendance_records`（签到操作人）
- `users 1 - N package_transactions`（手工调整操作人）

---

## 4) teachers

> Teacher 资料扩展表，和 `users` 一对一。避免把老师字段塞进 `users`。

### 字段
| 字段 | 类型 | 说明 |
|---|---|---|
| id | BIGINT UNSIGNED PK AI | 主键 |
| studio_id | BIGINT UNSIGNED FK | 所属舞蹈室（冗余，便于按租户过滤与外键一致性） |
| user_id | BIGINT UNSIGNED FK | 对应 `users.id` |
| display_name | VARCHAR(120) NULL | 对外显示名（可选） |
| bio | TEXT NULL | 简介（可选） |
| is_active | TINYINT(1) | 是否启用 |
| created_at | TIMESTAMP | 创建时间 |
| updated_at | TIMESTAMP | 更新时间 |

### 主要索引
- `PRIMARY KEY (id)`
- `UNIQUE teachers_user_unique (user_id)`
- `INDEX teachers_studio_is_active (studio_id, is_active)`

### 表之间关系
- `teachers N - 1 studios`
- `teachers 1 - 1 users`
- `teachers 1 - N regular_classes`
- `teachers 1 - N private_bookings`

---

## 5) students

### 字段
| 字段 | 类型 | 说明 |
|---|---|---|
| id | BIGINT UNSIGNED PK AI | 主键 |
| studio_id | BIGINT UNSIGNED FK | 所属舞蹈室 |
| name | VARCHAR(120) | 学生姓名 |
| phone | VARCHAR(40) NULL | 电话（可选） |
| notes | TEXT NULL | 备注（可选） |
| is_active | TINYINT(1) | 是否启用 |
| created_at | TIMESTAMP | 创建时间 |
| updated_at | TIMESTAMP | 更新时间 |

### 主要索引
- `PRIMARY KEY (id)`
- `INDEX students_studio_name (studio_id, name)`
- 可选唯一（按业务要求）：`UNIQUE students_studio_phone_unique (studio_id, phone)`

### 表之间关系
- `students N - 1 studios`
- `students 1 - N private_bookings`
- `students 1 - N student_packages`
- `students 1 - N payments`
- `students 1 - N package_transactions`

---

## 6) class_types

> 课程类型不写死，可由 Admin 配置。既可用于 Regular，也可用于 Private（例如不同舞种/不同课型/不同默认时长与扣课单位）。

### 字段
| 字段 | 类型 | 说明 |
|---|---|---|
| id | BIGINT UNSIGNED PK AI | 主键 |
| studio_id | BIGINT UNSIGNED FK | 所属舞蹈室 |
| name | VARCHAR(120) | 课型名称（如 HipHop / K-Pop / Private 60min） |
| kind | ENUM('regular','private','both') | 适用范围 |
| default_duration_minutes | SMALLINT UNSIGNED NULL | 默认时长（分钟，可选） |
| default_deduct_units | SMALLINT UNSIGNED | 默认扣课单位（MVP 建议 = 1） |
| is_active | TINYINT(1) | 是否启用 |
| created_at | TIMESTAMP | 创建时间 |
| updated_at | TIMESTAMP | 更新时间 |

### 主要索引
- `PRIMARY KEY (id)`
- `INDEX class_types_studio_kind (studio_id, kind)`
- `UNIQUE class_types_studio_name_unique (studio_id, name)`

### 表之间关系
- `class_types N - 1 studios`
- `class_types 1 - N regular_classes`
- `class_types 1 - N private_bookings`

---

## 7) package_types

> 课包类型（4/8/12 堂等）不写死，可配置价格与有效期。

### 字段
| 字段 | 类型 | 说明 |
|---|---|---|
| id | BIGINT UNSIGNED PK AI | 主键 |
| studio_id | BIGINT UNSIGNED FK | 所属舞蹈室 |
| name | VARCHAR(120) | 课包名称（如 Private 8 Sessions） |
| lessons_count | SMALLINT UNSIGNED | 总堂数（如 4/8/12） |
| validity_days | SMALLINT UNSIGNED NULL | 有效期天数（NULL 表示不限制） |
| price | DECIMAL(12,2) | 售价 |
| currency | CHAR(3) | 币种（默认与 studio 一致，也可单独记录） |
| is_active | TINYINT(1) | 是否启用 |
| created_at | TIMESTAMP | 创建时间 |
| updated_at | TIMESTAMP | 更新时间 |

### 主要索引
- `PRIMARY KEY (id)`
- `INDEX package_types_studio_is_active (studio_id, is_active)`
- `UNIQUE package_types_studio_name_unique (studio_id, name)`

### 表之间关系
- `package_types N - 1 studios`
- `package_types 1 - N student_packages`

---

## 8) student_packages

> 学生购买后的课包实例（有余额/到期日）。扣课只针对该表实例，流水记录在 `package_transactions`。

### 字段
| 字段 | 类型 | 说明 |
|---|---|---|
| id | BIGINT UNSIGNED PK AI | 主键 |
| studio_id | BIGINT UNSIGNED FK | 所属舞蹈室 |
| student_id | BIGINT UNSIGNED FK | 学生 |
| package_type_id | BIGINT UNSIGNED FK | 课包类型 |
| payment_id | BIGINT UNSIGNED NULL FK | 对应付款（MVP 可为空，允许后补） |
| purchased_at | DATETIME | 购买时间 |
| expires_at | DATETIME NULL | 到期时间（基于 validity_days 计算，可为空） |
| total_units | INT UNSIGNED | 总堂数（通常等于 package_types.lessons_count，做快照） |
| remaining_units | INT UNSIGNED | 剩余堂数 |
| status | ENUM('active','expired','void') | 状态 |
| notes | TEXT NULL | 备注 |
| created_at | TIMESTAMP | 创建时间 |
| updated_at | TIMESTAMP | 更新时间 |

### 主要索引
- `PRIMARY KEY (id)`
- `INDEX student_packages_student_active (studio_id, student_id, status, expires_at)`
- `INDEX student_packages_expires (studio_id, expires_at)`

### 表之间关系
- `student_packages N - 1 studios`
- `student_packages N - 1 students`
- `student_packages N - 1 package_types`
- `student_packages N - 1 payments`（可选）
- `student_packages 1 - N package_transactions`

---

## 9) regular_classes

> 每周固定课表。用于占用教室资源并参与 Private Booking 冲突计算。

### 字段
| 字段 | 类型 | 说明 |
|---|---|---|
| id | BIGINT UNSIGNED PK AI | 主键 |
| studio_id | BIGINT UNSIGNED FK | 所属舞蹈室 |
| room_id | BIGINT UNSIGNED FK | 教室 |
| teacher_id | BIGINT UNSIGNED NULL FK | 老师（可选） |
| class_type_id | BIGINT UNSIGNED FK | 课型 |
| day_of_week | TINYINT UNSIGNED | 1-7（建议 1=Mon…7=Sun） |
| start_time | TIME | 开始时间 |
| end_time | TIME | 结束时间 |
| starts_on | DATE NULL | 生效开始日期（NULL 表示立即/一直） |
| ends_on | DATE NULL | 生效结束日期（NULL 表示长期） |
| is_active | TINYINT(1) | 是否启用 |
| notes | TEXT NULL | 备注 |
| created_at | TIMESTAMP | 创建时间 |
| updated_at | TIMESTAMP | 更新时间 |

### 主要索引
- `PRIMARY KEY (id)`
- `INDEX regular_classes_room_time (studio_id, room_id, day_of_week, start_time, end_time)`
- `INDEX regular_classes_teacher_time (studio_id, teacher_id, day_of_week, start_time, end_time)`
- `INDEX regular_classes_effective (studio_id, starts_on, ends_on, is_active)`

### 表之间关系
- `regular_classes N - 1 studios`
- `regular_classes N - 1 rooms`
- `regular_classes N - 1 teachers`（可选）
- `regular_classes N - 1 class_types`

---

## 10) private_bookings

> 私教预约：指定日期、时间、教室、老师、学生。  
> 并发防冲突建议：在“确认（Confirmed）”动作中使用事务 + 冲突查询 + `SELECT ... FOR UPDATE`（或对目标 room/teacher 的相关记录加锁），确保高并发下不产生重叠。

### 字段
| 字段 | 类型 | 说明 |
|---|---|---|
| id | BIGINT UNSIGNED PK AI | 主键 |
| studio_id | BIGINT UNSIGNED FK | 所属舞蹈室 |
| student_id | BIGINT UNSIGNED FK | 学生 |
| teacher_id | BIGINT UNSIGNED FK | 老师 |
| room_id | BIGINT UNSIGNED FK | 教室 |
| class_type_id | BIGINT UNSIGNED NULL FK | 课型（可选） |
| start_at | DATETIME | 开始时间（具体日期） |
| end_at | DATETIME | 结束时间 |
| status | ENUM('pending','confirmed','rejected','cancelled','completed') | 状态 |
| requested_by_user_id | BIGINT UNSIGNED FK | 发起人（Teacher/Front Desk） |
| confirmed_by_user_id | BIGINT UNSIGNED NULL FK | 确认人（Front Desk/Admin） |
| cancelled_by_user_id | BIGINT UNSIGNED NULL FK | 取消人 |
| cancelled_at | DATETIME NULL | 取消时间 |
| rejection_reason | VARCHAR(255) NULL | 拒绝原因（可选） |
| notes | TEXT NULL | 备注 |
| created_at | TIMESTAMP | 创建时间 |
| updated_at | TIMESTAMP | 更新时间 |

### 主要索引
- `PRIMARY KEY (id)`
- 基础查询：
  - `INDEX private_bookings_student_time (studio_id, student_id, start_at)`
  - `INDEX private_bookings_teacher_time (studio_id, teacher_id, start_at, end_at)`
  - `INDEX private_bookings_room_time (studio_id, room_id, start_at, end_at)`
  - `INDEX private_bookings_status_time (studio_id, status, start_at)`
- 并发降风险（只能阻止“同一开始时间”，无法阻止“时间区间重叠”）：
  - `UNIQUE private_bookings_room_start_unique (studio_id, room_id, start_at)`
  - `UNIQUE private_bookings_teacher_start_unique (studio_id, teacher_id, start_at)`
- 约束建议：
  - `CHECK (end_at > start_at)`（MySQL 8+）

### 表之间关系
- `private_bookings N - 1 studios`
- `private_bookings N - 1 students`
- `private_bookings N - 1 teachers`
- `private_bookings N - 1 rooms`
- `private_bookings N - 1 class_types`（可选）
- `private_bookings N - 1 users`（requested/confirmed/cancelled 操作人）
- `private_bookings 1 - 0..1 attendance_records`（每个私教最多一条签到记录）

---

## 11) attendance_records

> 签到记录：仅当 `status = present` 时触发扣课（生成 `package_transactions`）。  
> `private_bookings.status = cancelled` 的预约不应产生 `present` 签到（实现层限制）。

### 字段
| 字段 | 类型 | 说明 |
|---|---|---|
| id | BIGINT UNSIGNED PK AI | 主键 |
| studio_id | BIGINT UNSIGNED FK | 所属舞蹈室 |
| private_booking_id | BIGINT UNSIGNED FK | 对应私教预约 |
| student_id | BIGINT UNSIGNED FK | 学生（冗余快照，便于查询） |
| teacher_id | BIGINT UNSIGNED FK | 老师（冗余快照，便于查询） |
| room_id | BIGINT UNSIGNED FK | 教室（冗余快照，便于查询） |
| status | ENUM('present','absent','no_show') | 出勤状态（MVP 重点是 present） |
| checked_in_at | DATETIME | 签到时间 |
| checked_in_by_user_id | BIGINT UNSIGNED FK | 签到人（Teacher/Front Desk） |
| voided_at | DATETIME NULL | 撤销签到时间 |
| voided_by_user_id | BIGINT UNSIGNED NULL FK | 撤销人 |
| notes | TEXT NULL | 备注 |
| created_at | TIMESTAMP | 创建时间 |
| updated_at | TIMESTAMP | 更新时间 |

### 主要索引
- `PRIMARY KEY (id)`
- `UNIQUE attendance_private_booking_unique (private_booking_id)`（一个预约最多一个签到）
- `INDEX attendance_studio_status_time (studio_id, status, checked_in_at)`
- `INDEX attendance_student_time (studio_id, student_id, checked_in_at)`

### 表之间关系
- `attendance_records N - 1 studios`
- `attendance_records N - 1 private_bookings`
- `attendance_records N - 1 students`
- `attendance_records N - 1 teachers`
- `attendance_records N - 1 rooms`
- `attendance_records N - 1 users`（checked_in/voided 操作人）
- `attendance_records 1 - N package_transactions`（通常 present 会对应 1 条 deduction；撤销会对应 1 条 void_deduction）

---

## 12) package_transactions

> 课包流水台账（强烈建议采用“只增不改”的 ledger 思路）：购买/扣课/撤销扣课/手工调整都记录在此。  
> 余额最终以 `student_packages.remaining_units` 为准（高频读），流水用于审计与对账。

### 字段
| 字段 | 类型 | 说明 |
|---|---|---|
| id | BIGINT UNSIGNED PK AI | 主键 |
| studio_id | BIGINT UNSIGNED FK | 所属舞蹈室 |
| student_id | BIGINT UNSIGNED FK | 学生 |
| student_package_id | BIGINT UNSIGNED FK | 影响到的课包实例 |
| type | ENUM('purchase','deduction','void_deduction','adjustment') | 流水类型 |
| units_delta | INT | 变动堂数：购买/回滚为正，扣课为负 |
| balance_after | INT UNSIGNED | 变动后该课包剩余堂数（快照） |
| occurred_at | DATETIME | 发生时间 |
| attendance_record_id | BIGINT UNSIGNED NULL FK | 来源：签到（扣课/撤销扣课） |
| payment_id | BIGINT UNSIGNED NULL FK | 来源：付款（购买） |
| created_by_user_id | BIGINT UNSIGNED NULL FK | 操作人（手工调整/补录） |
| notes | TEXT NULL | 备注 |
| created_at | TIMESTAMP | 创建时间 |
| updated_at | TIMESTAMP | 更新时间 |

### 主要索引
- `PRIMARY KEY (id)`
- `INDEX pkg_tx_student_time (studio_id, student_id, occurred_at)`
- `INDEX pkg_tx_package_time (studio_id, student_package_id, occurred_at)`
- `INDEX pkg_tx_type_time (studio_id, type, occurred_at)`
- `UNIQUE pkg_tx_deduction_unique (attendance_record_id, type)`（可选：避免同一签到重复写 deduction/void_deduction；实现时需注意 NULL 行为）

### 表之间关系
- `package_transactions N - 1 studios`
- `package_transactions N - 1 students`
- `package_transactions N - 1 student_packages`
- `package_transactions N - 1 attendance_records`（可选）
- `package_transactions N - 1 payments`（可选）
- `package_transactions N - 1 users`（可选）

---

## 13) payments

> 付款记录（MVP 可先做“后台记录”而非线上支付）。用于关联课包购买与对账。

### 字段
| 字段 | 类型 | 说明 |
|---|---|---|
| id | BIGINT UNSIGNED PK AI | 主键 |
| studio_id | BIGINT UNSIGNED FK | 所属舞蹈室 |
| student_id | BIGINT UNSIGNED FK | 学生 |
| amount | DECIMAL(12,2) | 金额 |
| currency | CHAR(3) | 币种 |
| method | ENUM('cash','bank_transfer','card','other') | 支付方式（可扩展） |
| status | ENUM('pending','paid','void','refunded') | 支付状态 |
| paid_at | DATETIME NULL | 支付时间（paid 才有） |
| reference | VARCHAR(120) NULL | 外部/内部参考号（收据号、转账单号等） |
| notes | TEXT NULL | 备注 |
| created_at | TIMESTAMP | 创建时间 |
| updated_at | TIMESTAMP | 更新时间 |

### 主要索引
- `PRIMARY KEY (id)`
- `INDEX payments_student_time (studio_id, student_id, paid_at)`
- `INDEX payments_status_time (studio_id, status, created_at)`
- `UNIQUE payments_studio_reference_unique (studio_id, reference)`（可选：若 reference 用作收据号）

### 表之间关系
- `payments N - 1 studios`
- `payments N - 1 students`
- `payments 1 - N student_packages`（一次付款可产生多个课包实例：例如买了两种包）
- `payments 1 - N package_transactions`

---

## 冲突防止实现建议（与索引配套）

> 这部分是“如何用 MySQL 落地防冲突”的最低实现建议，避免只靠索引导致漏判。

- 教室冲突（同一 `room_id` 同时间段重叠）：在确认私教（把 `status` 置为 `confirmed`）的事务中执行重叠查询：  
  - 条件：同 studio + 同 room + `status in ('confirmed')` + `start_at < :new_end AND end_at > :new_start`
- 老师冲突（同一 `teacher_id` 同时间段重叠）：同理查询 teacher 维度。
- 并发处理：在事务内对命中的记录加锁（`FOR UPDATE`），或对“room_id/teacher_id 的资源记录”做可控锁定（实现层）。  
- DB 约束兜底：`UNIQUE (studio_id, room_id, start_at)` 与 `UNIQUE (studio_id, teacher_id, start_at)` 只能阻止“完全相同 start_at 的重复确认”，不能替代重叠查询。

