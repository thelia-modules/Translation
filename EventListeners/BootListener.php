<?php

namespace Translation\EventListeners;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Translation\Loader\PoFileLoader;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Translation\Translator;

class BootListener implements EventSubscriberInterface
{
    protected $translator;

    public function __construct(Translator $translator)
    {
        $this->translator = $translator;
    }

    public static function getSubscribedEvents()
    {
        return [
            TheliaEvents::BOOT => ['addPoTranslations', 64]
       ];
    }

    public function addPoTranslations()
    {
        $poFiles = null;
        $xlfFiles = null;
        $directory = THELIA_LOCAL_DIR . 'po';
        if (file_exists($directory)){
            $poFiles = $this->getPoFiles($directory, []);
        }
        $translator = $this->translator;
        //$translator->addResource('po', THELIA_LOCAL_DIR.'/po/messages.fr.po', 'fr_FR', 'bo.default');
    }

    protected function getPoFiles($directory, $files)
    {
        foreach (new \DirectoryIterator($directory) as $fileInfo) {
            if ($fileInfo->isDot()) {
                continue;
            }

            if ($fileInfo->isDir()){
                $files[] = $this->getPoFiles($fileInfo->getPath(), $files);
            }

            if ($fileInfo->isFile()){
                die($fileInfo->getExtension());
            }
        }
    }
}