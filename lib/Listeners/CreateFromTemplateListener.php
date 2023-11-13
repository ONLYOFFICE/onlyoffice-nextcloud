<?php
/**
 *
 * (c) Copyright Ascensio System SIA 2023
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 */

namespace OCA\Onlyoffice\Listeners;

use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Template\FileCreatedFromTemplateEvent;

use OCA\Onlyoffice\TemplateManager;

/**
 * CreateFromTemplate listener
 */
class CreateFromTemplateListener implements IEventListener {

    public function handle(Event $event): void {
        if (!$event instanceof FileCreatedFromTemplateEvent) {
            return;
        }

        $template = $event->getTemplate();
        if ($template === null) {
            $targetFile = $event->getTarget();
            $templateEmpty = TemplateManager::GetEmptyTemplate($targetFile->getName());
            if ($templateEmpty) {
                $targetFile->putContent($templateEmpty);
            }
        }
    }
}
