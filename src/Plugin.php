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
use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Filesystem;

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
        $this->filesystem = new Filesystem();
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

        // Default project type is Composer.
        $projectType = 'composer';
        $dependencies = [
            'enlightn/security-checker',
            'ergebnis/composer-normalize',
            'php-parallel-lint/php-parallel-lint',
            'phpro/grumphp',
            'phpstan/phpstan-deprecation-rules',
        ];

        // Extra dependencies for Symfony projects.
        if ($localRepo->findPackages('symfony/framework-bundle')) {
            $projectType = 'symfony';
            $dependencies += [
                'escapestudios/symfony2-coding-standard',
            ];
        }

        // Extra dependencies for Drupal projects.
        if ($localRepo->findPackages('drupal/core')) {
            $projectType = 'drupal';
            $dependencies += [
                'drupal/coder',
                'mglaman/phpstan-drupal',
            ];
            // Make sure we have our custom folders.
            $this->filesystem->mkdir('web/modules/custom');
            $this->filesystem->mkdir('web/themes/custom');
            $this->filesystem->mkdir('web/profiles/custom');
        }

        // Find all templates to copy over to the project.
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

        // Copy all needed files over and notify the user.
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

        // Check if there are any missing dependencies.
        $missingDependencies = [];
        foreach ($dependencies as $dependency) {
            if (!$localRepo->findPackages($dependency)) {
                $missingDependencies[] = $dependency;
            }
        }

        // Ask the user if they would like to add the missing dependencies.
        // @todo: Create yes no question that allows to run composer require <packages> --dev
        if ($missingDependencies) {
            $io->write(sprintf(
                'There are dependencies missing! To add them please run <info>composer require %s --dev</info>',
                implode(' ', $missingDependencies)
            ));
        }
        return $exitCode;
    }
}
