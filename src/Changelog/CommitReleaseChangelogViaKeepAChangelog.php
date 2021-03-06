<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Changelog;

use Laminas\AutomaticReleases\Git\CheckoutBranch;
use Laminas\AutomaticReleases\Git\CommitFile;
use Laminas\AutomaticReleases\Git\Push;
use Laminas\AutomaticReleases\Git\Value\BranchName;
use Laminas\AutomaticReleases\Git\Value\SemVerVersion;
use Psr\Log\LoggerInterface;
use Webmozart\Assert\Assert;

use function sprintf;

final class CommitReleaseChangelogViaKeepAChangelog implements CommitReleaseChangelog
{
    private const CHANGELOG_FILE = 'CHANGELOG.md';

    private const COMMIT_TEMPLATE = <<< 'COMMIT'
        %s readiness

        Updates the %s to set the release date.
        COMMIT;

    private ChangelogExists $changelogExists;
    private CheckoutBranch $checkoutBranch;
    private CommitFile $commitFile;
    private Push $push;
    private LoggerInterface $logger;

    public function __construct(
        ChangelogExists $changelogExists,
        CheckoutBranch $checkoutBranch,
        CommitFile $commitFile,
        Push $push,
        LoggerInterface $logger
    ) {
        $this->changelogExists = $changelogExists;
        $this->checkoutBranch  = $checkoutBranch;
        $this->commitFile      = $commitFile;
        $this->push            = $push;
        $this->logger          = $logger;
    }

    /**
     * @psalm-param non-empty-string $repositoryDirectory
     */
    public function __invoke(
        ChangelogReleaseNotes $releaseNotes,
        string $repositoryDirectory,
        SemVerVersion $version,
        BranchName $sourceBranch
    ): void {
        if (! $releaseNotes->requiresUpdatingChangelogFile()) {
            // Nothing to commit
            $this->logger->info('CommitReleaseChangelog: no changes to commit.');

            return;
        }

        if (! ($this->changelogExists)($sourceBranch, $repositoryDirectory)) {
            // No changelog
            $this->logger->info('CommitReleaseChangelog: No CHANGELOG.md file detected');

            return;
        }

        ($this->checkoutBranch)($repositoryDirectory, $sourceBranch);

        $changelogFile = sprintf('%s/%s', $repositoryDirectory, self::CHANGELOG_FILE);
        Assert::stringNotEmpty($changelogFile);

        $releaseNotes::writeChangelogFile($changelogFile, $releaseNotes);

        $message = sprintf(self::COMMIT_TEMPLATE, $version->fullReleaseName(), self::CHANGELOG_FILE);
        Assert::notEmpty($message);

        ($this->commitFile)(
            $repositoryDirectory,
            $sourceBranch,
            self::CHANGELOG_FILE,
            $message
        );

        ($this->push)($repositoryDirectory, $sourceBranch->name());
    }
}
