-- ============================================================
-- 016：后台产品管理菜单（顶部模块「产品管理」+ 页面 + 按钮权限）
--   * 与客户端 SKU 功能一致，仅多一个「所属公司」维度
--   * 不用固定 ID，用 LAST_INSERT_ID 串接，避免与现有数据冲突
-- ============================================================

-- 顶部模块：产品管理
INSERT INTO `hw_menu` (`pid`, `type`, `title`, `path`, `icon`, `sort`, `create_time`)
VALUES (0, 1, '产品管理', '', 'el-icon-goods', 2, UNIX_TIMESTAMP());
SET @m_product := LAST_INSERT_ID();

-- 页面：产品列表
INSERT INTO `hw_menu` (`pid`, `type`, `title`, `path`, `icon`, `sort`, `create_time`)
VALUES (@m_product, 2, '产品列表', 'product', '', 1, UNIX_TIMESTAMP());
SET @p_product := LAST_INSERT_ID();

-- 按钮权限
INSERT INTO `hw_menu` (`pid`, `type`, `title`, `path`, `icon`, `sort`, `create_time`) VALUES
  (@p_product, 3, '新增产品', 'product:create', '', 1, UNIX_TIMESTAMP()),
  (@p_product, 3, '编辑产品', 'product:update', '', 2, UNIX_TIMESTAMP()),
  (@p_product, 3, '启停产品', 'product:status', '', 3, UNIX_TIMESTAMP()),
  (@p_product, 3, '删除产品', 'product:delete', '', 4, UNIX_TIMESTAMP()),
  (@p_product, 3, '打印标签', 'product:print',  '', 5, UNIX_TIMESTAMP());

-- 超管角色补齐新菜单（is_super 走旁路，此处仍写入一份便于配置页展示）
INSERT IGNORE INTO `hw_role_menu` (`role_id`, `menu_id`)
SELECT 1, `id` FROM `hw_menu`
WHERE `id` IN (@m_product, @p_product)
   OR `pid` IN (@m_product, @p_product);
