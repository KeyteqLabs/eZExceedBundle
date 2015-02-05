<?php

/**
 * Sanitizing data for eZ Exceed's Pencil Twig function
 *
 * @copyright //autogen//
 * @license //autogen//
 * @version //autogen//
 *
 */

namespace KTQ\Bundle\eZExceedBundle\Model;

use eZ\Publish\API\Repository\ContentService;
use eZ\Publish\API\Repository\ContentTypeService;
use eZ\Publish\API\Repository\LanguageService;
use eZ\Publish\API\Repository\LocationService;
use eZ\Publish\API\Repository\Values\Content\Content;
use eZ\Publish\API\Repository\Values\Content\Location;
use eZ\Publish\Core\MVC\Symfony\Templating\Twig\Extension\ContentExtension;
use eZ\Publish\Core\SignalSlot\Repository;
use eZ\Publish\Core\FieldType\Page\Parts\Page;
use eZ\Publish\Core\FieldType\Page\Parts\Block;
use ezexceed\models\content\Object as eZExceedObject;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

class Pencil
{
    /** @var Content */
    protected $currentContent;

    /** @var int */
    protected $currentContentId;

    /** @var array */
    protected $entities = array();

    /** @var string */
    protected $title = '';

    /** @var Page */
    protected $pageField;

    /** @var int */
    protected $zoneIndex = 0;

    /** @var Block */
    protected $block;

    /** @var boolean */
    protected $canCurrentUserEditCurrentContent;

    // Services below.

    /** @var Repository */
    protected $repository;

    /** @var ContentService */
    protected $contentService;

    /** @var ContentTypeService */
    protected $contentTypeService;

    /** @var LocationService */
    protected $locationService;

    /** @var PageService */
    protected $pageService;

    /** @var LanguageService */
    protected $languageService;

    /** @var Request */
    protected $request;

    /** @var ContentExtension  */
    protected $contentExtension;

    public function __construct(ContainerInterface $container)
    {
        $this->repository = $container->get('ezpublish.api.repository');
        $this->contentService = $this->repository->getContentService();
        $this->contentTypeService = $this->repository->getContentTypeService();
        $this->locationService = $this->repository->getLocationService();
        $this->languageService = $this->repository->getContentLanguageService();
        $this->pageService = $container->get('ezpublish.fieldType.ezpage.pageService');
        $this->contentExtension = $container->get('ezpublish.twig.extension.content');
        $this->request = $container->get('request');
    }

    protected function reset()
    {
        $this->currentContent = null;
        $this->currentContentId = null;
        $this->entities = array();
        $this->title = '';
        $this->pageField = null;
        $this->zoneIndex = 0;
        $this->block = null;
        $this->canCurrentUserEditCurrentContent = null;;
    }

    protected function loadContent($contentId = false, $locationId = false)
    {
        $locationId = $locationId ?: $this->request->attributes->get('locationId');
        if ($contentId) {
            $content = $this->contentService->loadContent($contentId);
        }
        elseif ($locationId) {
            $location = $this->locationService->loadLocation($locationId);
            $content = $this->contentService->loadContent($location->contentId);
        }
        else {
            return;
        }

        $this->currentContent = $content;
        $this->currentContentId = $content->id;
        $this->canCurrentUserEditCurrentContent = $this->repository->canUser('content', 'edit', $content);
    }

    public function fill($input, $contentId = false, $locationId = false)
    {
        // Can't set scope to prototype, this method prevents state from leaking between calls.
        $this->reset();

        if (!$contentId && $input instanceof Content) {
            $contentId = $input->id;
        }
        elseif (!$locationId && $input instanceof Location) {
            $contentId = $input->contentId;
        }

        $this->loadContent($contentId, $locationId);

        $this->entities = array();
        if (is_array($input)) {
            foreach ($input as $key => $value) {
                // Arrays of blocks are not supported
                if ($value instanceof Block) {
                    return false;
                }

                // Array contains Contents or Locations
                if (is_numeric($key)) {
                    if ($this->isPencilCompatible($value)) {
                        $this->addEntity($value);
                    }
                } else {
                    // Array is a set of Ids.
                    $this->addIdArray($value, $key);
                }
            }
        }
        elseif ($this->repository->canUser('content', 'edit', $this->currentContent)) {
            $this->addEntity($input);
        }

        return true;
    }

