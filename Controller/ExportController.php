<?php


namespace Translation\Controller;


use Symfony\Component\Finder\Finder;
use Symfony\Component\Translation\Dumper\PoFileDumper;
use Symfony\Component\Translation\Dumper\XliffFileDumper;
use Symfony\Component\Translation\MessageCatalogue;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\Translation\TranslationEvent;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Core\Translation\Translator;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Translation\Form\ExportForm;

class ExportController extends BaseAdminController
{
    /**
     * @return \Thelia\Core\HttpFoundation\Response
     * @throws \Exception
     */
    public function exportAction()
    {
        $form = new ExportForm($this->getRequest());

        $exportForm  = $this->validateForm($form);

        $lang = LangQuery::create()->filterById($this->getRequest()->get('lang_id'))->findOne();

        $dir = $exportForm->get('directory')->getData();
        $ext = $exportForm->get('extension')->getData();

        if ($dir === 'all'){
            $this->exportTranslations('frontOffice', $ext, $lang);
            $this->exportTranslations('backOffice', $ext, $lang);
            $this->exportTranslations('pdf', $ext, $lang);
            $this->exportTranslations('email', $ext, $lang);
            $this->exportTranslations('modules', $ext, $lang);
            $this->exportTranslations('coreThelia', $ext, $lang);
        }
        else{
            $this->exportTranslations($dir, $ext, $lang);
        }
        die();
        return $this->render('translation');
    }


    /**
     * @param $dir
     * @param $ext
     * @param Lang $lang
     * @throws \Exception
     */
    protected function exportTranslations($dir, $ext, Lang $lang)
    {
        $template = false;
        $items = [];
        switch ($dir){
            case "coreThelia":
                $domain =  "core";
                $items[$domain]["directory"] = THELIA_LIB;
                $items[$domain]["i18nDirectory"] = THELIA_LIB . 'Config' . DS . 'I18n';
                break;
            case "frontOffice" :
                $template = new TemplateDefinition("default", TemplateDefinition::FRONT_OFFICE);
                break;

            case "backOffice" :
                $template = new TemplateDefinition("default", TemplateDefinition::BACK_OFFICE);
                break;

            case "pdf" :
                $template = new TemplateDefinition("default", TemplateDefinition::PDF);
                break;

            case "email" :
                $template = new TemplateDefinition("default", TemplateDefinition::EMAIL);
                break;

            case "modules" :
                $items = $this->getModulesDirectories();
                break;
        }

        if ($template){
            $domain = $template->getTranslationDomain();
            $items[$domain]["directory"] = $template->getAbsolutePath();
            $items[$domain]["i18nDirectory"] = $template->getAbsoluteI18nPath();
        }


        $dumper = new PoFileDumper();
        if ($ext === "xlf"){
            $dumper = new XliffFileDumper();
        }


        foreach ($items as $domain => $item){

            $directory = $item["directory"];
            $i18nDirectory = $item["i18nDirectory"];

            $explodeDomain = explode('.',$domain);

            $walkMode = TranslationEvent::WALK_MODE_PHP;
            if (count($explodeDomain) > 1){
                $walkMode = TranslationEvent::WALK_MODE_TEMPLATE;
            }

            $this->loadTranslation($i18nDirectory, $domain, $lang->getLocale());

            $event = TranslationEvent::createGetStringsEvent(
                $directory,
                $walkMode,
                $lang->getLocale(),
                $domain
            );
            $this->getDispatcher()->dispatch(TheliaEvents::TRANSLATION_GET_STRINGS, $event);
            $arrayTranslations = [];
            if (null !== $translatableStrings = $event->getTranslatableStrings()){
                foreach ($translatableStrings as $translation) {
                    $arrayTranslations[$translation['text']] = $translation['translation'];
                }
            }


            if ($arrayTranslations != null){

                echo $domain;
                dump($arrayTranslations);

                $catalogue = new MessageCatalogue($lang->getCode());
                $catalogue->add($arrayTranslations);

                $dumper->formatCatalogue($catalogue, 'messages');
                $mod = "";
                if ($dir === 'modules'){
                    $key = explode('.', $domain);
                    $mod = DS.$key[0].DS;
                }
                $path = THELIA_LOCAL_DIR.$ext.DS.$dir.$mod.DS.$domain;
                $dumper->dump($catalogue, ['path' => $path]);
            }
        }
    }

    /**
     * @return array
     */
    protected function getModulesDirectories()
    {
        $modulesNames = scandir(THELIA_LOCAL_DIR.'modules'.DS);
        $directories = [];
        $types = [ 'co', 'fo', 'bo', 'ma', 'pf'];
        $domain = null;
        foreach ($modulesNames as $moduleName){
            if ($moduleName[0] !== '.'){
                /** @var Module $module */
                $module = $this->getModule($moduleName);
                foreach ($types as $type){
                    switch ($type){
                        case 'fo':
                            $domain = $module->getFrontOfficeTemplateTranslationDomain('default');
                            $path = $module->getAbsoluteFrontOfficeTemplatePath('default');
                            $i18nDirectory = $module->getAbsoluteFrontOfficeI18nTemplatePath('default');
                            break;
                        case 'bo':
                            $domain = $module->getBackOfficeTemplateTranslationDomain('default');
                            $path = $module->getAbsoluteBackOfficeTemplatePath('default');
                            $i18nDirectory = $module->getAbsoluteBackOfficeI18nTemplatePath('default');
                            break;

                        case 'ma':
                            $domain = $module->getEmailTemplateTranslationDomain('default');
                            $path = $module->getAbsoluteEmailTemplatePath('default');
                            $i18nDirectory = $module->getAbsoluteEmailI18nTemplatePath('default');
                            break;

                        case 'pf':
                            $domain = $module->getPdfTemplateTranslationDomain('default');
                            $path = $module->getAbsolutePdfTemplatePath('default');
                            $i18nDirectory = $module->getAbsolutePdfI18nTemplatePath('default');
                            break;
                        case 'co':
                            $domain = $module->getTranslationDomain();
                            $path = $module->getAbsoluteBaseDir();
                            $i18nDirectory = $module->getAbsoluteI18nPath();
                            break;
                    }
                    if (null !== $domain){
                        $directories[$domain]['directory'] = $path;
                        $directories[$domain]['i18nDirectory'] = $i18nDirectory;
                    }
                }
            }
        }
        return $directories;
    }

    /**
     * @param $name
     * @return Module
     */
    protected function getModule($name)
    {
        if (null !== $module = ModuleQuery::create()->filterByCode($name)->findOne()) {
            return $module;
        }

        throw new \InvalidArgumentException(
            $this->getTranslator()->trans("No module found for code '%item'", ['%item' => $name])
        );
    }

    private function loadTranslation($directory, $domain, $locale)
    {
        try {
            $finder = Finder::create()
                ->files()
                ->depth(0)
                ->in($directory);

            /** @var \DirectoryIterator $file */
            foreach ($finder as $file) {
                list($name, $format) = explode('.', $file->getBaseName(), 2);
                if ($name === $locale){
                    Translator::getInstance()->addResource($format, $file->getPathname(), $locale, $domain);
                }
            }
        } catch (\InvalidArgumentException $ex) {
            // Ignore missing I18n directories
        }
    }
}