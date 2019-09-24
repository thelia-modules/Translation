<?php

namespace Translation\Controller;


use Symfony\Component\Translation\Dumper\PhpFileDumper;
use Symfony\Component\Translation\Dumper\PoFileDumper;
use Symfony\Component\Translation\Dumper\XliffFileDumper;
use Symfony\Component\Translation\MessageCatalogue;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\Translation\TranslationEvent;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Translation\Form\ExportForm;
use Translation\Translation;


class ExportController extends BaseAdminController
{
    /**
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Exception
     */
    public function exportAction()
    {
        $translationsArchiveDir = Translation::TRANSLATIONS_DIR . 'archives';
        $translationsTempDir = Translation::TRANSLATIONS_DIR . 'tmp';

        if (! file_exists($translationsArchiveDir)){
            mkdir($translationsArchiveDir, 0777, true);
        }

        if (! file_exists($translationsTempDir)){
            mkdir($translationsTempDir, 0777, true);
        }

        $form = new ExportForm($this->getRequest());

        $exportForm  = $this->validateForm($form);

        $lang = LangQuery::create()->filterById($this->getRequest()->get('lang_id'))->findOne();

        $dirs = $exportForm->get('directories')->getData();
        $ext = Translation::getConfigValue('extension');

        if (in_array('all', $dirs)){
            $dirs = [
                'frontOffice',
                'backOffice',
                'pdf',
                'email',
                'modules',
                'core',
            ];
        }
        foreach ($dirs as $dir) {
            $this->exportTranslations($dir, $ext, $lang, $translationsTempDir);
        }

        if (file_exists($translationsTempDir.DS.$ext)){
            $dirToZip = $translationsTempDir.DS.$ext;

            $today = new \DateTime();
            $name = 'translation-export-'.$today->format('Y-m-d_H-i-s').'.zip';

            $zipPath = $translationsArchiveDir.DS.$name;

            $zip = new \ZipArchive();
            $zip->open($zipPath, \ZipArchive::CREATE);
            $this->folderToZip($dirToZip, $zip, strlen($translationsTempDir));
            $zip->close();

            Translation::deleteTmp();

            $archives = scandir($translationsArchiveDir);
            $archives = array_slice($archives, 2);
            if (count($archives) > 5)
            {
                for ($i = 0; $i < count($archives)-5; $i++){
                    unlink($translationsArchiveDir.DS.$archives[$i]);
                }
            }

            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename=' . basename($zipPath));
            header('Content-Length: ' . filesize($zipPath));

            readfile($zipPath);
        }

        Translation::deleteTmp();

        $this->setupFormErrorContext(
            'No translation' ,
            $this->getTranslator()->trans('No translation found'),
            $form
        );

        return $this->generateRedirect('/admin/module/translation');
    }


    /**
     * @param $dir
     * @param $ext
     * @param Lang $lang
     * @param $tmpDir
     * @throws \Exception
     */
    protected function exportTranslations($dir, $ext, Lang $lang, $tmpDir)
    {
        $items = [];

        switch ($dir){
            case 'core':
                $domain =  $dir;
                $items[$domain]['directory'] = THELIA_LIB;
                break;
            case 'modules' :
                $items = $this->getModulesDirectories();
                break;
            case 'frontOffice':
            case 'backOffice':
            case 'email':
            case 'pdf' :
                $templateDir = $this->getRequest()->get($dir.'_directory_select');
                $templateName = $this->camelCaseToUpperSnakeCase($dir);
                $template = new TemplateDefinition(
                    $templateDir,
                    constant('Thelia\Core\Template\TemplateDefinition::'.$templateName)
                );
                $domain = $template->getTranslationDomain();
                $items[$domain]['directory'] = $template->getAbsolutePath();
                break;
            default :
                $templateName = $this->camelCaseToUpperSnakeCase($dir);
                $templates = $this->getTemplates(
                    THELIA_TEMPLATE_DIR . $dir . DS,
                    constant('Thelia\Core\Template\TemplateDefinition::'.$templateName)
                );
                foreach ($templates as $template){
                    $domain = $template->getTranslationDomain();
                    $items[$domain]['directory'] = $template->getAbsolutePath();
                }
        }

        switch ($ext){
            case 'po':
                $dumper = new PoFileDumper();
                break;
            case 'xlf':
                $dumper = new XliffFileDumper();
                break;
            default:
                $dumper = new PhpFileDumper();
        }

        foreach ($items as $domain => $item){

            $directory = $item['directory'];

            $explodeDomain = explode('.', $domain);

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

            if ($arrayTranslations != null){
                $catalogue = new MessageCatalogue($lang->getCode());
                $catalogue->add($arrayTranslations);

                $dumper->formatCatalogue($catalogue, 'messages');
                $mod = "";
                if ($dir === 'modules'){
                    $key = explode('.', $domain);
                    $mod = DS.$key[0];
                }
                $path = $tmpDir.DS.$ext.DS.$dir.$mod.DS.$domain;
                $dumper->dump($catalogue, ['path' => $path]);
            }
        }
    }

