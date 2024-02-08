<?php

use App\Draft;
use Movim\Widget\Base;

class PublishHelp extends Base
{
    public function load()
    {
    }

    public function ajaxDrawer()
    {
        $view = $this->tpl();
        Drawer::fill('publish_help', $view->draw('_publishhelp'), true);
    }

    public function prepareToggles(Draft $draft)
    {
        return (new Publish)->prepareToggles($draft);
    }

    public function prepareHelp()
    {
        $view = $this->tpl();
        return $view->draw('_publishhelp');
    }
}
