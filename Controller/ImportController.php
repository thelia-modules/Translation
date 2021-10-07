<?php
/**
 * Created by PhpStorm.
 * User: nicolasbarbey
 * Date: 13/08/2019
 * Time: 08:59
 */

namespace Translation\Controller;


use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Tools\URL;
use Translation\Translation;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/admin/module/translation/import", name="admin_translation_import")
 */
class ImportController extends BaseAdminController
{
    /**
     * @Route("", name="", methods="POST")
     */
    public function importAction()
    {
        $path = Translation::TRANSLATIONS_DIR . 'tmp';

        $fs = new Filesystem();

        if (!file_exists($path)) {
            $fs->mkdir($path);
        }

        $ext = Translation::getConfigValue('extension');

        if (!file_exists(Translation::TRANSLATIONS_DIR . $ext)) {
            $fs->mkdir(Translation::TRANSLATIONS_DIR . $ext);
        }

        /** @var UploadedFile $importFile */
        $importFile = $this->getRequest()->files->get('file');

        $today = new \DateTime();
        $fileName = 'translation-import-' . $today->format('Y-m-d_H-i-s') . '.zip';

        copy($importFile , $path . DS . $fileName);

        $zip = new \ZipArchive();
        $zip->open($path . DS . $fileName);
        $zip->extractTo($path);
        $zip->close();

        unlink($path . DS . $fileName);
        $this->moveDirectory($path , Translation::TRANSLATIONS_DIR . $ext , $ext);
        Translation ::deleteTmp();

        return $this->generateRedirect(URL::getInstance()->absoluteUrl('/admin/module/translation'));
    }

    protected function moveDirectory($directory , $newDirectory , $ext)
    {
        $dirs = scandir($directory);

        if (in_array($ext , $dirs)) {
            $this->mergeDirectory($directory . DS . $ext , $newDirectory);
        } else {
            foreach ($dirs as $dir) {
                if ($dir[0] !== '.' && is_dir($dir)) {
                    $this->moveDirectory($directory . DS . $dir , $newDirectory , $ext);
                }
            }
        }
    }

    protected function mergeDirectory($oldDir , $newDir)
    {
        foreach (new \DirectoryIterator($oldDir) as $fileInfo) {
            if ($fileInfo->isDot()) {
                continue;
            }
            if ($fileInfo->isDir()) {
                if (file_exists($newDir . DS . $fileInfo->getBasename())) {
                    $this->mergeDirectory($fileInfo->getPathname() , $newDir . DS . $fileInfo->getBasename());
                } else {
                    rename($fileInfo->getPathname() , $newDir . DS . $fileInfo->getBasename());
                }
            }
            if ($fileInfo->isFile()) {
                if (file_exists($newDir . DS . $fileInfo->getBasename())) {
                    unlink($newDir . DS . $fileInfo->getBasename());
                }
                rename($fileInfo->getPathname() , $newDir . DS . $fileInfo->getBasename());
            }
        }
    }
}

