<?php

namespace Translation\Controller;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Translation\Dumper\PhpFileDumper;
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

        $dirs = $exportForm->get('directory')->getData();
        $ext = $exportForm->get('extension')->getData();

        if ($dirs === 'all'){
            $dirs = [
                'frontOffice',
                'backOffice',
                'pdf',
                'email',
                'modules',
                'core'
            ];
        }

        foreach ($dirs as $dir) {
            $this->exportTranslations($dir, $ext, $lang);
        }

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
        $items = [];
        
        switch ($dir){
            case "core":
                $domain =  "core";
                $items[$domain]["directory"] = THELIA_LIB;
                $items[$domain]["i18nDirectory"] = THELIA_LIB . 'Config' . DS . 'I18n';
                break;
            case "modules" :
                $items = $this->getModulesDirectories();
                break;
            default :
                $templateName = $this->camelCaseToUpperSnakeCase($dir);
                $template = new TemplateDefinition('default', constant('TemplateDefinition::'.$templateName));
                $domain = $template->getTranslationDomain();
                $items[$domain]["directory"] = $template->getAbsolutePath();
                $items[$domain]["i18nDirectory"] = $template->getAbsoluteI18nPath();
        }


        switch ($ext){
            case "po":
                $dumper = new PoFileDumper();
                break;
            case "xlf":
                $dumper = new XliffFileDumper();
                break;
            default:
                $dumper = new PhpFileDumper();
        }

        foreach ($items as $domain => $item){

            $directory = $item["directory"];
            $i18nDirectory = $item["i18nDirectory"];

            $explodeDomain = explode('.', $domain);

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
        $types = [ 'Core', 'FrontOffice', 'BackOffice', 'Email', 'Pdf'];
        $domain = null;
        foreach ($modulesNames as $moduleName){
            var_dump($moduleName);
            if ($moduleName[0] !== '.'){
                /** @var Module $module */
                $module = $this->getModule($moduleName);

                foreach ($types as $type){
                    $getDomainFunction = 'get'.$type.'TemplateTranslationDomain';
                    $getPathFunction = 'getAbsolute'.$type.'TemplatePath';
                    $getI18nDirectoryFunction = 'getAbsolute'.$type.'I18nTemplatePath';

                    if ($type === 'Core') {
                        $getDomainFunction = 'getTranslationDomain';
                        $getPathFunction = 'getAbsoluteBaseDir';
                        $getI18nDirectoryFunction = 'getAbsoluteI18nPath';
                    }

                    $domain = $module->$getDomainFunction('default');
                    $path = $module->$getPathFunction('default');
                    $i18nDirectory = $module->$getI18nDirectoryFunction('default');

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

    protected function loadTranslation($directory, $domain, $locale)
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

    protected function camelCaseToUpperSnakeCase($input)
    {
        if ( preg_match ( '/[A-Z]/', $input ) === 0 ) {
            return strtoupper($input);
        }
        $pattern = '/([a-z])([A-Z])/';
        $r = strtoupper(
            preg_replace_callback(
                $pattern,
                function ($a) {
                    return $a[1] . "_" . strtolower ( $a[2] );
                },
                $input
            )
        );

        return $r;
    }
}