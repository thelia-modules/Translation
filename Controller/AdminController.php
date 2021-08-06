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
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/admin/module/translation", name="admin_translation")
 */

class AdminController extends BaseAdminController
{

    /**
     * @Route("", name="_menu", methods="GET")
     */
    public function showPage()
    {
        // Is the module active ?
        $translationInUse = is_dir($this->getTranslationDir());

        return $this->render('Translation/translation', ['in_use' => $translationInUse]);
    }

    /**
     * @Route("/extension", name="_extension", methods="POST")
     */
    public function setExtensionAction()
    {
        $form = $this->createForm(ExtensionForm::getName());

        $extensionForm = $this->validateForm($form);

        $extension = $extensionForm->get('extension')->getData();

        Translation::setConfigValue('extension', $extension);

        return $this->generateRedirect(URL::getInstance()->absoluteUrl('/admin/module/translation'));
    }

    /**
     * @Route("/revert", name="_revert", methods="GET")
     */
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
