<?php

namespace PhpSchool\WorkshopManager;

use PhpSchool\WorkshopManager\Exception\WorkshopNotInstalledException;
use PhpSchool\WorkshopManager\Repository\InstalledWorkshopRepository;
use PhpSchool\WorkshopManager\Repository\WorkshopRepository;
use Symfony\Component\Filesystem\Exception\IOException;

/**
 * @author Michael Woodward <mikeymike.mw@gmail.com>
 * @author Aydin Hassan <aydin@hotmail.co.uk>
 */
class Uninstaller
{
    /**
     * @var WorkshopRepository
     */
    private $installedWorkshops;

    /**
     * @var Linker
     */
    private $linker;

    /**
     * @var Filesystem
     */
    private $filesystem;


    /**
     * @var string
     */
    private $workshopHomeDirectory;

    /**
     * Uninstaller constructor.
     * @param InstalledWorkshopRepository $installedWorkshops
     * @param Linker $linker
     * @param Filesystem $filesystem
     * @param $workshopHomeDirectory
     */
    public function __construct(
        InstalledWorkshopRepository $installedWorkshops,
        Linker $linker,
        Filesystem $filesystem,
        $workshopHomeDirectory
    ) {
        $this->filesystem         = $filesystem;
        $this->installedWorkshops = $installedWorkshops;
        $this->workshopHomeDirectory = $workshopHomeDirectory;
        $this->linker = $linker;
    }

    /**
     * @param string $workshop
     * @param bool $force
     *
     * @throws WorkshopNotInstalledException
     * @throws \RuntimeException When filesystem delete fails
     * @throws RootViolationException In non existent circumstances :)
     */
    public function uninstallWorkshop($workshop, $force = false)
    {
        if (!$this->installedWorkshops->hasWorkshop($workshop)) {
            throw new WorkshopNotInstalledException;
        }

        $workshop = $this->installedWorkshops->getByName($workshop);

        try {
            $this->filesystem->remove(sprintf('%s/workshops/%s', $this->workshopHomeDirectory, $workshop->getName()));
        } catch (IOException $e) {
            throw $e;
        }

        $this->installedWorkshops->remove($workshop);
        $this->installedWorkshops->save();

        $this->linker->unlink($workshop, $force);
    }
}
