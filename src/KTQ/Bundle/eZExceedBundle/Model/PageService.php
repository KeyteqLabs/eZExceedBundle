<?php

/**
 * @author Henning Kvinnesland <henning@keyteq.no>
 * @since 21.11.14
 */

namespace KTQ\Bundle\eZExceedBundle\Model;

use eZ\Bundle\EzPublishLegacyBundle\FieldType\Page\PageService as BasePageService;
use eZ\Publish\Core\MVC\Symfony\Siteaccess;
use eZ\Publish\Core\Base\Exceptions\NotFoundException;
use eZ\Publish\Core\FieldType\Page\Parts\Block;
use eZ\Publish\Core\FieldType\Page\Parts\Item;
use Symfony\Component\DependencyInjection\ContainerInterface;

class PageService extends BasePageService
{
    /** @var ContainerInterface */
    protected $container;

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
        // The ordering will be in reverse now, lets fix it.
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

    public function setContainer(ContainerInterface $container) { $this->container = $container; }

    public function loadBlock($id)
    {
        try {
            return parent::loadBlock($id);
        // Try to do a reverse-fetch using the url provided by the simplified-request.
        } catch (NotFoundException $e) {
            $siteaccess = $this->container->get('ezpublish.siteaccess');
            /** @noinspection PhpUndefinedMethodInspection */
            $simplifiedRequest = $siteaccess->matcher->getRequest();
            $match = $this->container->get('router')->match($simplifiedRequest->pathinfo);
            if (isset($match['locationId'])) {
                $location = $this->container->get('ezpublish.api.repository')->getLocationService()->loadLocation($match['locationId']);
                $this->container->get('ezpublish.view_manager')->loadUserDraft($location->contentInfo);
            }

            // Lets try again and ultimately fail if we were unable to fetch the draft-block.
            return parent::loadBlock($id);
        }
    }
}
