<?php
/**
 * Created by PhpStorm.
 * User: nicolasbarbey
 * Date: 05/08/2019
 * Time: 13:49
 */

namespace Translation\Controller;


use Thelia\Controller\Admin\BaseAdminController;

class AdminController extends BaseAdminController
{
    public function showPage()
    {
        return $this->render('translation');
    }
}