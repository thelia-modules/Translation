<?php
/**
 * Created by PhpStorm.
 * User: nicolasbarbey
 * Date: 05/08/2019
 * Time: 13:31
 */

namespace Translation\Hook;


use Thelia\Core\Event\Hook\HookRenderBlockEvent;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;
use Thelia\Tools\URL;
use Translation\Translation;

class AdminHook extends BaseHook
{
    public function onMainTopMenu(HookRenderEvent $event)
    {
        $event->add(
            $this->render('/hook/main-in-top-menu-items.html', [])
        );
    }

}