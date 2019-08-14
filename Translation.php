<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Translation;

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Module\BaseModule;

class Translation extends BaseModule
{
    /** @var string */
    const DOMAIN_NAME = 'translation';

    const TRANSLATIONS_DIR = THELIA_LOCAL_DIR."translations".DS;

    /*
     * You may now override BaseModuleInterface methods, such as:
     * install, destroy, preActivation, postActivation, preDeactivation, postDeactivation
     *
     * Have fun !
     */

    public function postActivation(ConnectionInterface $con = null)
    {
        if (null == Translation::getConfigValue('extension')){
            Translation::setConfigValue("extension", "po");
        }
    }

    public static function deleteTmp()
    {
        $path = self::TRANSLATIONS_DIR."tmp";
        if (file_exists($path)){
            self::deleteContent($path);
        }
        rmdir($path);
    }

    public static function deleteContent($directory){
        foreach (new \DirectoryIterator($directory) as $fileInfo){
            if ($fileInfo->isDir()){
                if (!$fileInfo->isDot()){
                    self::deleteContent($fileInfo->getPathname());
                    rmdir($fileInfo->getPathname());
                }
            }else{
                unlink($fileInfo->getPathname());
            }
        }
    }
}
