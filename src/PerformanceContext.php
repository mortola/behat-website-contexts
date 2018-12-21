<?php

namespace MOrtola\BehatSEOContexts;

use Behat\Mink\Element\NodeElement;
use Behat\Mink\Exception\UnsupportedDriverActionException;
use PHPUnit\Framework\Assert;

class PerformanceContext extends BaseContext
{
    const RESOURCE_TYPES = [
        'PNG' => 'png',
        'HTML' => 'html',
        'JPEG' => 'jpeg',
        'GIF' => 'gif',
        'ICO' => 'ico',
        'JAVASCRIPT' => 'js',
        'CSS' => 'css',
        'CSS_INLINE_HEAD' => 'css-inline-head',
        'CSS_LINK_HEAD' => 'css-link-head',
    ];

    /**
     * @param string $host
     * @param string $resourceType
     *
     * @throws UnsupportedDriverActionException
     * @throws \Exception
     *
     * @Then /^browser cache must be enabled for (.+\..+|external|internal) (png|jpeg|gif|ico|js|css) resources$
     */
    public function browserCacheMustBeEnabledForResources($host, $resourceType)
    {
        $this->supportsSymfony(false);
        switch ($host) {
            case 'internal':
                $elements = $this->getPageResources($resourceType, true);
                break;
            case 'external':
                $elements = $this->getPageResources($resourceType, false, $host);
                break;
            default:
                $elements = $this->getPageResources($resourceType, false, $host);
                break;
        }
        $this->checkResourceCache($elements[array_rand($elements)], $resourceType);
        $this->getSession()->back();
    }

    /**
     * @param        $element
     * @param string $resourceType
     *
     * @throws \Exception
     */
    private function checkResourceCache($element, $resourceType)
    {
        $elementUrl = $this->getResourceUrl($element, $resourceType);

        $this->getSession()->visit($elementUrl);

        $responseHeaders = $this->getSession()->getResponseHeaders();

        Assert::assertTrue(
            isset($responseHeaders['Cache-Control']),
            sprintf(
                'Browser cache is not enabled for %s resources. Cache-Control HTTP header was not received.',
                $resourceType
            )
        );

        Assert::assertNotContains(
            '-no',
            $responseHeaders['Cache-Control'],
            sprintf(
                'Browser cache is not enabled for %s resources. Cache-Control HTTP header is "no-cache".',
                $resourceType
            )
        );
    }

    /**
     * @param string $resourceType
     * @param bool   $selfHosted
     * @param bool   $expected
     *
     * @param null   $host
     *
     * @return NodeElement[]
     * @throws \Exception
     */
    private function getPageResources($resourceType, $selfHosted = true, $expected = true, $host = null)
    {
        switch ($resourceType) {
            case self::RESOURCE_TYPES['JPEG']:
                $xpath = '//img[contains(@src,".jpeg")]';

                break;
            case self::RESOURCE_TYPES['PNG']:
                $xpath = '//img[contains(@src,".png")]';

                break;
            case self::RESOURCE_TYPES['GIF']:
                $xpath = '//img[contains(@src,".gif")]';

                break;
            case self::RESOURCE_TYPES['ICO']:
                $xpath = '//link[contains(@href,".ico")]';

                break;
            case self::RESOURCE_TYPES['CSS']:
                $xpath = '//link[contains(@href,".css")]';

                break;
            case self::RESOURCE_TYPES['JAVASCRIPT']:
                $xpath = '//script[contains(@src,".js")]';

                break;
            case self::RESOURCE_TYPES['CSS_INLINE_HEAD']:
                $xpath = '//head//style';

                break;
            case self::RESOURCE_TYPES['CSS_LINK_HEAD']:
                $xpath = '//head//link[contains(@href,".css")]';

                break;
            default:
                throw new \Exception(
                    sprintf('TODO: Must implement %s resource type xpath constructor', $resourceType)
                );
        }

        if (true === $selfHosted) {
            $xpath = preg_replace(
                '/\[contains\(@(.*),/',
                '[(starts-with(@$1,"' . $this->webUrl . '") or starts-with(@$1,"/")) and contains(@$1,',
                $xpath
            );
        } elseif (false === $selfHosted && $host === 'external') {
            $xpath = preg_replace(
                '/\[contains\(@(.*),/',
                '[not(starts-with(@$1,"' . $this->webUrl . '") or starts-with(@$1,"/")) and contains(@$1,',
                $xpath
            );
        } elseif (null !== $host) {
            $xpath = preg_replace(
                '/\[contains\(@(.*),/',
                '[(starts-with(@$1,"' . $host . '") or starts-with(@$1,"/")) and contains(@$1,',
                $xpath
            );
        }

        $elements = $this->getSession()->getPage()->findAll('xpath', $xpath);

        if (true === $expected) {
            Assert::assertNotEmpty(
                $elements,
                sprintf(
                    'No%s %s files are found for %s',
                    $selfHosted ? ' self hosted' : '',
                    $resourceType,
                    $host ?: $this->getCurrentUrl()
                )
            );
        }

        return $elements;
    }

