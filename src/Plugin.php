<?php

namespace DrupalPackageUtilities;

use Composer\Composer as RealComposer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Composer\Installer\PackageEvent;

class Plugin extends Composer implements PluginInterface, EventSubscriberInterface {

  protected $composer;

  protected $io;

  protected $vendorDirectory;

  public function activate(RealComposer $composer, IOInterface $io) {
    $this->composer = $composer;
    $this->io = $io;
    $this->vendorDirectory = $this->composer->getConfig()->get('vendor-dir');
  }

  public static function getSubscribedEvents() {
    return [
      ScriptEvents::POST_CREATE_PROJECT_CMD => 'postCreateProject',
    ];
  }

  public function postCreateProject(Event $event) {

  }

  public static function vendorTestCodeCleanup(PackageEvent $event) {
    $op = $event->getOperation();
    if ($op->getJobType() == 'update') {
      $package = $op->getTargetPackage();
    }
    else {
      $package = $op->getPackage();
    }
    $package_key = static::findPackageKey($package->getName());
    $message = sprintf("    Processing <comment>%s</comment>", $package->getPrettyName());
    if ($this->io->isVeryVerbose()) {
      $this->io->write($message);
    }
    if ($package_key) {
      foreach (static::$packageToCleanup[$package_key] as $path) {
        $dir_to_remove = $this->vendorDirectory . '/' . $package_key . '/' . $path;
        $print_message = $this->io->isVeryVerbose();
        if (is_dir($dir_to_remove)) {
          if (static::deleteRecursive($dir_to_remove)) {
            $message = sprintf("      <info>Removing directory '%s'</info>", $path);
          }
          else {
            // Always display a message if this fails as it means something has
            // gone wrong. Therefore the message has to include the package name
            // as the first informational message might not exist.
            $print_message = TRUE;
            $message = sprintf("      <error>Failure removing directory '%s'</error> in package <comment>%s</comment>.", $path, $package->getPrettyName());
          }
        }
        else {
          // If the package has changed or the --prefer-dist version does not
          // include the directory this is not an error.
          $message = sprintf("      Directory '%s' does not exist", $path);
        }
        if ($print_message) {
          $this->io->write($message);
        }
      }

      if ($this->io->isVeryVerbose()) {
        // Add a new line to separate this output from the next package.
        $this->io->write("");
      }
    }
  }

}
