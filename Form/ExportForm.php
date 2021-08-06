<?php
/**
 * Created by PhpStorm.
 * User: nicolasbarbey
 * Date: 06/08/2019
 * Time: 09:01
 */

namespace Translation\Form;


use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;
use Translation\Translation;

class ExportForm extends BaseForm
{
    protected function buildForm()
    {
        $form = $this->formBuilder;
        $form
            ->add(
                'directories',
                ChoiceType::class,
                [
                    'required' => true,
                    'multiple' => true,
                    'choices' => [
                        Translator::getInstance()->trans('Every translations', [], Translation::DOMAIN_NAME) => "all",
                        Translator::getInstance()->trans('Core Thelia', [], Translation::DOMAIN_NAME) => "core",
                        Translator::getInstance()->trans('Front-office templates', [], Translation::DOMAIN_NAME) => "frontOffice",
                        Translator::getInstance()->trans('Back-office templates', [], Translation::DOMAIN_NAME) => "backOffice",
                        Translator::getInstance()->trans('PDF templates', [], Translation::DOMAIN_NAME) => "pdf",
                        Translator::getInstance()->trans('E-mail templates', [], Translation::DOMAIN_NAME) => "email",
                        Translator::getInstance()->trans('Modules', [], Translation::DOMAIN_NAME) => "modules"
                    ]
                ]
            );
    }

    public static function getName()
    {
        return 'translation-export-form';
    }

}
