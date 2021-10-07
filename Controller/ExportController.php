<?php

namespace Translation\Controller;


use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Translation\Dumper\PhpFileDumper;
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
use Thelia\Tools\URL;
use Translation\Dumper\PoFileDumperWithComments;
use Translation\Form\ExportForm;
use Translation\Translation;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/admin/module/translation/export", name="admin_translation_export")
 */
class ExportController extends BaseAdminController
{
    /**
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Exception
     * @Route("", name="", methods="POST")
     */
    public function exportAction(RequestStack $requestStack, EventDispatcherInterface $dispatcher)
    {
        $translationsArchiveDir = Translation::TRANSLATIONS_DIR . 'archives';
        $translationsTempDir = Translation::TRANSLATIONS_DIR . 'tmp';

        $fs = new Filesystem();

        if (!file_exists($translationsArchiveDir)){
            $fs->mkdir($translationsArchiveDir);
        }

        if (! file_exists($translationsTempDir)){
            $fs->mkdir($translationsTempDir);
        }

        $form = $this->createForm(ExportForm::getName());

        $exportForm  = $this->validateForm($form);

        $lang = LangQuery::create()->filterById($requestStack->getCurrentRequest()->get('lang_id'))->findOne();

        $dirs = $exportForm->get('directories')->getData();
        $ext = Translation::getConfigValue('extension');

        if (in_array('all', $dirs)) {
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
            $this->exportTranslations($dir, $ext, $lang, $translationsTempDir, $requestStack, $dispatcher);
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

            $response = new StreamedResponse();

            $response->headers->set('Content-Type', 'application/zip');
            $response->headers->set('Content-Disposition', 'attachment; filename=' . basename($zipPath));
            $response->headers->set('Content-Length', filesize($zipPath));

            $response->setCallback(function() use ($zipPath) {
                readfile($zipPath);
            });
        } else {
            $this->setupFormErrorContext(
                'No translation' ,
                Translator::getInstance()->trans('No translation found'),
                $form
            );

            $response = $this->generateRedirect(URL::getInstance()->absoluteUrl('/admin/module/translation'));
        }

        Translation::deleteTmp();

        return $response;
    }


    /**
     * @param $dir
     * @param $ext
     * @param Lang $lang
     * @param $tmpDir
     * @throws \Exception
     */
    protected function exportTranslations($dir, $ext, Lang $lang, $tmpDir, RequestStack $requestStack, EventDispatcherInterface $dispatcher)
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
                $templateDir = $requestStack->getCurrentRequest()->get($dir.'_directory_select');
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
                $dumper = new PoFileDumperWithComments();
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

            $keyMetaData = [];

            $dispatcher->dispatch($event, TheliaEvents::TRANSLATION_GET_STRINGS);
            if (null !== $translatableStrings = $event->getTranslatableStrings()) {
                $catalogue = new MessageCatalogue($lang->getLocale());

                foreach ($translatableStrings as $translation) {
                    $key = $translation['text'];

                    if (!isset($keyMetaData[$key])) {
                        $keyMetaData[$key] = '';
                    }

                    // Store files where this string exists. We cannot get the exact line in the file, let's use 1.
                    foreach ($translation['files'] as $file) {
                        $keyMetaData[$key] .= "$file:1 ";
                    }

                    $catalogue->set($key, $translation['translation'], $domain);
                    if ($translation['custom_fallback'] !== '') {
                        $catalogue->set($key, $translation['custom_fallback'], $domain);
                    }
                }

                if ($dir === 'modules') {
                    $key = explode('.' , $domain);
                    $mod = DS . $key[0];
                } else {
                    $mod = '';
                }

                $path = $tmpDir . DS . $ext . DS . $dir . $mod . DS . $domain;

                $dumper->dump($catalogue , ['path' => $path , 'metadata' => $keyMetaData]);
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
        $types = [
            'core' => 'Core',
            'frontOffice' => 'FrontOffice',
            'backOffice'  => 'BackOffice',
            'email' => 'Email',
            'pdf' => 'Pdf'
        ];

        foreach ($modulesNames as $moduleName){
            if ($moduleName[0] !== '.'){
                /** @var Module $module */
                $module = $this->getModule($moduleName);

                if (!$module){
                    continue;
                }

                foreach ($types as $dirName => $type){
                    if ($type === 'Core') {
                        $getDomainFunction = 'getTranslationDomain';
                        $getPathFunction = 'getAbsoluteBaseDir';
                        $templateNames[] = $type;
                    } else {
                        $getDomainFunction = 'get'.$type.'TemplateTranslationDomain';
                        $getPathFunction = 'getAbsolute'.$type.'TemplatePath';
                        $templateNames = [];
                        if (file_exists($module->getAbsoluteBaseDir().DS.'templates'.DS.$dirName)){
                            $templateNames = $this->getTemplateNames($module->getAbsoluteBaseDir().DS.'templates'.DS.$dirName);
                        }
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
