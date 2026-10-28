-- ============================================================
-- 023：首页 / 工作台 模块重组
--   定位调整：首页 = 概览仪表盘（排第一）；工作台 = 操作性工作模块
--   1) 新增模块「首页」，原「首页概览」页面挂到其下（页面 id 不变，授权无感）
--   2) 「收货作业」页面从「入库管理」挪到「工作台」模块下
--      （入库管理回归单据管理：只保留入库单列表）
--   3) 清理脏菜单 id=27（bad:act，历史测试遗留）
--   4) 新模块「首页」补授权给全部角色
--   幂等：新模块按 title 判断是否已存在
-- ============================================================

-- 1) 新增「首页」模块（排最前）
INSERT INTO `hw_menu` (`pid`, `type`, `title`, `path`, `icon`, `sort`, `create_time`)
SELECT 0, 1, '首页', '', 'el-icon-s-home', 0, UNIX_TIMESTAMP()
WHERE NOT EXISTS (SELECT 1 FROM `hw_menu` WHERE `type` = 1 AND `title` = '首页' AND `is_deleted` = 0);

SET @m_home := (SELECT `id` FROM `hw_menu` WHERE `type` = 1 AND `title` = '首页' AND `is_deleted` = 0 LIMIT 1);

-- 2) 首页概览页面挪到「首页」模块下
UPDATE `hw_menu` SET `pid` = @m_home, `sort` = 1
WHERE `id` = 2 AND `type` = 2 AND `path` = 'dashboard';

-- 3) 收货作业挪到「工作台」（模块 id=1），入库管理只留入库单列表
UPDATE `hw_menu` SET `pid` = 1, `sort` = 1
WHERE `type` = 2 AND `path` = 'receiving';

-- 4) 清理历史脏菜单
UPDATE `hw_menu` SET `is_deleted` = 1 WHERE `id` = 27 AND `path` = 'bad:act';

-- 5) 新「首页」模块补授权给全部角色
INSERT IGNORE INTO `hw_role_menu` (`role_id`, `menu_id`)
SELECT r.`id`, @m_home FROM `hw_role` r WHERE r.`is_deleted` = 0;