    /**
     * @param NodeElement $element
     * @param string $resourceType
     *
     * @return string
     * @throws \Exception
     */
    private function getResourceUrl(NodeElement $element, $resourceType)
    {
        $this->assertResourceTypeIsValid($resourceType);

        switch ($resourceType) {
            case self::RESOURCE_TYPES['PNG']:
            case self::RESOURCE_TYPES['JPEG']:
            case self::RESOURCE_TYPES['GIF']:
            case self::RESOURCE_TYPES['JAVASCRIPT']:
                return $element->getAttribute('src');

                break;
            case self::RESOURCE_TYPES['CSS']:
            case self::RESOURCE_TYPES['ICO']:
                return $element->getAttribute('href');

                break;
            default:
                throw new \Exception(
                    sprintf('%s resource type url is not implemented', $resourceType)
                );
        }
    }

    /**
     * @param string $resourceType
     */
    private function assertResourceTypeIsValid($resourceType)
    {
        if (!in_array($resourceType, self::RESOURCE_TYPES)) {
            throw new \InvalidArgumentException(
                sprintf(
                    '%s resource type is not valid. Allowed types are: %s',
                    $resourceType,
                    implode(',', self::RESOURCE_TYPES)
                )
            );
        }
    }

    /**
     * @throws \Exception
     *
     * @Then /^js should load (async|defer)$/
     */
    public function theJavascriptFilesShouldLoadAsync()
    {
        $scriptElements = $this->getPageResources(self::RESOURCE_TYPES['JAVASCRIPT']);

        foreach ($scriptElements as $scriptElement) {
            Assert::assertTrue(
                $scriptElement->hasAttribute('async') || $scriptElement->hasAttribute('defer'),
                sprintf(
                    'Javascript file %s is render blocking in %s',
                    $this->getResourceUrl($scriptElement, self::RESOURCE_TYPES['JAVASCRIPT']),
                    $this->getCurrentUrl()
                )
            );
        }
    }

    /**
     * @throws \Exception
     *
     * @Then /^html should be minimized$/
     */
    public function htmlShouldBeMinimized()
    {
        $content = $this->getSession()->getPage()->getContent();
        $this->assertContentIsMinified($content, self::RESOURCE_TYPES['HTML']);
    }

    /**
     * @param string $content
     * @param string $resourceType
     *
     * @throws \Exception
     */
    private function assertContentIsMinified($content, $resourceType)
    {
        switch ($resourceType) {
            case self::RESOURCE_TYPES['CSS']:
                $contentMinified = $this->minimizeCss($content);

                break;
            case self::RESOURCE_TYPES['JAVASCRIPT']:
                $contentMinified = $this->minimizeJs($content);

                break;
            case self::RESOURCE_TYPES['HTML']:
                $contentMinified = $this->minimizeHtml($content);

                break;
            default:
                throw new \Exception(
                    sprintf('Resource type "%s" can not be minified', $resourceType)
                );
        }

        Assert::assertTrue(
            $content == $contentMinified,
            sprintf(
                'Page %s %s code is not minimized.',
                $this->getCurrentUrl(),
                $resourceType
            )
        );
    }

