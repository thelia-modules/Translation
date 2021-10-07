<?php

namespace Translation\Form;

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Thelia\Form\BaseForm;
use Translation\Translation;

class ExtensionForm extends BaseForm
{
    protected function buildForm()
    {
        $form = $this->formBuilder;
        $form
            ->add(
                'extension',
                ChoiceType::class,
                [
                    'data' => Translation::getConfigValue("extension"),
                    'choices' => [
                        '.po' => 'po',
                        ".xlf" => 'xlf'
                    ]
                ]
            );
    }

    public static function getName()
    {
        return "translation-config-form";
    }
}