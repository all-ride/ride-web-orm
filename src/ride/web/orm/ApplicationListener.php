<?php

namespace ride\web\orm;

use ride\library\event\Event;
use ride\library\orm\OrmManager;

use ride\web\base\menu\MenuItem;

use \Exception;

class ApplicationListener {

    private $menuNames = array();

    public function prepareContentMenu(Event $event, OrmManager $ormManager) {
        $locale = $event->getArgument('locale');
        $taskbar = $event->getArgument('taskbar');
        $applicationsMenu = $taskbar->getApplicationsMenu();

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

            $menuName = $meta->getOption('scaffold.menu', 'content.menu');

            $this->menuNames[$menuName] = true;

            $menu = $applicationsMenu->getItem($menuName);
            if (!$menu) {
                throw new Exception('Could not add model ' . $meta->getName() . ' to menu ' . $menuName . ': menu does not exist');
            }

            $menu->addMenuItem($menuItem);
        }
    }

    public function sortContentMenu(Event $event) {
        $taskbar = $event->getArgument('taskbar');
        $applicationsMenu = $taskbar->getApplicationsMenu();

        foreach ($this->menuNames as $menuName => $true) {
            $menu = $applicationsMenu->getItem($menuName);
            $menu->orderItems();
        }
    }

}
