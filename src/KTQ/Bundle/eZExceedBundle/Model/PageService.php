<?php

/**
 * @author Henning Kvinnesland <henning@keyteq.no>
 * @since 21.11.14
 */

namespace KTQ\Bundle\eZExceedBundle\Model;

use eZ\Bundle\EzPublishLegacyBundle\FieldType\Page\PageService as BasePageService;
use eZ\Publish\Core\FieldType\Page\Parts\Block;

class PageService extends BasePageService
{
    public function cacheBlock(Block $block)
    {
        $this->blocksById[$block->id] = $block;
        /** @noinspection PhpIllegalArrayKeyTypeInspection Spl-storage voodoo! */
        $this->validBlockItems[$block] = $block->items;
    }
}