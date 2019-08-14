<?php

namespace Translation\EventListeners;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Translation\Loader\PoFileLoader;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Translation\Translator;
use Thelia\Model\LangQuery;
use Translation\Translation;

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
            TheliaEvents::BOOT => ['addTranslations', 64]
       ];
    }

    public function addTranslations()
    {
        $translationDir = Translation::TRANSLATIONS_DIR;
        $ext = Translation::getConfigValue("extension");
        $Directory = $translationDir . $ext;
        if (file_exists($Directory)){
            $this->addTranslationsResources($Directory, $ext);
        }
    }

    protected function addTranslationsResources($directory, $ext)
    {
        foreach (new \DirectoryIterator($directory) as $fileInfo) {
            if ($fileInfo->isDot()) {
                continue;
            }

            if ($fileInfo->isDir()){
                $this->addTranslationsResources($fileInfo->getPathname(), $ext);
            }

            if ($fileInfo->isFile()){
                if ($ext === $fileInfo->getExtension()){
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