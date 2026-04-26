<?php

/**
 *
 * (c) Copyright Ascensio System SIA 2026
 *
 * This program is a free software product.
 * You can redistribute it and/or modify it under the terms of the GNU Affero General Public License
 * (AGPL) version 3 as published by the Free Software Foundation.
 * In accordance with Section 7(a) of the GNU AGPL its Section 15 shall be amended to the effect
 * that Ascensio System SIA expressly excludes the warranty of non-infringement of any third-party rights.
 *
 * This program is distributed WITHOUT ANY WARRANTY;
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * For details, see the GNU AGPL at: http://www.gnu.org/licenses/agpl-3.0.html
 *
 * You can contact Ascensio System SIA at 20A-12 Ernesta Birznieka-Upisha street, Riga, Latvia, EU, LV-1050.
 *
 * The interactive user interfaces in modified source and object code versions of the Program
 * must display Appropriate Legal Notices, as required under Section 5 of the GNU AGPL version 3.
 *
 * Pursuant to Section 7(b) of the License you must retain the original Product logo when distributing the program.
 * Pursuant to Section 7(e) we decline to grant you any rights under trademark law for use of our trademarks.
 *
 * All the Product's GUI elements, including illustrations and icon sets, as well as technical
 * writing content are licensed under the terms of the Creative Commons Attribution-ShareAlike 4.0 International.
 * See the License terms at http://creativecommons.org/licenses/by-sa/4.0/legalcode
 *
 */

namespace OCA\Onlyoffice\Listeners;

use Exception;
use OCA\Mail\Service\AccountService;
use OCA\Onlyoffice\AppInfo\Application;
use OCA\Onlyoffice\Events\MailMergeEndedEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IConfig;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\Mail\IMailer;
use OCP\Mail\IMessage;
use Psr\Log\LoggerInterface;
use OCP\L10N\IFactory;
use OCP\Notification\IManager as NotificationIManager;
use OCP\Notification\IncompleteNotificationException;
use OCP\Notification\InvalidValueException;

/**
 * MailMerge Ended Event Listener
 */
class MailMergeEndedListener implements IEventListener {

    /**
     * Translation object
     * @var
     */
    private $trans;

    public function __construct(
        private readonly IConfig $config,
        private readonly IUserManager $userManager,
        private readonly LoggerInterface $logger,
        private readonly IMailer $mailer,
        private readonly NotificationIManager $notificationManager,
        private readonly IFactory $l10nFactory,
        private readonly IURLGenerator $urlGenerator,
        private readonly ?AccountService $accountService
    ) {}

    public function handle(Event $event): void {
        if (!$event instanceof MailMergeEndedEvent) {
            return;
        }

        $user = $this->userManager->get($event->getUserId());

        $lang = $user !== null ? $this->config->getUserValue($user->getUID(), 'core', 'lang', 'en') : 'en';
        $this->trans = $this->l10nFactory->get(Application::class, $lang);

        $this->notifySender($event->getUserId(), $event->getTotalCount(), $event->getErrorCount());
        $this->notifySenderByMail($event->getUserId(), $event->getEmailAddress(), $event->getTotalCount(), $event->getErrorCount());
    }

    private function notifySender($uid, $totalCount, $errorCount): void {
        try {
            $notification = $this->notificationManager->createNotification();
            $notification->setApp(Application::APP_ID)
                ->setDateTime(new \DateTime())
                ->setObject("mailmerge_ended", 'mailmerge_ended')
                ->setSubject("mailmerge_ended", [
                    "totalCount" => $totalCount,
                    "errorCount" => $errorCount,
                ]);
            $notification->setUser($uid);
            $this->notificationManager->notify($notification);
        } catch (InvalidValueException | IncompleteNotificationException $e) {
            $this->logger->error("Invalid value for notification provided: " . $e->getMessage(), ['exception' => $e]);
        }
    }

    private function notifySenderByMail($uid, $email, $totalCount, $errorCount): void {
        try {
            $sentFolderUrl = $this->urlGenerator->getBaseUrl() . '/index.php/apps/mail/';

            if ($this->accountService !== null) {
                $accounts = $this->accountService->findByUserIdAndAddress($uid, $email);
                if (count($accounts) > 0 && ($mailboxId = $accounts[0]->getMailAccount()->getSentMailboxId()) !== null) {
                    $sentFolderUrl = $this->urlGenerator->getBaseUrl() . "/index.php/apps/mail/box/$mailboxId";
                }
            }

            $success = $errorCount < 1;
            $subject = $success
                ? $this->trans->t('Mailing is completed successfully')
                : $this->trans->t('Mailing is completed with some errors');
            $body = $success
                ? $this->trans->t(
                    "You're receiving this email to confirm that your request to send %1\$s messages has been completed. The successfully sent mail messages can be found in your <a href=\"%2\$s\">Sent</a> folder of the Mail module.",
                    [$totalCount, $sentFolderUrl]
                )
                : $this->trans->t(
                    "You're receiving this email to confirm that your request to send %1\$s messages has been processed. However, %2\$s messages could not be sent. Please check the Mail module to review the reasons for the failure.\nThe successfully sent mail messages can be found in your <a href=\"%3\$s\">Sent</a> folder of the Mail module.",
                    [$totalCount, $errorCount, $sentFolderUrl]
                );
            $message = $this->prepareMailMessage($email, $subject, $body);
            $errors = $this->mailer->send($message);

            if (!empty($errors)) {
                $this->logger->error("Email service error: " . json_encode($errors));
            }
        } catch (Exception $e) {
            $this->logger->error($e->getMessage(), [
                'exception' => $e,
            ]);
        }
    }

    private function prepareMailMessage($recipient, $subject, $message): IMessage {
        $template = $this->mailer->createEMailTemplate("onlyoffice.MailMergeEnd");
        $template->setSubject($subject);
        $template->addHeader();
        $template->addHeading($subject);
        $template->addBodyText($message, true);
        $template->addFooter();

        $message = $this->mailer->createMessage();
        $message->setTo([$recipient]);
        $message->useTemplate($template);

        return $message;
    }
}
