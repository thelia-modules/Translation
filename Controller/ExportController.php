<?php


namespace Translation\Controller;


use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Translation\Dumper\PoFileDumper;
use Symfony\Component\Translation\Dumper\XliffFileDumper;
use Symfony\Component\Translation\MessageCatalogue;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\Translation\TranslationEvent;
use Thelia\Core\Template\TemplateDefinition;

class ExportController extends BaseAdminController
{
    public function exportAction()
    {
        $directory = THELIA_ROOT . DS . 'templates' . DS . TemplateDefinition::FRONT_OFFICE_SUBDIR . DS .'default' . DS .'i18n';
        $directory = "/application/templates/frontOffice/default";
        //$directory = THELIA_LIB;
        $domain = 'fo.default';
        $i18nDirectory = THELIA_LIB . 'Config' . DS . 'I18n';
        $walkMode = TranslationEvent::WALK_MODE_TEMPLATE;

        $event = TranslationEvent::createGetStringsEvent(
            $directory,
            $walkMode,
            $this->getCurrentEditionLocale(),
            $domain
        );

        $this->getDispatcher()->dispatch(TheliaEvents::TRANSLATION_GET_STRINGS, $event);

        //return new JsonResponse($event->getTranslatableStrings());

        $catalogue = new MessageCatalogue('fr');

        foreach ($event->getTranslatableStrings() as $translation) {
            //$catalogueLine[] = [$translation['text'] => $translation['translation']];
            $catalogue->add([$translation['text'] => $translation['translation']]);
        }

        //$catalogue->add($catalogueLine);

        //$dumper = new XliffFileDumper();
        $dumper = new PoFileDumper();

        dump($dumper->formatCatalogue($catalogue, 'messages'));
        $dumper->dump($catalogue, ['path' => THELIA_LOCAL_DIR.'po'.DS]);
        die();
    }

}