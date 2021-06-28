<?php

/**
 * This file is part of the VerbruggenAlex CodingStandards Composer plugin.
 */

namespace Verbruggenalex\CodingStandards;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Composer\Util\Filesystem;
use Composer\Util\ProcessExecutor;
use Symfony\Component\Finder\Finder;

/**
 * Plugin.
 */
final class Plugin implements PluginInterface, EventSubscriberInterface
{

    /**
     * @var Composer
     */
    private $composer;

    /**
     * @var string
     */
    private $cwd;

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var IOInterface
     */
    private $io;

    /**
     * Triggers the plugin's main functionality.
     *
     * Makes it possible to run the plugin as a custom command.
     *
     * @param Event $event
     */
    public static function run(Event $event)
    {
        $io = $event->getIO();
        $composer = $event->getComposer();

        $instance = new static();

        $instance->io = $io;
        $instance->composer = $composer;
        $instance->init();
        $instance->onDependenciesChangedEvent();
    }

    /**
     * {@inheritDoc}
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->io = $io;

        $this->init();
    }

    /**
     * {@inheritDoc}
     */
    public function deactivate(Composer $composer, IOInterface $io)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function uninstall(Composer $composer, IOInterface $io)
    {
    }

    /**
     * Prepares the plugin so it's main functionality can be run.
     */
    private function init()
    {
        $this->cwd = getcwd();
        $this->filesystem = new Filesystem(new ProcessExecutor($this->io));
    }

    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            ScriptEvents::POST_INSTALL_CMD => array(
                array('onDependenciesChangedEvent', 0),
            ),
            ScriptEvents::POST_UPDATE_CMD => array(
                array('onDependenciesChangedEvent', 0),
            ),
        );
    }

    /**
     * Entry point for post install and post update events.
     */
    public function onDependenciesChangedEvent()
    {
        $io = $this->io;
        $exitCode = 0;
        $localRepo = $this->composer->getRepositoryManager()->getLocalRepository();
        $projectType = 'composer';
        if ($localRepo->findPackages('symfony/framework-bundle')) {
            $projectType = 'symfony';
        }
        if ($localRepo->findPackages('drupal/core')) {
            $projectType = 'drupal';
        }

        $finder = new Finder();
        $finder->files()->in(__DIR__ . '/../templates/' . $projectType);
        $scaffolding = [];

        foreach ($finder as $file) {
            $absoluteFilePath = $file->getRealPath();
            $relativeFilePath = $file->getRelativePathname();
            if (!file_exists($relativeFilePath)) {
                $scaffolding[$absoluteFilePath] = $relativeFilePath;
            }
        }

        if ($scaffolding) {
            $io->write('Scaffolding files for <comment>verbruggenalex/coding-standards</comment>');
            foreach ($scaffolding as $absoluteFilePath => $relativeFilePath) {
                // Copy the tool configuration files that point to our coding-standards standard.
                $this->filesystem->copy($absoluteFilePath, $this->cwd . '/' . $relativeFilePath);
                $io->write(sprintf(
                    '  - Copy <info>%s</info> from <info>%s</info>',
                    $relativeFilePath,
                    dirname($absoluteFilePath)
                ));
            }
        }
        return $exitCode;
    }
}
