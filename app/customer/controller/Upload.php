<?php
declare(strict_types=1);

namespace app\customer\controller;

use app\common\exception\BizException;
use think\facade\Filesystem;

/**
 * 文件上传（货主门户，需登录）
 * 存储走框架 public 磁盘（public/storage），返回相对 URL，便于以后切 CDN/OSS 只改一处
 */
class Upload extends Base
{
    /** 允许的扩展名与大小上限（5MB） */
    private const EXT = ['jpg', 'jpeg', 'png', 'webp'];
    private const MAX_SIZE = 5242880;

    /** 入库附件：图片 + PDF，上限 10MB */
    private const ATTACH_EXT = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
    private const ATTACH_MAX_SIZE = 10485760;

    /**
     * 上传图片
     * POST /customer/upload/image  (multipart: file)
     * 返回 data.url 为相对路径（如 /storage/sku/xx/yy.jpg），前端直接 <img :src>
     */
    public function image()
    {
        $file = $this->request->file('file');
        if (!$file || !$file->isValid()) {
            throw new BizException('请选择要上传的图片');
        }
        if ($file->getSize() > self::MAX_SIZE) {
            throw new BizException('图片不能超过 5MB');
        }
        $ext = strtolower($file->getOriginalExtension());
        if (!in_array($ext, self::EXT, true)) {
            throw new BizException('仅支持 jpg / png / webp 格式');
        }
        // 双重保险：真实内容必须是图片（防改后缀伪装）
        $info = getimagesize($file->getRealPath());
        if ($info === false) {
            throw new BizException('文件内容不是有效图片');
        }

        $path = Filesystem::disk('public')->putFile('sku', $file);
        if (!$path) {
            throw new BizException('图片保存失败，请重试');
        }

        return $this->ok([
            'url'  => '/storage/' . str_replace('\\', '/', $path),
            'size' => $file->getSize(),
        ], '上传成功');
    }

    /**
     * 上传入库附件（装箱单等：图片 / PDF，≤10MB）
     * POST /customer/upload/attachment  (multipart: file)
     */
    public function attachment()
    {
        $file = $this->request->file('file');
        if (!$file || !$file->isValid()) {
            throw new BizException('请选择要上传的文件');
        }
        if ($file->getSize() > self::ATTACH_MAX_SIZE) {
            throw new BizException('附件不能超过 10MB');
        }
        $ext = strtolower($file->getOriginalExtension());
        if (!in_array($ext, self::ATTACH_EXT, true)) {
            throw new BizException('仅支持 jpg / png / webp / pdf 格式');
        }
        // 图片做真实内容校验；PDF 只查文件头
        if ($ext !== 'pdf') {
            if (getimagesize($file->getRealPath()) === false) {
                throw new BizException('文件内容不是有效图片');
            }
        } elseif (!preg_match('/^%PDF-/', (string) file_get_contents($file->getRealPath(), false, null, 0, 5))) {
            throw new BizException('文件内容不是有效 PDF');
        }

        $path = Filesystem::disk('public')->putFile('inbound', $file);
        if (!$path) {
            throw new BizException('附件保存失败，请重试');
        }

        return $this->ok([
            'url'       => '/storage/' . str_replace('\\', '/', $path),
            'file_name' => $file->getOriginalName(),
            'size'      => $file->getSize(),
        ], '上传成功');
    }
}
