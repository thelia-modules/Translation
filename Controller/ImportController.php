<?php
/**
 * Created by PhpStorm.
 * User: nicolasbarbey
 * Date: 13/08/2019
 * Time: 08:59
 */

namespace Translation\Controller;


use Symfony\Component\HttpFoundation\File\UploadedFile;
use Thelia\Controller\Admin\BaseAdminController;
use Translation\Form\ImportForm;
use Translation\Translation;

class ImportController extends BaseAdminController
{
    public function importAction()
    {
        mkdir(Translation::TRANSLATIONS_DIR."tmp");
        $path = Translation::TRANSLATIONS_DIR."tmp".DS;

        $ext = Translation::getConfigValue("extension");
        if (!file_exists(Translation::TRANSLATIONS_DIR.$ext)){
            mkdir(Translation::TRANSLATIONS_DIR.$ext);
        }

        /** @var UploadedFile $importFile */
        $importFile = $this->getRequest()->files->get('file');

        $today = getdate();
        $fileName = "translation-import".$today["mday"]."-".$today["mon"]."-".$today["year"]."_".$today["hours"]."h".$today["minutes"].".zip";

        copy($importFile, $path.$fileName);

        $zip = new \ZipArchive();
        $zip->open($path.$fileName);
        $zip->extractTo($path);
        $zip->close();

        unlink($path.$fileName);
        $this->moveDirectory($path,Translation::TRANSLATIONS_DIR.$ext);
        $this->deleteContent($path);
        rmdir($path);
        return $this->generateRedirect("/admin/module/translation");
    }

    protected function moveDirectory($directory, $newDirectory)
    {
        $dirs = scandir($directory);
        $ext = Translation::getConfigValue("extension");
        if (in_array($ext, $dirs)){
            $this->mergeDirectory($directory.DS.$ext, $newDirectory);
        }else{
            foreach ($dirs as $dir){
                if ($dir[0] !== '.' && is_dir($dir)){
                    $this->moveDirectory($directory.DS.$dir, $newDirectory);
                }
            }
        }
    }

    protected function mergeDirectory($oldDir, $newDir){
        foreach (new \DirectoryIterator($oldDir) as $fileInfo){
            if ($fileInfo->isDot()){
                continue;
            }
            if ($fileInfo->isDir()){
                if (file_exists($newDir.DS.$fileInfo->getBasename())){
                    $this->mergeDirectory($fileInfo->getPathname(), $newDir.DS.$fileInfo->getBasename());
                }else{
                    rename($fileInfo->getPathname(), $newDir.DS.$fileInfo->getBasename());
                }
            }
            if ($fileInfo->isFile()){
                if (file_exists($newDir.DS.$fileInfo->getBasename())){
                    unlink($newDir.DS.$fileInfo->getBasename());
                    rename($fileInfo->getPathname(), $newDir.DS.$fileInfo->getBasename());
                }else{
                    rename($fileInfo->getPathname(), $newDir.DS.$fileInfo->getBasename());
                }
            }
        }
    }

    public static function deleteContent($directory){
        foreach (new \DirectoryIterator($directory) as $fileInfo){
            if ($fileInfo->isDir()){
                if (!$fileInfo->isDot()){
                    ImportController::deleteContent($fileInfo->getPathname());
                    rmdir($fileInfo->getPathname());
                }
            }else{
                unlink($fileInfo->getPathname());
            }
        }
    }
}

