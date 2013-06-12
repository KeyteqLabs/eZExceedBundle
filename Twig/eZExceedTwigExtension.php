<?php

namespace KTQ\Bundle\eZExceedBundle\Twig;

use Twig_Extension;
use Twig_Function_Method;
//use Twig_Filter_Method;
use Twig_Environment;
use Twig_Template;
use Symfony\Component\Templating\EngineInterface;
use Symfony\Component\HttpFoundation\Request;
use KTQ\Bundle\eZExceedBundle\Model\Pencil;
use eZ\Bundle\EzPublishLegacyBundle\DependencyInjection\Configuration\LegacyConfigResolver;
use eZ\Publish\Core\Repository\Repository;
use eZ\Publish\Core\FieldType\Page\PageService;
use eZ\Publish\Core\Repository\Values\Content\Content;

class eZExceedTwigExtension extends Twig_Extension
{
    protected $pageService;
    protected $legacyConfigResolver;
    protected $templateEngine;
    protected $pencil;

    public function __construct(
        PageService $pageService,
        LegacyConfigResolver $legacyConfigResolver,
        EngineInterface $templateEngine,
        Pencil $pencil)
    {
        $this->pageService = $pageService;
        $this->legacyConfigResolver = $legacyConfigResolver;
        $this->templateEngine = $templateEngine;

        $this->pencil = $pencil;
    }

    public function getFunctions()
    {
        return array(
            'pencil' => new Twig_Function_Method( $this, 'eZExceedPencil' ),
            'ini' => new Twig_Function_Method( $this, 'getIniSetting' )
        );
    }

    /*
    public function getFilters()
    {
        return array(
            'translate' => new Twig_Filter_Method( $this, 'translate' )
        );
    }
    */

    public function getName()
    {
        return 'ktq_ezexceed';
    }

    /**
     * Renders eZ Exceed's pencil when provided with an eZ Flow block object,
     * a location object or a collection of such or
     * a content object or a collection of such
     *
     * @param mixed $input An eZ Flow block object, a content object or a collection of such
     * @return string The HTML markup
     */

    public function eZExceedPencil( $input, Content $currentContent )
    {
        $this->pencil->fill( $input, $currentContent );

        // Mapping stuff up manually as Twig can’t handle the entire $pencil object
        $parameters = array(
            'pencil' => array(
                'title' => $this->pencil->attribute('title'),
                'entities' => $this->pencil->attribute('entities'),
                'page' => array(
                    'field' => $this->pencil->attribute('pageField'),
                    'zone' => array(
                        'index' => $this->pencil->attribute('zoneIndex')
                    ),
                    'block' => array(
                        'id' => $this->pencil->attribute('block')->id,
                        'name' => trim( $this->pencil->attribute('block')->name ),
                        'type' => $this->pencil->attribute('block')->type,
                        'data' => $this->pencil->attribute('block')
                    )
                ),
                'content' => array(
                    'id' => $this->pencil->attribute('currentContentId'),
                    'data' => $this->pencil->attribute('currentContent'),
                    'canedit' => $this->pencil->attribute('canCurrentUserEditCurrentContent')
                )
            )
        );

        echo $this->templateEngine->render( 'KTQeZExceedBundle:particle:pencil.html.twig', $parameters );
    }

    public function getIniSetting( $name, $section, $file )
    {
        $file = str_replace( array('.ini.append.php', '.ini' ), '', $file );

        return $this->legacyConfigResolver->getParameter( $section . '.' . $name, $file );
    }

    /*
    public function translate( string $string, string $context )
    {
        // return translated string
    }
    */
}