    /**
     * @param $directory
     * @param $templateDefinition
     * @return array
     * @throws \Exception
     */
    protected function getTemplates($directory, $templateDefinition){

        $templates = [];

        /** @var \DirectoryIterator $templateDirectory */
        foreach (new \DirectoryIterator($directory) as $templateDirectory){
            if ($templateDirectory->isDot()){
                continue;
            }
            if ($templateDirectory->isDir()){
                $templates[] = new TemplateDefinition($templateDirectory->getBasename(), $templateDefinition);
            }
        }
        return $templates;
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
            if ($moduleName[0] !== '.'){
                /** @var Module $module */
                $module = $this->getModule($moduleName);

                if (!$module){
                    continue;
                }

                foreach ($types as $type){
                    $getDomainFunction = 'get'.$type.'TemplateTranslationDomain';
                    $getPathFunction = 'getAbsolute'.$type.'TemplatePath';
                    $templateNames = [];
                    if (file_exists($module->getAbsoluteBaseDir().DS.'templates'.DS.$type)){
                        $templateNames = $this->getTemplateNames($module->getAbsoluteBaseDir().DS.'templates'.DS.$type);
                    }

                    if ($type === 'Core') {
                        $getDomainFunction = 'getTranslationDomain';
                        $getPathFunction = 'getAbsoluteBaseDir';
                        $templateNames[] = $type;
                    }

                    foreach ($templateNames as $templateName){
                        $domain = $module->$getDomainFunction($templateName);
                        $path = $module->$getPathFunction($templateName);

                        if (null !== $domain){
                            $directories[$domain]['directory'] = $path;
                        }
                    }
                }
            }
        }
        return $directories;
    }

    protected function getModule($name)
    {
        return ModuleQuery::create()
            ->filterByCode($name)
            ->filterByActivate(1)
            ->findOne();
    }

    protected function getTemplateNames($directory)
    {
        $names = [];
        foreach (new \DirectoryIterator($directory) as $templateDirectory){
            if ($templateDirectory->isDot()){
                continue;
            }
            if ($templateDirectory->isDir()){
                $names[] = $templateDirectory->getBasename();
            }
        }
        return $names;
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
                    return $a[1] . '_' . strtolower ( $a[2] );
                },
                $input
            )
        );

        return $r;
    }

    /**
     * @param $folder
     * @param \ZipArchive $zipFile
     * @param $exclusiveLength
     */
    protected function folderToZip($folder, &$zipFile, $exclusiveLength) {
        $handle = opendir($folder);
        while (false !== $f = readdir($handle)) {
            if ($f !== '.' && $f !== '..') {
                $filePath = "$folder/$f";
                $localPath = ltrim(str_replace('\\', '/', substr($filePath, $exclusiveLength)), '/');

                if (is_file($filePath)) {
                    $zipFile->addFile($filePath, $localPath);
                } elseif (is_dir($filePath)) {
                    $zipFile->addEmptyDir($localPath);
                    $this->folderToZip($filePath, $zipFile, $exclusiveLength);
                }
            }
        }
        closedir($handle);
    }
}
