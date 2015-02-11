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
        // Newest modifications will be last in the array. Lets use them.
        $items = $this->checkAddItems(array_reverse($block->items), $items);
        $items = $this->checkAddItems($this->getStorageGateway()->getWaitingBlockItems($block), $items);
        $items = $this->checkAddItems($this->getStorageGateway()->getValidBlockItems($block), $items);
        $items = array_filter($items);
        $items = array_values($items);
        // The ordering will probably be in reverse now, lets fix it.
        usort($items, array($this, 'sortItems'));

        /** @noinspection PhpIllegalArrayKeyTypeInspection Spl-storage voodoo! */
        $this->validBlockItems[$block] = array_values($items);
    }

    protected function sortItems(Item $a, Item $b)
    {
        if ($a->priority == $b->priority) {
            return 0;
        }

        return $a->priority > $b->priority ? -1 : 1;
    }

    /**
     * @param Item[] $items
     * @param Item[] $array
     *
     * @return Item[]
     */
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
