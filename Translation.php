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
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\Filesystem\Filesystem;
use Thelia\Module\BaseModule;

class Translation extends BaseModule
{
    /** @var string */
    const DOMAIN_NAME = 'translation';

    const TRANSLATIONS_DIR = THELIA_LOCAL_DIR . 'translations' . DS;

    public function postActivation(ConnectionInterface $con = null): void
    {
        if (null === self::getConfigValue('extension')){
            self::setConfigValue('extension' , 'po');
        }
    }

    public static function deleteTmp()
    {
        (new Filesystem())->remove(self::TRANSLATIONS_DIR . 'tmp');
    }

    public static function configureServices(ServicesConfigurator $servicesConfigurator): void
    {
        $servicesConfigurator->load(self::getModuleCode().'\\', __DIR__)
            ->exclude([THELIA_MODULE_DIR . ucfirst(self::getModuleCode()). "/I18n/*"])
            ->autowire(true)
            ->autoconfigure(true);
    }
}
