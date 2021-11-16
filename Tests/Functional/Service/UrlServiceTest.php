<?php

declare(strict_types=1);
namespace Sypets\RedirectsHelper\Tests\Functional\Service;

use Sypets\RedirectsHelper\Service\UrlService;
use TYPO3\CMS\Core\Configuration\SiteConfiguration;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Http\ServerRequestFactory;
use TYPO3\CMS\Core\Routing\Enhancer\EnhancerFactory;
use TYPO3\CMS\Core\Routing\SiteMatcher;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Tests\Functional\SiteHandling\SiteBasedTestTrait;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

class UrlServiceTest extends FunctionalTestCase
{
    use SiteBasedTestTrait;

    /**
     * @var bool Reset singletons created by subject
     */
    protected $resetSingletonInstances = true;

    protected $coreExtensionsToLoad = ['redirects'];

    private $languages = [
        [
            'title' => 'English',
            'enabled' => true,
            'languageId' => '0',
            'base' => '/',
            'typo3Language' => 'default',
            'locale' => 'en_US.UTF-8',
            'iso-639-1' => 'en',
            'navigationTitle' => 'English',
            'hreflang' => 'en-us',
            'direction' => 'ltr',
            'flag' => 'us',
        ],
        [
            'title' => 'German',
            'enabled' => true,
            'languageId' => '1',
            'base' => 'https://de.example.com/',
            'typo3Language' => 'de',
            'locale' => 'de_DE.UTF-8',
            'iso-639-1' => 'de',
            'navigationTitle' => 'German',
            'hreflang' => 'de-de',
            'direction' => 'ltr',
            'flag' => 'de',
        ],
        [
            'title' => 'Spanish',
            'enabled' => true,
            'languageId' => '2',
            'base' => '/es/',
            'typo3Language' => 'es',
            'locale' => 'es_ES.UTF-8',
            'iso-639-1' => 'es',
            'navigationTitle' => 'Spanish',
            'hreflang' => 'es-es',
            'direction' => 'ltr',
            'flag' => 'es',
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Bootstrap::initializeLanguageObject();
        $this->buildBaseSiteWithLanguages();
    }

    protected function buildBaseSiteWithLanguages(): void
    {
        $configuration = [
            'rootPageId' => 1,
            'base' => 'https://example.com',
            'languages' => $this->languages,
        ];
        $siteConfiguration = GeneralUtility::makeInstance(SiteConfiguration::class);
        $siteConfiguration->write('testing', $configuration);
    }

    /**
     * @return \Generator<string,string[]>
     */
    public function urlToPageInfoReturnsCorrectResultDataProvider(): \Generator
    {
        yield 'Existing URL with language 0' => [
            'https://example.com/abc',
            [
                'typolink' =>  't3://page?uid=2',
                'pageId' => 2,
                'languageId' => 0,
                'slug' => '/abc'
            ]
        ];
    }

    /**
     * @test
     * @dataProvider urlToPageInfoReturnsCorrectResultDataProvider
     */
    public function urlToPageInfoReturnsCorrectResult(string $url, array $expectedResult): void
    {
        $this->importDataSet(__DIR__ . '/Fixtures/UrlService.xml');
        $this->writeSiteConfiguration(
            'testing',
            $this->buildSiteConfiguration(1, 'https://example.com/')
        );
        $this->setUpFrontendRootPage(
            1,
            ['typo3/sysext/redirects/Tests/Functional/Service/Fixtures/Redirects.typoscript']
        );

        $subject = new UrlService(
            new SiteMatcher(),
            new ServerRequestFactory(),
            new RequestFactory(),
            new Context(),
            new EnhancerFactory(),
            new SiteFinder()
        );
        $result = $subject->urlToPageInfo($url, false);

        self::assertEquals($expectedResult, $result);
    }
}
