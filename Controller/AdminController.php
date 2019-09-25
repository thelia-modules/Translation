<?php
/**
 * Created by PhpStorm.
 * User: nicolasbarbey
 * Date: 05/08/2019
 * Time: 13:49
 */

namespace Translation\Controller;


use Symfony\Component\Filesystem\Filesystem;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Tools\URL;
use Translation\Form\ExtensionForm;
use Translation\Translation;


class AdminController extends BaseAdminController
{
    public function showPage()
    {
        // Is the module active ?
        $translationInUse = is_dir($this->getTranslationDir());

        return $this->render('Translation/translation', ['in_use' => $translationInUse]);
    }

    public function setExtensionAction()
    {
        $form = new ExtensionForm($this->getRequest());

        $extensionForm = $this->validateForm($form);

        $extension = $extensionForm->get('extension')->getData();

        Translation::setConfigValue('extension', $extension);

        return $this->generateRedirect(URL::getInstance()->absoluteUrl('/admin/module/translation'));
    }

    public function revertAction()
    {
        $fs = new Filesystem();

        $fs->remove($this->getTranslationDir());

        return $this->generateRedirect(URL::getInstance()->absoluteUrl('/admin/module/translation'));
    }

    protected function getTranslationDir()
    {
        return Translation::TRANSLATIONS_DIR . Translation::getConfigValue('extension', 'undefined');
    }
}