    protected function addEntity($value)
    {
        switch (true) {
            case $value instanceof Content:
                $this->addContent($value);
                break;
            case $value instanceof Location:
                $this->addLocation($value);
                break;
            case $value instanceof Block:
                $this->addBlock($value);
        }
    }

    protected function addContent(Content $content)
    {
        if ($this->repository->canUser('content', 'edit', $content)) {
            $contentVersionInfo = $content->getVersionInfo();
            $contentTypeIdentifier = $this->contentTypeService->loadContentType($contentVersionInfo->contentInfo->contentTypeId)->identifier;

            $name = $this->contentExtension->getTranslatedContentName($content);

            $entity = array(
                'id' => $content->__get('id'),
                'separator' => false,
                'name' => $name,
                'classIdentifier' => $contentTypeIdentifier
            );
            $this->entities[] = $entity;
        }
    }

    protected function addLocation(Location $location)
    {
        if ($this->repository->canUser('content', 'read', $location->getContentInfo(), $location)) {
            $this->addContent($this->contentService->loadContentByContentInfo($location->getContentInfo()));
        }
    }

    protected function addBlock(Block $block)
    {
        $this->pageField = $this->getPageField();
        $this->block = $block;
        $this->setZoneIndex();

        $blockItems = $this->pageService->getValidBlockItems($block);
        if ($blockItems) {
            $locationIdMapper = function ($blockItem) {
                return $blockItem->locationId;
            };
            $locationIdList = array_map($locationIdMapper, $blockItems);

            // Use 'nodes' and not 'locations' to remain compatible
            $this->addIdArray($locationIdList, 'nodes');
        }

        $waitingBlockItems = $this->pageService->getWaitingBlockItems($block);
        if ($waitingBlockItems) {
            $this->addSeparator('Content in queue');

            $contentIdMapper = function ($blockItem) {
                return $blockItem->contentId;
            };
            $contentIdList = array_map($contentIdMapper, $waitingBlockItems);

            // Use 'objects' and not 'contents' to remain compatible
            $this->addIdArray($contentIdList, 'objects');
        }
    }

    protected function addIdArray($values, $type)
    {
        $this->title = \ezpI18n::tr('ezexceed', 'Edit ' . $type);

        if ($type === 'objects') {
            foreach ($values as $contentId) {
                $this->addContent($this->contentService->loadContent($contentId));
            }
        } elseif ($type === 'nodes') {
            foreach ($values as $locationId) {
                $this->addLocation($this->locationService->loadLocation($locationId));
            }
        }
    }

    protected function isPencilCompatible($input)
    {
        return ($input instanceof Block || $input instanceof Content || $input instanceof Location);
    }

    protected function setZoneIndex()
    {
        if (!$this->pageField) {
            return false;
        }

        foreach ($this->pageField->zones as $zoneIndex => $zone) {
            foreach ($zone->blocks as $block) {
                if ($block->id === $this->block->id) {
                    $this->zoneIndex = $zoneIndex;
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param Content $content
     *
     * @return Page
     */
    protected function getPageField(Content $content = null)
    {
        $content = $content ?: $this->currentContent;
        $fields = $content->getFields();

        foreach ($fields as $field) {
            $field = $field->value;

            if (property_exists($field, 'page') && $field->page instanceof Page)
                return $field->page;
        }

        return null;
    }

    protected function addSeparator($title = '')
    {
        $this->entities[] = array(
            'separator' => true,
            'title' => $title
        );
    }

    public function attribute($attribute)
    {
        return $this->$attribute;
    }
}
