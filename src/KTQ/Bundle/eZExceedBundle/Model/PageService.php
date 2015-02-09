<?php

/**
 * @author Henning Kvinnesland <henning@keyteq.no>
 * @since 21.11.14
 */

namespace KTQ\Bundle\eZExceedBundle\Model;

use eZ\Bundle\EzPublishLegacyBundle\FieldType\Page\PageService as BasePageService;
use eZ\Publish\Core\FieldType\Page\Parts\Block;
use eZ\Publish\Core\FieldType\Page\Parts\Item;

class PageService extends BasePageService
{
    public function cacheBlock(Block $block)
    {
        $this->blocksById[$block->id] = $block;

        $items = array();
        $items = $this->checkAddItems($block->items, $items);
        $items = $this->checkAddItems($this->getStorageGateway()->getWaitingBlockItems($block), $items);
        $items = $this->checkAddItems($this->getStorageGateway()->getValidBlockItems($block), $items);
        $items = array_filter($items);

        /** @noinspection PhpIllegalArrayKeyTypeInspection Spl-storage voodoo! */
        $this->validBlockItems[$block] = array_values($items);
    }

    protected function checkAddItems($items, $array)
    {
        foreach ($items as $item) {
            if ($item->action == 'remove') {
                $array[$item->contentId] = false;
            }
            elseif (!isset($array[$item->contentId])) {
                $array[$item->contentId] = $item;
            }
        }

        return $array;
    }
}
