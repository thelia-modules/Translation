<?php
/**
 * Created by PhpStorm.
 * User: nicolasbarbey
 * Date: 05/08/2019
 * Time: 13:49
 */

namespace Translation\Controller;


use Thelia\Controller\Admin\BaseAdminController;
use Translation\Form\ExtensionForm;
use Translation\Translation;


class AdminController extends BaseAdminController
{
    public function showPage()
    {
        return $this->render('Translation/translation');
    }

    public function setExtensionAction()
    {
        $form = new ExtensionForm($this->getRequest());

        $extensionForm = $this->validateForm($form);

        $extension = $extensionForm->get("extension")->getData();

        Translation::setConfigValue("extension", $extension);

        return $this->generateRedirect("/admin/module/translation");
    }
}