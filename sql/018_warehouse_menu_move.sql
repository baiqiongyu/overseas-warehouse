-- ============================================================
-- 018：仓库列表从独立模块移入「系统设置」
--   * 页面/按钮的 menu id 不变，仅改 pid，角色授权（hw_role_menu）不受影响
-- ============================================================

SET @p_wh := (SELECT `id` FROM `hw_menu` WHERE `type` = 2 AND `path` = 'warehouse' LIMIT 1);
SET @m_setting := (SELECT `id` FROM `hw_menu` WHERE `type` = 1 AND `title` = '系统设置' LIMIT 1);

-- 页面挂到系统设置下，排在菜单管理之后
UPDATE `hw_menu` SET `pid` = @m_setting, `sort` = 4 WHERE `id` = @p_wh;

-- 删除原「仓库管理」顶部模块
DELETE FROM `hw_menu` WHERE `type` = 1 AND `title` = '仓库管理';
