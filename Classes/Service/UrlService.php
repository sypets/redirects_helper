<?php

declare(strict_types=1);
namespace Sypets\RedirectsHelper\Service;

use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\TooManyRedirectsException;
use GuzzleHttp\TransferStats;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Http\ServerRequestFactory;
use TYPO3\CMS\Core\LinkHandling\Exception\UnknownLinkHandlerException;
use TYPO3\CMS\Core\LinkHandling\LinkService;
use TYPO3\CMS\Core\Routing\Enhancer\EnhancerFactory;
use TYPO3\CMS\Core\Routing\PageSlugCandidateProvider;
use TYPO3\CMS\Core\Routing\SiteMatcher;
use TYPO3\CMS\Core\Routing\SiteRouteResult;
use TYPO3\CMS\Core\Site\Entity\NullSite;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class UrlService
{
    /**
     * @var int
     */
    public const REQUEST_RESULT_ERROR_TYPE_UNKNOWN = 1;

    /**
     * @var int
     */
    public const REQUEST_RESULT_ERROR_TYPE_HTTP = 2;

    /**
     * @var int
     */
    public const REQUEST_RESULT_ERROR_TYPE_TOO_MANY_REDIRECTS = 3;

    /**
     * @var int
     */
    public const REQUEST_RESULT_ERROR_TYPE_EXCEPTION = 4;

    /**
     * @var int
     */
    public const ERROR_URL_NOT_SUPPORTED = 1;

    /**
     * @var array<string,mixed>
     */
    protected $pageSlugCandidateProviders;

    /**
     * @var array<mixed>
     */
    protected $currentPageSlugCandidateProvider;

    /**
     * @var SiteFinder
     */
    protected $siteFinder;

    /**
     * @var SiteLanguage
     */
    protected $siteLanguage;

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var EnhancerFactory
     */
    protected $enhancerFactory;

    /**
     * @var ServerRequestFactory
     */
    protected $serverRequestFactory;

    /**
     * @var RequestFactory
     *
     * @todo - check if $serverRequestFactory + $requestFactory can be merged
     */
    protected $httpRequestFactory;

    /**
     * @var SiteMatcher
     */
    protected $siteMatcher;

    /**
     * @var array
     */
    protected $errorParams;

    /**
     * @var string
     */
    protected $effectiveUrl;

    public function __construct(
        SiteMatcher $siteMatcher,
        ServerRequestFactory $requestFactory,
        RequestFactory $factory,
        Context $context,
        EnhancerFactory $enhancerFactory,
        SiteFinder $siteFinder
    ) {
        $this->siteMatcher = $siteMatcher;
        $this->serverRequestFactory = $requestFactory;
        $this->httpRequestFactory = $factory;
        $this->context = $context;
        $this->enhancerFactory = $enhancerFactory;
        $this->siteFinder = $siteFinder;

        $this->pageSlugCandidateProviders = [];
    }

    /**
     * $this->site  must be initialized before calling this function!
     *
     * @throws \UnexpectedValueException
     */
    protected function initializeSlugCandidateProvider(SiteInterface $site, PageSlugCandidateProvider $pageSlugCandidateProvider = null): void
    {
        $identifier = $site->getIdentifier();
        if (!isset($this->pageSlugCandidateProviders[$identifier])) {
            $this->pageSlugCandidateProviders[$identifier] = [
                'candidateProvider' => $pageSlugCandidateProvider ?:
                    GeneralUtility::makeInstance(
                        PageSlugCandidateProvider::class,
                        $this->context,
                        $site,
                        $this->enhancerFactory
                    ),
                'site' => $site
            ];
            $this->currentPageSlugCandidateProvider = $this->pageSlugCandidateProviders[$identifier];
        }
    }

    /**
     * Fetch a Site matching the given URL.
     *
     * @param string $url
     * @return SiteInterface
     * @throws \InvalidArgumentException
     */
    public function getSiteForUrl(string $url): SiteInterface
    {
        $host = parse_url($url, PHP_URL_HOST);

        /**
         * @var SiteInterface[] $sites
         */
        $sites = $this->siteFinder->getAllSites();
        foreach ($sites as $site) {
            if ($site->getBase()->getHost() === $host) {
                return $site;
            }
        }
        throw new \InvalidArgumentException('No site found for given url:' . $url);
    }

    public function getErrorMessage(): string
    {
        if (isset($this->errorParams)) {
            if (isset($this->errorParams['exception'])) {
                return $this->errorParams['exception'];
            }
            if (isset($this->errorParams['errorType'])) {
                return $this->errorParams['errorType'];
            }
        }
        return 'unknown error';
    }

    /**
     * Follow redirects for URL and check if final URL is valid.
     *
     * @param string $url
     * @return string
     */
    public function url2Url(string $url): string
    {
        if (!$this->requestUrl($url)) {
            return '';
        }
        return $this->effectiveUrl;
    }

    /**
     * @deprecated unnecessary url2url now returns URL directly
     * @return string
     */
    public function getEffectiveUrl(): string
    {
        return $this->effectiveUrl;
    }

    protected function requestUrl(string $url, string $method = 'GET', array $options = []): bool
    {
        $effectiveUrl = '';

        if ($options === []) {
            $options = [
                'cookies' => GeneralUtility::makeInstance(CookieJar::class),
                'allow_redirects' => ['strict' => true],
                'headers' => [
                    'User-Agent' => 'TYPO3 internal redirects_helper'
                ],
                'on_stats' => function (TransferStats $stats) use (&$effectiveUrl) {
                    $effectiveUrl = (string)$stats->getEffectiveUri();
                },
            ];
        }

        $isValidUrl = false;

        try {
            $response = $this->httpRequestFactory->request($url, $method, $options);
            if ($response->getStatusCode() >= 300) {
                $this->errorParams['errorType'] = self::REQUEST_RESULT_ERROR_TYPE_HTTP;
                $this->errorParams['errno'] = $response->getStatusCode();
            } else {
                $isValidUrl = true;
            }
        } catch (TooManyRedirectsException $e) {
            $this->errorParams['errorType'] = self::REQUEST_RESULT_ERROR_TYPE_TOO_MANY_REDIRECTS;
            $this->errorParams['exception'] = $e->getMessage();
        } catch (ClientException $e) {
            if ($e->hasResponse()) {
                $this->errorParams['errorType'] = self::REQUEST_RESULT_ERROR_TYPE_HTTP;
                $this->errorParams['errno'] = $e->getResponse()->getStatusCode();
            } else {
                $this->errorParams['errorType'] = self::REQUEST_RESULT_ERROR_TYPE_UNKNOWN;
            }
            $this->errorParams['exception'] = $e->getMessage();
        } catch (RequestException $e) {
            $this->errorParams['errorType'] = self::REQUEST_RESULT_ERROR_TYPE_EXCEPTION;
            $this->errorParams['exception'] = $e->getMessage();
        } catch (\Exception $e) {
            // Generic catch for anything else that may go wrong
            $this->errorParams['errorType'] = self::REQUEST_RESULT_ERROR_TYPE_EXCEPTION;
            $this->errorParams['exception'] = $e->getMessage();
        }
        if ($effectiveUrl) {
            $this->effectiveUrl = $effectiveUrl;
        }
        return $isValidUrl;
    }

    /**
     * A URL is mapped to a specific page.
     *
     * Note:
     *
     * @param string $url
     * @param bool $alwaysLinkToOriginalLanguage always create a link to the page of the original
     *   language (and not the language overlay). In some contexts this may be useful, for example
     *   if typolinks are used in bodytext, a link is created using the configured behaviour for
     *   language handling.
     * @return array with information, including typolink, pageId, language etc. or empty array
     * @throws \InvalidArgumentException
     * @throws UnknownLinkHandlerException
     */
    public function urlToPageInfo(string $url, bool $alwaysLinkToOriginalLanguage=false): array
    {
        $candidate = $this->urlToPageCandidate($url);
        if ($candidate === []) {
            return [];
        }

        $languageId = $candidate['languageId'];
        $pageId = $candidate['uid'];
        if ($languageId != 0) {
            $pageId = $candidate['l10n_parent'];
            if ($alwaysLinkToOriginalLanguage) {
                $languageId = 0;
            }
        }

        $results = [
            'typolink' =>  $this->pageToTypolink($pageId, $languageId),
            'pageId' => $pageId,
            'languageId' => $languageId,
            'slug' => $candidate['slug']
        ];
        return $results;
    }

    /**
     * @param int $pageUid
     * @return string
     * @throws UnknownLinkHandlerException
     */
    public function pageToTypolink(int $pageUid, int $language): string
    {
        $linkService = GeneralUtility::makeInstance(LinkService::class);

        /**
         * @todo due to no information which paraameters are correct, we are more
         *   or less guessing here, should be fixed
         * @todo we can't pass the language here? Why?
         */
        $parameters = [
            'type' => LinkService::TYPE_PAGE,
            'pageuid' => $pageUid
        ];

        $result = $linkService->asString($parameters);
        if ($language !== 0) {
            // @todo apparently this is different in v10 and v11 ????
            //$result .= '&_language=' . $language;
            $result .= '&L=' . $language;
        }
        return $result;
    }

    /**
     * @param string $url
     * @return array
     */
    public function urlToPageCandidate(string $url): array
    {
        $routeResult = $this->urlToRouteResult($url);
        $site = $routeResult->getSite();
        $siteIdentifier = $site->getIdentifier();
        if ($site instanceof NullSite) {
            throw new \InvalidArgumentException('Can\'t get site for URL:' . $url);
        }
        $siteLanguage = $routeResult->getLanguage();
        $this->initializeSlugCandidateProvider($site);

        $tail = $routeResult->getTail();

        $candidates = $this->pageSlugCandidateProviders[$siteIdentifier]['candidateProvider']->getCandidatesForPath('/' . $tail, $siteLanguage) ?: [];

        if ($candidates === []) {
            return [];
        }
        $selectedCandidate = [];
        foreach ($candidates as $candidate) {
            if ($candidate['slug'] !== '/' . $tail) {
                continue;
            }
            if (!isset($candidate['uid'])) {
                continue;
            }
            $selectedCandidate = $candidate;
            break;
        }
        if ($selectedCandidate === []) {
            return [];
        }
        $selectedCandidate['languageId'] = $siteLanguage->getLanguageId();
        return $selectedCandidate;
    }

    /**
     * @param string $url
     * @return SiteRouteResult
     *
     * @throws \InvalidArgumentException
     */
    public function urlToRouteResult(string $url): SiteRouteResult
    {
        $request = $this->serverRequestFactory->createServerRequest('GET', $url);

        /**
         * @var SiteRouteResult
         */
        $routeResult = $this->siteMatcher->matchRequest($request);

        if (!($routeResult instanceof SiteRouteResult)) {
            throw new \InvalidArgumentException('Unable to convert give URL:' . $url);
        }

        return $routeResult;
    }
}
