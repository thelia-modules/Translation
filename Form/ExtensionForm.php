<?php
/**
 * Created by PhpStorm.
 * User: nicolasbarbey
 * Date: 13/08/2019
 * Time: 15:25
 */

namespace Translation\Form;


use Thelia\Form\BaseForm;

class ExtensionForm extends BaseForm
{
    protected function buildForm()
    {
        $form = $this->formBuilder;
        $form
            ->add(
                'extension',
                "choice",
                [
                    'choices' => [
                        'po' => '.po',
                        "xlf" => '.xlf'
                    ]
                ]
            );
    }

    public function getName()
    {
        return "translation-config-form";
    }
}