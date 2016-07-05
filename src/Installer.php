<?php

namespace PhpSchool\WorkshopManager;

use Composer\Installer as ComposerInstaller;
use Composer\Factory as ComposerFactory;
use Composer\IO\IOInterface;
use League\Flysystem\Exception as FlysystemException;
use League\Flysystem\Filesystem;
use PhpSchool\WorkshopManager\Entity\Workshop;
use PhpSchool\WorkshopManager\Exception\ComposerFailureException;
use PhpSchool\WorkshopManager\Exception\DownloadFailureException;
use PhpSchool\WorkshopManager\Exception\FailedToMoveWorkshopException;
use PhpSchool\WorkshopManager\Exception\WorkshopAlreadyInstalledException;

/**
 * Class Installer
 * @author Michael Woodward <mikeymike.mw@gmail.com>
 */
final class Installer
{
    /**
     * @var ManagerState
     */
    private $state;

    /**
     * @var Downloader
     */
    private $downloader;

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var ComposerFactory
     */
    private $factory;

    /**
     * @var IOInterface
     */
    private $io;

    /**
     * @param ManagerState $state
     * @param Downloader $downloader
     * @param Filesystem $filesystem
     * @param ComposerFactory $factory
     * @param IOInterface $io
     */
    public function __construct(
        ManagerState $state,
        Downloader $downloader,
        Filesystem $filesystem,
        ComposerFactory $factory,
        IOInterface $io
    ) {
        $this->state      = $state;
        $this->downloader = $downloader;
        $this->filesystem = $filesystem;
        $this->factory    = $factory;
        $this->io         = $io;
    }

    /**
     * @param Workshop $workshop
     *
     * @throws WorkshopAlreadyInstalledException
     * @throws ComposerFailureException
     * @throws DownloadFailureException
     * @throws FailedToMoveWorkshopException
     */
    public function installWorkshop(Workshop $workshop)
    {
        if ($this->state->isWorkshopInstalled($workshop)) {
            throw new WorkshopAlreadyInstalledException;
        }

        $pathToZip  = $this->downloader->download($workshop);
        $zipArchive = new \ZipArchive();

        $zipArchive->open($pathToZip);
        $zipArchive->extractTo(dirname($pathToZip));

        $srcPath  = sprintf('.temp/%s', $zipArchive->getNameIndex(0));
        $destPath = sprintf('workshops/%s', $workshop->getName());

        try {
            $this->filesystem->rename($srcPath, $destPath);
        } catch (FlysystemException $e) {
            throw new FailedToMoveWorkshopException($srcPath, $destPath);
        }

        try {
            $currentPath  = getcwd();
            $workshopPath = $this->filesystem->getAdapter()->applyPathPrefix(
                sprintf('workshops/%s', $workshop->getName())
            );

            $composer = $this->factory->createComposer(
                $this->io,
                sprintf('%s/composer.json', $workshopPath),
                false,
                $workshopPath
            );

            $installer = ComposerInstaller::create($this->io, $composer);

            chdir($workshopPath);
            $installer->run();
            chdir($currentPath);
        } catch (\Exception $e) {
            throw ComposerFailureException::fromException($e);
        }
    }
}