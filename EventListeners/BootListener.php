<?php

namespace Translation\EventListeners;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Translation\Loader\PoFileLoader;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Translation\Translator;
use Thelia\Model\LangQuery;

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
        $poDirectory = THELIA_LOCAL_DIR . "po";
        $xlfDirectory = THELIA_LOCAL_DIR . "xlf";
        if (file_exists($poDirectory)){
            $this->importPoFiles($poDirectory);
        }elseif (file_exists($xlfDirectory)){
            $this->importPoFiles($xlfDirectory);
        }
    }

    protected function importPoFiles($directory)
    {
        foreach (new \DirectoryIterator($directory) as $fileInfo) {
            if ($fileInfo->isDot()) {
                continue;
            }

            if ($fileInfo->isDir()){
                $this->importPoFiles($fileInfo->getPathname());
            }

            if ($fileInfo->isFile()){
                if ('po' === $fileInfo->getExtension()){
                    $langCode = explode('.', $fileInfo->getBasename())[1];
                    $lang = LangQuery::create()->filterByCode($langCode)->findOne();
                    $pathArray = explode('/', $fileInfo->getPath());
                    $domain = end($pathArray);
                    $this->translator->addResource(
                        $fileInfo->getExtension(),
                        $fileInfo->getPathname(),
                        $lang->getLocale(),
                        $domain
                    );

                }
            }
        }
    }
}