    /**
     * @param $css
     *
     * @return string
     */
    private function minimizeCss($css)
    {
        return preg_replace(
            [
                '#("(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\')|\/\*(?!\!)(?>.*?\*\/)|^\s*|\s*$#s',
                '#("(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\'|\/\*(?>.*?\*\/))|\s*+;\s*+(})\s*+|\s*+([*$~^|
                ]?+=|[{};,>~+]|\s*+-(?![0-9\.])|!important\b)\s*+|([[(:])\s++|\s++([])])|\s++(:)\s*+(?!(?>[^{}"\']++|
                "(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\')*+{)|^\s++|\s++\z|(\s)\s+#si',
            ],
            ['$1', '$1$2$3$4$5$6$7'],
            $css
        );
    }

    /**
     * @param $js
     *
     * @return string
     */
    private function minimizeJs($js)
    {
        return preg_replace(
            [
                '#\s*("(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\')\s*|\s*\/\*(?!\!|@cc_on)(?>[\s\S]*?\*\/)\s*|
                \s*(?<![\:\=])\/\/.*(?=[\n\r]|$)|^\s*|\s*$#',
                '#("(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\'|\/\*(?>.*?\*\/)|\/(?!\/)[^\n\r]*?\/(?=[\s.,;]|
                [gimuy]|$))|\s*([!%&*\(\)\-=+\[\]\{\}|;:,.<>?\/])\s*#s',
            ],
            ['$1', '$1$2'],
            $js
        );
    }

    /**
     * @param $html
     *
     * @return string
     */
    private function minimizeHtml($html)
    {
        return preg_replace_callback(
            '#<([^\/\s<>!]+)(?:\s+([^<>]*?)\s*|\s*)(\/?)>#s',
            function ($matches) {
                $withoutSpaces = preg_replace(
                    '#([^\s=]+)(\=([\'"]?)(.*?)\3)?(\s+|$)#s',
                    ' $1$2',
                    $matches[2]
                );

                return sprintf('<%s%s%s>', $matches[1], $withoutSpaces, $matches[3]);
            },
            $html
        );
    }

    /**
     * @param string $resourceType
     *
     * @throws \Exception
     * @throws UnsupportedDriverActionException
     *
     * @Then /^(css|js) should be minimized$/
     */
    public function cssOrJavascriptFilesShouldBeMinimized($resourceType)
    {
        $this->supportsSymfony(false);

        $elements = $this->getPageResources($resourceType);

        foreach ($elements as $element) {
            $elementUrl = $this->getResourceUrl($element, $resourceType);

            $this->getSession()->visit($elementUrl);

            $content = $this->getSession()->getPage()->getContent();
            $this->assertContentIsMinified($content, $resourceType);

            $this->getSession()->back();
        }
    }

    /**
     * @throws \Exception
     *
     * @Then css should load deferred
     */
    public function cssFilesShouldLoadDeferred()
    {
        $cssElements = $this->getPageResources(
            self::RESOURCE_TYPES['CSS_LINK_HEAD'],
            true,
            false
        );

        Assert::assertEmpty(
            $cssElements,
            sprintf(
                '%s self hosted css files are loading in head in %s',
                count($cssElements),
                $this->getCurrentUrl()
            )
        );
    }

    /**
     * @throws \Exception
     *
     * @Then critical css should exist in head
     */
    public function criticalCssShouldExistInHead()
    {
        $styleCssElements = $this->getPageResources(
            self::RESOURCE_TYPES['CSS_INLINE_HEAD']
        );

        Assert::assertNotEmpty(
            $styleCssElements,
            sprintf(
                'No inline css is loading in head in %s',
                $this->getCurrentUrl()
            )
        );
    }
}