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
use Thelia\Tools\URL;
use Translation\Translation;
use \RecursiveDirectoryIterator;
use \RecursiveIteratorIterator;

class ImportController extends BaseAdminController
{
    public function getFilePath($dirPath, $fileName) {
        $oDirectory = new RecursiveDirectoryIterator($dirPath);
        $oIterator = new RecursiveIteratorIterator($oDirectory);
        foreach($oIterator as $oFile) {
            if ($oFile->getFilename() == $fileName) {
               return $oFile->getPath();
            }
        }
        return false;
    }

    public function importAction()
    {
        $path = Translation::TRANSLATIONS_DIR . 'tmp';

        if (!file_exists($path)) {
            mkdir($path, 0777 , true);
        }

        $ext = Translation::getConfigValue('extension');

        if (!file_exists(Translation::TRANSLATIONS_DIR . $ext)) {
            mkdir(Translation::TRANSLATIONS_DIR . $ext, 0777 , true);
        }

        /** @var UploadedFile $importFile */
        $importFile = $this->getRequest()->files->get('file');
        if ($importFile) {
          $originalExt = $importFile->getClientOriginalExtension();
          $originalName = $importFile->getClientOriginalName();

          if ($originalExt == 'zip') {

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
          } else {
            $filePath = $this->getFilePath(Translation::TRANSLATIONS_DIR . $ext, $originalName);
            if (!$filePath)
              return $this->generateRedirect(URL::getInstance()->absoluteUrl('/admin/module/translation', ['error' => '1']));
            copy($importFile, $filePath . DS . $originalName);

          }
        }
        else
          return $this->generateRedirect(URL::getInstance()->absoluteUrl('/admin/module/translation', ['error' => '2']));

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
