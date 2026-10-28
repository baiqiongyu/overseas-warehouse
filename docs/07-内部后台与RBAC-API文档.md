
## 产品管理（后台，2026-10-22）

> 与货主门户 SKU 功能一致，多一个「所属公司」维度。路由前缀 `/admin/product`，全部挂 AdminAuth；
> 写操作需按钮权限 `product:create / update / status / delete / print`。

| 方法 | 路径 | 说明 |
|---|---|---|
| GET | /admin/product/list | 全货主产品列表；`keyword`(sku_code/barcode/品名)、`status`、`customer_id`、`start_time`、`end_time`、分页；行含 `company_name`/`company_code` |
| GET | /admin/product/stats | 全部/启用/停用统计 |
| GET | /admin/product/customers | 所属公司下拉（启用货主：id/code/name） |
| POST | /admin/product/create | 代货主建档；必传 `customer_id`；条码=货主编码-SKU编码；同公司 sku_code 唯一 |
| POST | /admin/product/update | 仅颜色/图片/中英文描述（锁定规则同客户端） |
| POST | /admin/product/status | 启用/停用 |
| POST | /admin/product/delete | 软删 |
| POST | /admin/product/print-labels | 打印标签；一次只能打同一家公司的 SKU（跨公司报错） |

配套：`POST /admin/upload/image`（后台图片上传，AdminAuth）。
菜单：016_admin_product_menu.sql（顶部模块「产品管理」→ 页面「产品列表」+ 5 按钮权限，超管自动补授权）。
已知边界：list/stats/customers 未做页面级权限拦截（同 staff 等现有接口），角色看不到菜单但接口可达；如需收紧再加 perms 校验。

## 仓库管理（后台，2026-10-22）

> 仓库为系统级基础资料（`hw_warehouse`，009 建表），货主门户入库下单时只读选择，后台维护。
> 路由前缀 `/admin/warehouse`，全部挂 AdminAuth；写操作需按钮权限 `warehouse:create / update / status / delete`。

| 方法 | 路径 | 说明 |
|---|---|---|
| GET | /admin/warehouse/list | 分页列表；`keyword`(编码/名称/城市)、`status`；按 id 升序 |
| GET | /admin/warehouse/stats | 全部/启用/停用统计（列表页快捷筛选用） |
| POST | /admin/warehouse/create | 新增；编码必填 `^[A-Z0-9_-]{2,32}$`（自动转大写）且全局唯一；name 必填；country 两位 ISO 可选 |
| POST | /admin/warehouse/update | 编辑；全部字段可改（含编码），编码改后仍需唯一 |
| POST | /admin/warehouse/status | 启用/停用；停用后货主无法创建发往该仓库的入库单（客户端下拉只取 status=1） |
| POST | /admin/warehouse/delete | 软删；**被未删除入库单引用的仓库不可删**（报引用张数，建议停用） |

菜单：017 建独立模块 → 018 移入系统设置 → 020 恢复独立顶栏模块「仓库管理」（为后续库区/库位管理预留，页面「仓库列表」path=warehouse + 4 按钮权限，超管自动补授权）。
前端：pages/admin/warehouse.html（满高表格 + 带数量状态快捷筛选 + 新增/编辑弹窗，国家下拉可搜可自填）。

## 入库单管理（后台，2026-10-22）

> 全货主全局视角的入库单列表，仓库侧的审核入口。路由前缀 `/admin/inbound`，全部挂 AdminAuth；
> 审核需按钮权限 `inbound:approve`。状态机与货主门户一致（7 态）。

| 方法 | 路径 | 说明 |
|---|---|---|
| GET | /admin/inbound/list | 全货主入库单分页；`keyword`(单号/客户单号/柜号/运单号/公司名/编码)、`state`、`warehouse_id`、`customer_id`、`biz_type`、`handling_type`、`date_field`(create/expect)+`date_start`/`date_end`；行含 `company_name/company_code/warehouse_name` 与箱数/SKU 种类/件数摘要 |
| GET | /admin/inbound/stats | 全部 8 态计数（draft/pending/approved/receiving/received/shelved/canceled） |
| GET | /admin/inbound/detail | 只读详情：单头 + 货主信息(contact/phone) + SKU 汇总(应收/实收/已上架) + 箱明细分页瀑布流(box_page/box_limit, has_more) + 附件 + 审核时间 |
| POST | /admin/inbound/approve | 审核通过：待审核 20 → 待入库 30；写 `audit_time`/`audit_by`；仅 20 可审 |

配套变更：
- `sql/022_admin_inbound_menu.sql`：`hw_inbound_order` 加 `audit_time`/`audit_by`；菜单「入库管理」下新增页面「入库单列表」(path=inbound) + 按钮「审核入库单」(inbound:approve)，收货作业排第二；超管补授权
- `app/common/service/InboundBoxes.php`：箱+箱内明细分页查询抽为公共服务，客户端与后台 detail 共用
- 前端 `pages/admin/inbound.html`：满高表格 + 8 态快捷筛选（带数量）+ 公司/仓库/关键词/日期筛选 + 只读详情弹窗（固定高度内部滚动、箱明细滚动到底自动加载）+ 待审核单显示「审核」按钮
- 已知边界：list/stats/detail 未做页面级 perms 拦截（同 staff/product 等现有接口），角色看不到菜单但接口可达；approve 有 perms 强校验
