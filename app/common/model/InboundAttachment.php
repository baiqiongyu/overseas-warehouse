<?php
declare(strict_types=1);

namespace app\common\model;

use think\Model;

/**
 * 入库单附件
 */
class InboundAttachment extends Model
{
    protected $name = 'inbound_attachment';

    protected $autoWriteTimestamp = 'int';
}
