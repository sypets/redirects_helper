<?php

declare(strict_types=1);
namespace Sypets\RedirectsHelper\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
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
    protected $dryRun;

    /**
     * @var array
     */
    protected $options;

    /**
     * @var bool
     */
    protected $forceHttps;

    /**
     * @var bool
     */
    protected $interactive;

    /**
     * @var InputInterface
     */
    protected $input;

    /**
     * @var OutputInterface
     */
    protected $output;

    public function __construct(UrlService $urlService, RedirectsService $redirectsService)
    {
        parent::__construct('redirects_helper:sanitize');
        $this->urlService = $urlService;
        $this->redirectsService = $redirectsService;
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
            );
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
        $this->input = $input;
        $this->output = $output;
        $this->io->title($this->getDescription());

        $this->options = $input->getOptions();
        $this->dryRun = $this->options['dry-run'] ?? false;
        $this->interactive = !($this->options['no-interaction'] ?? false);

        if ($this->dryRun) {
            $this->output->writeln('Dry run only - do not change');
        } else {
            $this->output->writeln('No dry run - irreversible changes will be made');
        }
        $this->convertPathToPageLink();

        // @todo Use Command::SUCCESS later, when older dependencies dropped
        return 0;
    }

    protected function convertPathToPageLink(): void
    {
        $redirects = $this->redirectsService->getRedirects();
        $helper = $this->getHelper('question');

        if (!$redirects) {
            $this->io->writeln('No redirects');
            return;
        }

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

        $this->output->writeln('Checking ...', OutputInterface::VERBOSITY_NORMAL);

        while ($redirect = $redirects->fetchAssociative()) {
            try {
                $uid = $redirect['uid'];
                $originalTarget = $redirect['target'];
                $sourcePath = $redirect['source_path'];
                $forceHttps = $this->forceHttps;

                $type = $this->redirectsService->getTargetType($redirect);
                if ($type !== RedirectsService::TARGET_TYPE_PATH) {
                    $this->output->writeln(
                        sprintf('uid=%d:Skipping, target type is not path (target=%s)', $uid, $originalTarget),
                        OutputInterface::VERBOSITY_DEBUG
                    );
                    continue;
                }
                // todo - make alwaysHttps configurable
                $url = $this->redirectsService->getTargetPathUrl($redirect, $forceHttps);

                if ($url === '') {
                    $this->output->writeln(
                        'Skipping: Can\'t build URL:  uid=' . $uid . ' host=' . $redirect['source_host'] . ' target=' . $originalTarget,
                        OutputInterface::VERBOSITY_DEBUG
                    );
                    continue;
                }

                $effectiveUrl = $this->urlService->url2Url($url);
                if ($effectiveUrl === '') {
                    $this->output->writeln(sprintf(
                        'Skipping: URL %s does not resolve to valid URL (uid=%d, original target=%s)',
                        $url,
                        $uid,
                        $originalTarget
                    ), OutputInterface::VERBOSITY_DEBUG);
                    continue;
                }
                $result = $this->urlService->urlToPageInfo($effectiveUrl, false);
                if (!$result) {
                    $this->output->writeln(sprintf(
                        'Skipping: URL %s does not resolve to valid page (uid=%d, original target=%s)',
                        $url,
                        $uid,
                        $originalTarget
                    ), OutputInterface::VERBOSITY_DEBUG);
                }
                $result['isValidUrl'] = true;
                $result['originalTarget'] = $originalTarget;

                $typolink = $result['typolink'] ?? '';
                if ($typolink === '') {
                    $this->output->writeln('Skipping: redirect has no typolink, uid=' . $uid, OutputInterface::VERBOSITY_DEBUG);
                    continue;
                }
                $this->output->writeln(sprintf(
                    'OK: can be converted: uid=%d source=%s, target path %s can be converted to %s',
                    $uid,
                    $sourcePath,
                    $originalTarget,
                    $typolink
                ));

                if ($this->dryRun) {
                    continue;
                }

                if ($this->interactive) {
                    $question = new ConfirmationQuestion('Convert this redirect? (y|n)', false);
                    if (!$helper->ask($this->input, $this->output, $question)) {
                        $this->output->writeln('Skip ...', OutputInterface::VERBOSITY_QUIET);
                        continue;
                    }
                    $this->output->writeln('Continue ...', OutputInterface::VERBOSITY_QUIET);
                }
                $values = [
                    'target' => $typolink
                ];
                $errorMessage = '';

                $this->output->writeln(sprintf(
                    'convert redirect with uid=%d original target=%s new target=%s',
                    $uid,
                    $result['originalTarget'],
                    $typolink
                ));
                $result = $this->redirectsService->updateRedirect($uid, $values, [], $errorMessage);
                if ($result === false) {
                    $this->io->warning($errorMessage, AbstractMessage::ERROR);
                }
            } catch (\Exception | \Throwable $e) {
                $this->output->writeln($e->getMessage(), OutputInterface::VERBOSITY_DEBUG);
                continue;
            }
        }
    }
}
