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
     */
    protected function exportTranslations($dir, $ext, Lang $lang)
    {

        $directories = [];
        switch ($dir){
            case "coreThelia":
                    $directories["core"] = THELIA_LIB;
                break;
            case "frontOffice" :
                $directories["fo.default"] = THELIA_ROOT . "templates" . DS . TemplateDefinition::FRONT_OFFICE_SUBDIR . DS ."default";
                break;

            case "backOffice" :
                $directories["bo.default"] = THELIA_ROOT . "templates" . DS . TemplateDefinition::BACK_OFFICE_SUBDIR . DS ."default";
                break;

            case "pdf" :
                $directories["pdf.default"] = THELIA_ROOT . "templates" . DS . TemplateDefinition::PDF_SUBDIR . DS ."default";
                break;

            case "email" :
                $directories["email.default"] = THELIA_ROOT . "templates" . DS . TemplateDefinition::EMAIL_SUBDIR . DS ."default";
                break;

            case "modules" :
                $directories = $this->getModulesDirectories();
                break;
        }

        if ($ext === "po"){
            $dumper = new PoFileDumper();
        }
        elseif ($ext === "xlf"){
            $dumper = new XliffFileDumper();
        }
        else{
            die("error");
        }

        foreach ($directories as $domain => $directory){
            $explodeDomain = explode('.',$domain);

            $walkMode = TranslationEvent::WALK_MODE_PHP;
            if (count($explodeDomain) > 1){
                $walkMode = TranslationEvent::WALK_MODE_TEMPLATE;
            }


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
            $message = $arrayTranslations;

            if ($message != null){
                echo $directory;
                dump($message);
                $catalogue = new MessageCatalogue($lang->getCode());
                $catalogue->add($message);

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
        $modulesNames = scandir(THELIA_LOCAL_DIR.'modules');
        $directories = [];
        $types = ['fo', 'bo', 'ma', 'pf', 'co'];
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
                        $this->loadTranslation($i18nDirectory, $domain);
                        $directories[$domain] = $path;
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

    private function loadTranslation($directory, $domain)
    {
        try {
            $finder = Finder::create()
                ->files()
                ->depth(0)
                ->in($directory);

            /** @var \DirectoryIterator $file */
            foreach ($finder as $file) {
                list($locale, $format) = explode('.', $file->getBaseName(), 2);

                Translator::getInstance()->addResource($format, $file->getPathname(), $locale, $domain);
            }
        } catch (\InvalidArgumentException $ex) {
            // Ignore missing I18n directories
        }
    }
}