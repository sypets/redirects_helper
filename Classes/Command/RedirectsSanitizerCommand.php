<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace Sypets\RedirectsHelper\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Sypets\RedirectsHelper\Service\RedirectsService;
use Sypets\RedirectsHelper\Service\UrlService;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Messaging\AbstractMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class RedirectsSanitizerCommand extends Command
{
    /**
     * @var UrlService
     */
    protected $urlService;

    /**
     * @var RedirectsService
     */
    protected $redirectsService;

    /**
     * @var SymfonyStyle
     */
    protected $io;

    /**
     * @var bool
     */
    protected $verbose;

    /**
     * @var bool
     */
    protected $dryRun;

    /**
     * @var bool
     */
    protected $noOutput;

    /**
     * @var array
     */
    protected $options;

    /**
     * @var bool
     */
    protected $forceHttps;

    public function injectUrlService(UrlService $urlService = null): void
    {
        $this->urlService = $urlService ?: GeneralUtility::makeInstance(UrlService::class);
    }

    public function injectRedirectsService(RedirectsService $redirectsService = null): void
    {
        $this->redirectsService = $redirectsService ?: GeneralUtility::makeInstance(RedirectsService::class);
    }

    /**
     * Configure the command by defining the name, options and arguments
     */
    protected function configure(): void
    {
        $this->setDescription('Sanitize sys_redirects')
            ->addOption(
                'dry-run',
                'd',
                InputOption::VALUE_NONE,
                'Do not make any changes, just show the changes'
            )
            ->addArgument('cmd', InputArgument::REQUIRED, 'command: "path2pagelink": convert path to typolink with page ID');
    }

    /**
     * Executes the command for showing sys_log entries
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->forceHttps = (bool)(GeneralUtility::makeInstance(ExtensionConfiguration::class)
            ->get('redirects_helper', 'forceHttps'));

        $this->io = new SymfonyStyle($input, $output);
        $this->io->title($this->getDescription());

        $cmd = $input->getArgument('cmd');
        $this->options = $input->getOptions();
        $this->verbose = $this->options['verbose'] ?? false;
        $this->dryRun = $this->options['dry-run'] ?? false;
        $this->noOutput = $this->options['quiet'] ?? false;

        if ($this->dryRun) {
            $this->write('Dry run only - do not change', AbstractMessage::INFO);
        } else {
            $this->write('No dry run - irreversible changes will be made', AbstractMessage::INFO);
        }

        $redirects = $this->redirectsService->getRedirects();

        switch ($cmd) {
            case 'path2pagelink':
                $this->convertPathToPageLink($redirects);
                break;
            default:
                $this->write('Unsupported argument passed, use -h for help', AbstractMessage::ERROR);
                return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * @param array $redirects
     * @todo add dry-run
     * @todo make configurable: use https, set endtime
     */
    protected function convertPathToPageLink(array $redirects): void
    {
        /*
         * 1. get type of target link - consider only paths (e.g. '/someslug/other')
         * 2. get final URL and check (follow redirects, ignore invalid URLs)
         * 3. get page information for URL, convert to typolink
         * 4. convert target to typolink
         */

        /**
         * @var array
         *
         * $uid => [
         *   'isValidUrl' => bool
         *   'errormessage' => string
         *   'typolink' =>  string
         *   'pageId' => int
         *   'l10nParent' => int
         *   'languageId' => int
         *   'slug' = string
         *   'originalTarget' => string
         * ]
         */
        $results = [];

        $this->write('Checking ...', AbstractMessage::INFO);

        foreach ($redirects as $redirect) {
            try {
                $uid = $redirect['uid'];
                $originalTarget = $redirect['target'];
                $sourcePath = $redirect['source_path'];
                $forceHttps = (bool)($redirect['force_https'] ?: $this->forceHttps);

                $type = $this->redirectsService->getTargetType($redirect);
                if ($type !== RedirectsService::TARGET_TYPE_PATH) {
                    $this->write('uid=' . $uid . ':Skipping, target type is not path:' . $originalTarget, AbstractMessage::NOTICE);
                    continue;
                }
                // todo - make alwaysHttps configurable
                $url = $this->redirectsService->getTargetPathUrl($redirect, $forceHttps);

                if ($url === '') {
                    $this->write(
                        'Skipping: Can\'t build URL:  uid=' . $uid . ' host=' . $redirect['source_host'] . ' target=' . $originalTarget,
                        AbstractMessage::NOTICE
                    );
                    continue;
                }

                $effectiveUrl = $this->urlService->url2Url($url);
                if ($effectiveUrl === '') {
                    $this->write(sprintf(
                        'Skipping: URL does not resolve to valid URL: uid=%d, original target=%s, error=%s',
                        $uid,
                        $originalTarget,
                        $this->urlService->getErrorMessage()
                    ), AbstractMessage::NOTICE);
                    continue;
                }
                // @todo add alwaysLinkToOriginalLanguage
                $result = $this->urlService->urlToPageInfo($effectiveUrl, false);
                $result['isValidUrl'] = true;
                $result['originalTarget'] = $originalTarget;
                $results[$uid] = $result;

                $this->write(sprintf(
                    'OK: can be converted: uid=%d source=%s, target path %s can be converted to %s',
                    $uid,
                    $sourcePath,
                    $originalTarget,
                    $result['typolink'] ?? ''
                ), AbstractMessage::INFO);
            } catch (\Exception | \Throwable $e) {
                $results[$uid] = [
                    'isValidUrl' => false,
                    'errormessage' => $e->getMessage()
                ];
                $this->write($e->getMessage(), AbstractMessage::WARNING);
                continue;
            }
        }

        foreach ($results as $uid => $result) {
            $uid = (int)$uid;
            $typolink = $result['typolink'] ?? '';
            $errorMessage = '';

            if ($typolink === '') {
                $this->write('Skipping: redirect has no typolink, uid=' . $uid, AbstractMessage::WARNING);
                continue;
            }

            $values = [
                'target' => $typolink
            ];

            $this->write(sprintf(
                'convert redirect with uid=%d original target=%s new target=%s',
                $uid,
                $result['originalTarget'],
                $typolink
            ), AbstractMessage::INFO);
            if (!$this->dryRun) {
                $result = $this->redirectsService->updateRedirect($uid, $values, [], $errorMessage);
                if ($result === false) {
                    $this->write($errorMessage, AbstractMessage::ERROR);
                }
            }
        }
    }

    /**
     * Map input options to supported AbstractMessage. The more severe the message,
     * the higher the value. In case of an unexpected event (e.g. warning), the output should be
     * visible, even if not explicitly set
     *
     * Options:
     *
     * -v   :  show all output
     * -q   :  no output at all.
     *
     * By default, >= AbstractMessage::OK is displayed
     *
     * Starting here, everything will be output by default, unless -q (quiet) is given.
     *
     * AbstractMessage::NOTICE
     * AbstractMessage::INFO
     * AbstractMessage::OK
     * AbstractMessage::WARNING
     * AbstractMessage::ERROR
     *
     * @param string $msg
     * @param int $level
     *
     * @todo Use OutputInterface, see https://symfony.com/doc/current/console/verbosity.html
     */
    protected function write(string $msg, int $level = AbstractMessage::INFO): void
    {
        if ($this->noOutput) {
            return;
        }

        if ($this->verbose || $level >= AbstractMessage::OK) {
            $this->io->writeln($msg);
        }
    }
}
