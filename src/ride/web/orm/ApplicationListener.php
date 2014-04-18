<?php

namespace ride\web\orm;

use ride\library\event\Event;

use ride\web\base\view\MenuItem;

/**
 * Listener for application events
 */
class ApplicationListener {

    /**
     * Adds a menu item for the ORM backend
     * @param \ride\library\event\Event $event Event of the taskbar
     * @return null
     */
    public function prepareTaskbar(Event $event) {
        $taskbar = $event->getArgument('taskbar');

        $menuItem = new MenuItem();
        $menuItem->setTranslation('title.orm');
        $menuItem->setRoute('system.orm');

        $taskbar->getSettingsMenu()->getItem('title.system')->addMenuItem($menuItem);
    }

}
