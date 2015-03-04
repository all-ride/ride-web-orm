<?php

namespace ride\web\orm;

use ride\library\event\Event;
use ride\library\orm\OrmManager;

use ride\web\base\menu\MenuItem;

class ApplicationListener {

    public function prepareContentMenu(Event $event, OrmManager $ormManager) {
        $locale = $event->getArgument('locale');
        $taskbar = $event->getArgument('taskbar');
        $menu = $taskbar->getApplicationsMenu()->getItem('content.menu');

        $models = $ormManager->getModels();
        foreach ($models as $model) {
            $meta = $model->getMeta();
            if (!$meta->getOption('scaffold.expose')) {
                continue;
            }

            $menuItem = new MenuItem();

            $title = $meta->getOption('scaffold.title');
            if ($title) {
                $menuItem->setTranslation($title);
            } else {
                $menuItem->setLabel($meta->getName());
            }

            $menuItem->setRoute('system.orm.scaffold.index', array('locale' => $locale, 'model' => $meta->getName()));

            $menu->addMenuItem($menuItem);
        }
    }

}
