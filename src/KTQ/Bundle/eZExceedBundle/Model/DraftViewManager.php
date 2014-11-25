<?php

/**
 * @author Henning Kvinnesland <henning@keyteq.no>
 * @since 17.10.14
 */

namespace KTQ\Bundle\eZExceedBundle\Model;

use eZ\Bundle\EzPublishCoreBundle\View\Manager;
use eZ\Publish\API\Repository\Values\Content\Content;
use eZ\Publish\API\Repository\Values\Content\ContentInfo;
use eZ\Publish\API\Repository\Values\Content\Location;
use eZ\Publish\API\Repository\Values\Content\VersionInfo;
use eZ\Publish\Core\FieldType\Page\Value;
use eZ\Publish\Core\MVC\Symfony\SiteAccess;
use eZ\Publish\Core\MVC\Symfony\View\ContentViewInterface;
use eZ\Publish\Core\MVC\Symfony\View\ViewManagerInterface;
use eZ\Publish\Core\Repository\Repository;
use RuntimeException;

class DraftViewManager extends Manager
{
    /** @var Repository */
    protected $repository;

    public function renderLocation(Location $location, $viewType = ViewManagerInterface::VIEW_TYPE_FULL, $parameters = array())
    {
        foreach ($this->getAllLocationViewProviders() as $viewProvider) {
            $view = $viewProvider->getView($location, $viewType);
            if ($view instanceof ContentViewInterface) {
                $parameters['location'] = $location;
                $parameters['content'] = $this->loadUserDraft($location->contentInfo);

                return $this->renderContentView($view, $parameters);
            }
        }

        throw new RuntimeException( "Unable to find a view for location #$location->id" );
    }

    /**
     * @param VersionInfo $a
     * @param VersionInfo $b
     *
     * @return int
     */
    protected function compareDrafts(VersionInfo $a, VersionInfo $b)
    {
        $aVersion = $a->versionNo;
        $bVersion = $b->versionNo;

        if ($aVersion == $bVersion) {
            return 0;
        }

        return $aVersion > $bVersion ? -1 : 1;
    }

    protected function cacheBlocks(Content $content)
    {
        $pageService = \ezpKernel::instance()->getServiceContainer()->get('ezpublish.fieldtype.ezpage.pageservice');

        foreach ($content->getFields() as $field) {
            if (!$field->value instanceof Value) {
                continue;
            }

            foreach ($field->value->page->zones as $zone) {
                foreach ($zone->blocks as $block) {
                    $pageService->cacheBlock($block);
                }
            }
        }
    }

    protected function loadUserDraft(ContentInfo $contentInfo)
    {
        $languages = $this->configResolver->getParameter('languages');
        $displayMode = $this->configResolver->getParameter('VersionManagement.DisplayMode', 'content');
        $contentService = $this->repository->getContentService();

        $content = $contentService->loadContentByContentInfo($contentInfo, $languages);

        $currentUser = $this->repository->getCurrentUser();
        $anonymousUserId = $this->configResolver->getParameter('UserSettings.AnonymousUserID');
        $isAnonymous = $currentUser->id == $anonymousUserId;

        if ($displayMode != 'latest' || $isAnonymous) {
            return $content;
        }

        try {
            $drafts = $contentService->loadContentDrafts($this->repository->getCurrentUser());
            usort($drafts, array($this, 'compareDrafts'));
            foreach ($drafts as $draft) {
                // Wrong object
                if ($draft->contentInfo->id != $contentInfo->id) {
                    continue;
                }

                // Wrong language
                if (!in_array($draft->initialLanguageCode, $languages)) {
                    continue;
                }

                // Too old
                if ($draft->versionNo < $contentInfo->currentVersionNo) {
                    continue;
                }

                $content = $contentService->loadContentByVersionInfo($draft);
                $this->cacheBlocks($content);

                break;
            }
        }
        catch (\Exception $e) {
            return $content;
        }

        return $content;
    }

    public function renderContent(Content $content, $viewType = ViewManagerInterface::VIEW_TYPE_FULL, $parameters = array())
    {
        $contentInfo = $content->getVersionInfo()->getContentInfo();
        foreach ($this->getAllContentViewProviders() as $viewProvider) {
            $view = $viewProvider->getView($contentInfo, $viewType);
            if ($view instanceof ContentViewInterface) {
                $parameters['content'] = $this->loadUserDraft($contentInfo);

                return $this->renderContentView($view, $parameters);
            }
        }

        throw new RuntimeException( "Unable to find a template for #$contentInfo->id" );
    }
}
