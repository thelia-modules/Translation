<?php

namespace Translation\EventListeners;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
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
        $ext = Translation::getConfigValue('extension');
        $directory = $translationDir . $ext;
        if (file_exists($directory)){
            $this->addTranslationsResources($directory, $ext);
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

            if ($fileInfo->isFile() && $ext === $fileInfo->getExtension()) {
                $locale = array_reverse(explode('.', $fileInfo->getBasename()))[1];

                if (null === $lang = LangQuery::create()->filterByLocale($locale)->findOne()) {
                    // Try to find the lang using the code (compatibility with pre-1.0.3 version)
                    if (null === $lang = LangQuery::create()->filterByCode($locale)->findOne()) {
                        throw new \InvalidArgumentException("Failed to find a lang for locale or code '$locale'. Please check translation file name.");
                    }
                }

                $pathArray = explode(DS, $fileInfo->getPath());
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
