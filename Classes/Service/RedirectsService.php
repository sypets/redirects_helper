<?php

declare(strict_types=1);
namespace Sypets\RedirectsHelper\Service;

use Doctrine\DBAL\Driver\Statement;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\Exception\NotImplementedMethodException;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class RedirectsService
{
    protected const TABLE = 'sys_redirect';

    public const TARGET_TYPE_UNKNOWN = 0;
    public const TARGET_TYPE_PATH = 1;
    public const TARGET_TYPE_URL = 2;
    public const TARGET_TYPE_PAGE_ID = 3;
    public const TARGET_TYPE_TYPOLINK_PAGE = 4;
    public const TARGET_TYPE_TYPOLINK_FILE = 5;

    /**
     * @var DataHandler
     */
    protected $dataHandler;

    /**
     * @var bool
     */
    protected $useDataHandler;

    public function __construct()
    {
        // initialize for DataHandler
        Bootstrap::initializeBackendAuthentication();
    }

    public function injectDataHandler(DataHandler $dataHandler): void
    {
        $this->dataHandler = $dataHandler;
    }

    /**
     * @return Statement|\Doctrine\DBAL\ForwardCompatibility\Result|\Doctrine\DBAL\Driver\ResultStatement|int
     */
    public function getRedirects()
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(self::TABLE);

        return $queryBuilder
            ->select('*')
            ->from(self::TABLE)
            ->execute();
    }

    public function getTargetType(array $redirectRecord): int
    {
        $target = $redirectRecord['target'];
        if (is_int($target)) {
            return self::TARGET_TYPE_PAGE_ID;
        }
        if (strpos($target, 't3://page?') === 0) {
            return self::TARGET_TYPE_TYPOLINK_PAGE;
        }
        if (strpos($target, 't3://file?') === 0) {
            return self::TARGET_TYPE_TYPOLINK_FILE;
        }
        if (strpos($target, '/') === 0) {
            return self::TARGET_TYPE_PATH;
        }
        if (strpos($target, 'http') === 0) {
            return self::TARGET_TYPE_URL;
        }

        return self::TARGET_TYPE_UNKNOWN;
    }

    /**
     * Gets the target URL for a redirect if the target is a path - this will only work for records
     * where the source_host has been set. For others, use getTargetUrls() instead.
     *
     * @todo This conversion will usually work for typical usecase of converting automatically
     *   generated redirects with a target path where in this case the additional entry points
     *   for language, redirect enhancers etc. are already consistederd, so in the path we have
     *   not just the slug (/path), but for example /en/path.html.
     *   Should check if there are edge cases where this does not work.
     *
     *
     * @param array $redirectsRecord
     * @param bool $alwaysHttps
     * @return string result URL or empty string
     */
    public function getTargetPathUrl(array $redirectsRecord, bool $alwaysHttps = false): string
    {
        if ($alwaysHttps === false) {
            $alwaysHttps = (bool)($redirectsRecord['force_https']);
        }
        $scheme = $alwaysHttps ? 'https' : 'http';
        $host = $redirectsRecord['source_host'];
        if (!$host || $host === '*') {
            return '';
        }
        return $scheme . '://' . $host . $redirectsRecord['target'] ?? '';
    }

    /**
     * @param array $redirectsRecord
     * @param bool $alwaysHttps
     * @return array
     *
     * @throws NotImplementedMethodException
     */
    public function getTargetUrlsarray(array $redirectsRecord, bool $alwaysHttps = false): array
    {
        throw new NotImplementedMethodException('Function getTargetUrlsarray() not implemented');
    }

    public function updateRedirect(int $uid, array $values, array $types = [], string &$errorMessage): bool
    {
        if ($this->useDataHandler) {
            $data = [
                self::TABLE => [
                    $uid => $values
                ]
            ];

            $this->dataHandler->start($data, []);
            $this->dataHandler->process_datamap();
            if (!empty($this->dataHandler->errorLog)) {
                foreach ($this->dataHandler->errorLog as $log) {
                    $errorMessage .= $log . ' ';
                }
                return false;
            }
        } else {
            if ($types) {
                $numChanged = (int)GeneralUtility::makeInstance(ConnectionPool::class)
                    ->getConnectionForTable(self::TABLE)
                    ->update(
                        self::TABLE,
                        $values,
                        ['uid' => $uid],
                        $types
                    );
            } else {
                $numChanged = (int)GeneralUtility::makeInstance(ConnectionPool::class)
                    ->getConnectionForTable(self::TABLE)
                    ->update(
                        self::TABLE,
                        $values,
                        ['uid' => $uid]
                    );
            }
            if ($numChanged !== 1) {
                $errorMessage = sprintf('Num changed should be 1, is %d for uid=%d', $numChanged, $uid);
                return false;
            }
        }
        return true;
    }
}
