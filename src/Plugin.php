<?php

namespace LocalPackages;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Json\JsonFile;
use Composer\Package\AliasPackage;
use Composer\Package\CompletePackage;
use Composer\Package\PackageInterface;
use Composer\Plugin\PluginInterface;
use Composer\Repository\CompositeRepository;
use Composer\Repository\InstalledFilesystemRepository;
use Composer\Repository\PathRepository;
use Composer\Repository\WritableRepositoryInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Composer\Util\Filesystem;

class Plugin implements PluginInterface, EventSubscriberInterface
{
    /**
     * @var Composer
     */
    protected $composer;

    /**
     * @var IOInterface
     */
    protected $io;

    /**
     * @var bool
     */
    protected $localPackagesLoaded = false;

    /**
     * @var array
     */
    protected $localPackages = [];

    public function activate(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->io = $io;
    }

    public static function getSubscribedEvents()
    {
        return [
            ScriptEvents::POST_UPDATE_CMD    => ['symlinkLocalPackages', 100],
            ScriptEvents::POST_INSTALL_CMD   => ['symlinkLocalPackages', 100],
            ScriptEvents::PRE_AUTOLOAD_DUMP  => ['loadLocalPackages', 100],
            ScriptEvents::POST_AUTOLOAD_DUMP => ['revertLocalPackages', 100],
        ];
    }

    /**
     * Symlink all LocalPackages-managed packages
     *
     * After `composer update`, we replace all packages that can also be found
     * in paths managed by LocalPackages with symlinks to those paths.
     *
     * @param \Composer\Script\Event $event
     */
    public function symlinkLocalPackages(Event $event)
    {
        // Create symlinks for all left-over packages in vendor/composer/LocalPackages
        $destination = $this->composer->getConfig()->get('vendor-dir') . '/composer/local-packages';
        (new Filesystem())->emptyDirectory($destination);
        $localPackagesRepo = new InstalledFilesystemRepository(
            new JsonFile($destination . '/installed.json')
        );

        $installationManager = $this->composer->getInstallationManager();

        // Get local repository which contains all installed packages
        $installed = $this->composer->getRepositoryManager()->getLocalRepository();

        foreach ($this->getManagedPackages() as $package) {
            $package->setInstallationSource('dist');

            $original = $installed->findPackage($package->getName(), '*');
            $originalPackage = $original instanceof AliasPackage ? $original->getAliasOf() : $original;

            // Change the source type to path, to prevent 'The package has modified files'
            if ($originalPackage instanceof CompletePackage) {
                $originalPackage->setInstallationSource('dist');
                $originalPackage->setDistType('path');
            }

            $this->retry(function () use ($installationManager, $original, $installed) {
                $installationManager->getInstaller($original->getType())->uninstall($installed, $original);
            });

            $this->retry(function () use ($installationManager, $package, $localPackagesRepo) {
                $installationManager->getInstaller($package->getType())->install($localPackagesRepo, $package);
            });
        }

        $localPackagesRepo->write($event->isDevMode(), $installationManager);
    }

    /**
     * Swap installed packages with symlinked versions for autoload dump.
     */
    public function loadLocalPackages()
    {
        $this->registerLocalPackages();

        $this->swapPackages();
    }

    /**
     * Revert swapped package versions when autoload dump is complete.
     */
    public function revertLocalPackages()
    {
        $this->swapPackages(true);
    }

    /**
     * If LocalPackages packages have not already been loaded, we need to determine
     * which ones specified in LocalPackages.json are installed for this repo.
     */
    public function registerLocalPackages()
    {
        if (! $this->localPackagesLoaded) {
            $localRepo = $this->composer->getRepositoryManager()->getLocalRepository();

            foreach ($this->getManagedPackages() as $package) {
                $this->write('Loading package ' . $package->getName());

                $this->localPackages[$package->getName()] = [
                    'local' => $package,
                ];
            }

            foreach ($localRepo->getCanonicalPackages() as $package) {
                if (isset($this->localPackages[$package->getName()])) {
                    $this->localPackages[$package->getName()]['original'] = $package;
                }
            }
        }
    }

    /**
     * Remove original packages from local repository manager and replace with
     * LocalPackages/symlinked packages.
     *
     * @param bool $revert Revert flag will undo this change.
     */
    protected function swapPackages($revert = false)
    {
        $localRepo = $this->composer->getRepositoryManager()->getLocalRepository();

        foreach ($this->localPackages as $package) {
            $localRepo->removePackage($package[$revert ? 'local' : 'original']);
            $localRepo->addPackage(clone $package[$revert ? 'original' : 'local']);
        }
    }

    /**
     * @param WritableRepositoryInterface $installedRepo
     * @param PathRepository[]            $managedRepos
     *
     * @return PackageInterface[]
     */
    private function getIntersection(WritableRepositoryInterface $installedRepo, $managedRepos)
    {
        $managedRepo = new CompositeRepository($managedRepos);

        return array_filter(
            array_map(
                function (PackageInterface $package) use ($managedRepo) {
                    return $managedRepo->findPackage($package->getName(), '*');
                },
                $installedRepo->getCanonicalPackages()
            )
        );
    }

    private function getManagedPackages()
    {
        $composerConfig = $this->composer->getConfig();

        // Get array of PathRepository instances for LocalPackages-managed paths
        $managed = [];
        foreach ($this->getManagedPaths() as $path) {
            $managed[] = new PathRepository(
                ['url' => $path],
                $this->io,
                $composerConfig
            );
        }

        // Intersect PathRepository packages with local repository
        return $this->getIntersection(
            $this->composer->getRepositoryManager()->getLocalRepository(),
            $managed
        );
    }

    /**
     * Get the list of paths that are being managed by LocalPackages.
     *
     * @return array
     */
    private function getManagedPaths()
    {
        $targetDir = realpath($this->composer->getPackage()->getTargetDir() ?: '');
        $config = Config::make(sprintf('%s/%s', $targetDir, Config::FILENAME));

        return $config->getPaths();
    }

    protected function retry($f, $delay = 3, $retries = 5)
    {
        try {
            return $f();
        } catch (\Throwable $e) {
            if ($retries > 0) {
                sleep($delay);

                return $this->retry($f, $delay, $retries - 1);
            } else {
                throw $e;
            }
        }
    }

    private function write($msg)
    {
        $this->io->write("[LocalPackages] $msg");
    }

    public function deactivate(Composer $composer, IOInterface $io)
    {
        // TODO: Implement deactivate() method.
    }

    public function uninstall(Composer $composer, IOInterface $io)
    {
        // TODO: Implement uninstall() method.
    }
}
